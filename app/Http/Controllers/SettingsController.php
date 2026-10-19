<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::whereNotIn('key', ['org_logo'])
            ->orderBy('group')->orderBy('label')->get()->groupBy('group');
        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $submitted = $request->input('settings', []);

        // For boolean settings, unchecked checkboxes are absent from the request.
        // Explicitly set them to 0 when missing.
        $booleanKeys = SystemSetting::where('type', 'boolean')->pluck('key');
        foreach ($booleanKeys as $key) {
            SystemSetting::set($key, isset($submitted[$key]) ? 1 : 0);
        }

        foreach ($submitted as $key => $value) {
            SystemSetting::set($key, $value);
        }

        return back()->with('success', 'Settings saved successfully.');
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        $logosDir = public_path('logos');
        if (!is_dir($logosDir)) {
            mkdir($logosDir, 0755, true);
        }

        // Delete old logo if exists
        $existing = SystemSetting::get('org_logo');
        if ($existing && file_exists(public_path($existing))) {
            @unlink(public_path($existing));
        }

        $file = $request->file('logo');
        $filename = 'logos/' . uniqid('logo_') . '.' . $file->getClientOriginalExtension();
        $file->move($logosDir, basename($filename));

        SystemSetting::set('org_logo', $filename);

        return back()->with('success', 'Organisation logo updated successfully.');
    }

    public function removeLogo()
    {
        $existing = SystemSetting::get('org_logo');
        if ($existing && file_exists(public_path($existing))) {
            @unlink(public_path($existing));
        }

        SystemSetting::set('org_logo', '');

        return back()->with('success', 'Logo removed.');
    }

    public function reconcile()
    {
        Artisan::call('eltech:reconcile');
        $output = Artisan::output();

        // Count fixed vs ok from output
        preg_match('/Fixed:\s*(\d+)/', $output, $fixedMatch);
        preg_match('/Already correct:\s*(\d+)/', $output, $okMatch);
        $fixed = $fixedMatch[1] ?? '?';
        $ok    = $okMatch[1] ?? '?';

        $msg = "Reconciliation complete. Fixed: {$fixed} field(s). Already correct: {$ok}.";
        if ($fixed > 0) {
            // Include details of what was fixed
            $lines = collect(explode("\n", $output))
                ->filter(fn($l) => str_contains($l, '['))
                ->map(fn($l) => trim(strip_tags($l)))
                ->filter()
                ->implode(' | ');
            if ($lines) {
                $msg .= " Details: {$lines}";
            }
        }

        return back()->with('success', $msg);
    }

    /** Seeders safe to trigger from the browser — deliberately not free-text input. */
    private const RUNNABLE_SEEDERS = [
        'LoanPenaltyTierSeeder'      => 'Loan Penalty Tiers',
        'SavingsInterestTierSeeder'  => 'Savings Interest Tiers',
        'RolesAndPermissionsSeeder'  => 'Roles & Permissions (resets super_admin/admin/cashier/staff to code defaults)',
        'ChartOfAccountsSeeder'      => 'Chart of Accounts (adds any new accounts; safe to re-run, existing accounts untouched)',
    ];

    /**
     * Read-only list of running loans set to Flat interest, with the installment each method
     * gives, so loans that are really reducing balance can be switched.
     */
    public function loanInterestMethods()
    {
        $corrections = app(\App\Services\LoanCorrectionService::class);
        $loans = \App\Models\Loan::with('client', 'product')
            ->whereIn('status', ['active', 'defaulted'])
            ->where('interest_method', 'flat')
            ->whereHas('product', fn ($q) => $q->where('name', '!=', 'Locked-Up Loans'))
            ->orderBy('loan_product_id')->orderBy('loan_number')
            ->get()
            ->each(function ($loan) use ($corrections) {
                $loan->flat_installment = $corrections->termsInstallment($loan);
                $reducing = clone $loan;
                $reducing->interest_method = 'reducing';
                $loan->reducing_installment = $corrections->termsInstallment($reducing);
            });
        $products = \App\Models\LoanProduct::where('name', '!=', 'Locked-Up Loans')->orderBy('name')->get();

        // Running loans whose own method differs from their product's (e.g. switched to reducing but still on "… (Flat)")
        $mismatched = \App\Models\Loan::with('client', 'product')
            ->whereIn('status', ['active', 'defaulted'])
            ->whereHas('product', fn ($q) => $q->where('name', '!=', 'Locked-Up Loans'))
            ->get()
            ->filter(fn ($l) => $l->product && $l->interest_method !== $l->product->interest_method)
            ->each(fn ($l) => $l->setAttribute('target_product', $this->matchingProduct($l, $products)))
            ->sortBy('loan_number')->values();

        return view('settings.loan-interest-methods', compact('loans', 'products', 'mismatched'));
    }

    /**
     * The product a loan should sit on for its own interest method: same name apart from "(Flat)" / "(Reducing)",
     * that method, and the same receivable / interest / penalty accounts — so moving it changes no balances.
     */
    private function matchingProduct(\App\Models\Loan $loan, $products = null): ?\App\Models\LoanProduct
    {
        $current = $loan->product;
        if (!$current || $loan->interest_method === $current->interest_method) {
            return null;
        }
        $base = fn ($name) => strtolower(trim(preg_replace('/\((flat|reducing)[^)]*\)/i', '', $name)));
        $products ??= \App\Models\LoanProduct::where('name', '!=', 'Locked-Up Loans')->get();

        return $products->first(fn ($p) => (int) $p->id !== (int) $current->id
            && $p->is_active
            && $p->interest_method === $loan->interest_method
            && $base($p->name) === $base($current->name)
            && (int) $p->receivable_account_id === (int) $current->receivable_account_id
            && (int) $p->interest_income_account_id === (int) $current->interest_income_account_id
            && (int) $p->penalty_income_account_id === (int) $current->penalty_income_account_id);
    }

    /** Moves ticked loans to the product matching their method (label only — accounts are identical, no journal). */
    public function moveLoansToMatchingProduct(Request $request)
    {
        $request->validate(['move_ids' => 'required|array|min:1', 'move_ids.*' => 'integer']);
        $moved = 0;
        foreach (\App\Models\Loan::with('product')->whereIn('id', $request->move_ids)->get() as $loan) {
            if ($target = $this->matchingProduct($loan)) {
                $from = $loan->product->name;
                $loan->update(['loan_product_id' => $target->id]);
                \App\Models\AuditLog::record('loan_product_moved', "Moved {$loan->loan_number} from {$from} to {$target->name} (matches its {$loan->interest_method} method; same accounts, no journal)", 'loans');
                $moved++;
            }
        }
        return redirect()->route('settings.loan-interest-methods')->with('success', "{$moved} loan(s) moved to the product matching their interest method.");
    }

    /**
     * Switches the ticked loans (and optionally products) from Flat to Reducing. Only the method
     * changes: balances and journals are untouched (Run Loans already charges interest on the
     * reducing balance); it drives the loan terms shown and how a balance correction rebuilds.
     */
    public function applyLoanInterestMethods(Request $request)
    {
        $request->validate([
            'loan_ids'      => 'array',
            'loan_ids.*'    => 'integer',
            'product_ids'   => 'array',
            'product_ids.*' => 'integer',
        ]);

        $loans = \App\Models\Loan::whereIn('id', $request->input('loan_ids', []))
            ->where('interest_method', 'flat')->get();
        $products = \App\Models\LoanProduct::whereIn('id', $request->input('product_ids', []))
            ->where('interest_method', 'flat')->where('name', '!=', 'Locked-Up Loans')->get();

        if ($loans->isEmpty() && $products->isEmpty()) {
            return back()->with('error', 'Tick at least one loan or product to switch.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($loans, $products) {
            foreach ($loans as $loan) {
                $loan->update(['interest_method' => 'reducing']);
                \App\Models\AuditLog::record('loan_interest_method', "Changed {$loan->loan_number} interest method from flat to reducing", 'loans');
                // …and onto the matching "(Reducing)" product when there is one with the same accounts
                if ($target = $this->matchingProduct($loan->fresh('product'))) {
                    $from = $loan->product->name ?? '';
                    $loan->update(['loan_product_id' => $target->id]);
                    \App\Models\AuditLog::record('loan_product_moved', "Moved {$loan->loan_number} from {$from} to {$target->name} (same accounts, no journal)", 'loans');
                }
            }
            foreach ($products as $product) {
                $product->update(['interest_method' => 'reducing']);
                \App\Models\AuditLog::record('loan_product_interest_method', "Changed loan product {$product->name} interest method from flat to reducing", 'loans');
            }
        });

        return redirect()->route('settings.loan-interest-methods')->with('success',
            $loans->count() . ' loan(s)' . ($products->isNotEmpty() ? ' and ' . $products->count() . ' product(s)' : '') . ' switched to reducing balance.');
    }

    /**
     * Moves maturity payouts of closed FDs that went to Cash into the client's
     * savings account. Preview unless the "confirm" button was used.
     */
    public function fdMaturityToSavings(Request $request)
    {
        $request->validate(['deposits' => 'required|string|max:500']);

        $deposits = preg_split('/[\s,]+/', trim($request->deposits), -1, PREG_SPLIT_NO_EMPTY);
        $args     = ['deposits' => $deposits];
        if ($request->boolean('post')) {
            $args['--confirm'] = true;
        }

        Artisan::call('eltech:fd-maturity-cash-to-savings', $args);

        return back()->with('success', Artisan::output());
    }

    /**
     * Read-only check: compares Locked-Up Loans against the old system's Lock
     * Up Report bundle and reports drift/missing rows without changing anything.
     */
    public function verifyLockedUpLoans()
    {
        Artisan::call('eltech:verify-locked-up-loans');
        $output = Artisan::output();

        return back()->with('success', $output);
    }

    /**
     * Corrects outstanding_principal/outstanding_interest on Locked-Up Loans
     * that already exist but drifted from the old system's figures. Never
     * creates loans/clients -- see importLockedUpLoans() for that.
     */
    public function fixLockedUpLoans()
    {
        Artisan::call('eltech:verify-locked-up-loans', ['--fix' => true]);
        $output = Artisan::output();

        return back()->with('success', $output);
    }

    /**
     * Creates any Locked-Up Loans from the old system's Lock Up Report that
     * don't exist here at all yet. Safe to run repeatedly -- skips any
     * LU-<fcode> loan that already exists.
     */
    public function importLockedUpLoans()
    {
        Artisan::call('eltech:import-locked-up-loans-2026-07-31', ['--confirm' => true]);
        $output = Artisan::output();

        return back()->with('success', $output);
    }

    /**
     * One-off: creates the "delete transactions" permission (the journal
     * entry Delete action used to be hardcoded to role:super_admin) and
     * grants it to main_cashier + super_admin. Additive only.
     */
    public function addDeleteTransactionsPermission()
    {
        Artisan::call('eltech:add-delete-transactions-permission', ['--confirm' => true]);
        $output = Artisan::output();

        return back()->with('success', $output);
    }

    /**
     * The Roles & Permissions screen claims super_admin always has every
     * permission, but nothing in code actually enforces that -- it can
     * silently drift out of sync whenever a new permission is added. This
     * restores it. Safe to run any time; only ever adds, never removes.
     */
    public function syncSuperAdminPermissions()
    {
        Artisan::call('eltech:sync-super-admin-permissions', ['--confirm' => true]);
        $output = Artisan::output();

        return back()->with('success', $output);
    }

    public function migrate()
    {
        Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();

        return back()->with('success', "Migrations run.\n\n{$output}");
    }

    /**
     * Dry run: lists pending migrations and the SQL each would execute, without
     * touching the database (Laravel's --pretend mode).
     */
    public function previewMigrations()
    {
        Artisan::call('migrate', ['--pretend' => true, '--force' => true]);
        $output = trim(Artisan::output());

        return back()->with('migration_preview', $output !== '' ? $output : 'Nothing to migrate.');
    }

    public function seed(Request $request)
    {
        $request->validate([
            'seeder' => 'required|in:' . implode(',', array_keys(self::RUNNABLE_SEEDERS)),
        ]);

        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\' . $request->seeder,
            '--force' => true,
        ]);
        $output = Artisan::output();

        return back()->with('success', self::RUNNABLE_SEEDERS[$request->seeder] . " seeder run.\n\n{$output}");
    }

    public function clearCache()
    {
        Artisan::call('view:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('clear-compiled');

        $opcache = 'not available on this server';
        if (function_exists('opcache_reset')) {
            $opcache = opcache_reset() ? 'reset' : 'reset failed';
        }

        return back()->with('success', "View, route, config, and compiled caches cleared. OPcache: {$opcache}.");
    }

    /**
     * Reports the outbound IP this server actually uses to reach the internet -- the
     * one gateways like MarzPay need for IP whitelisting. On shared hosting this can
     * differ from the "shared IP" shown in cPanel, so we ask an external echo service
     * rather than reading any local config.
     */
    public function checkServerIp()
    {
        try {
            $response = Http::timeout(10)->get('https://api.ipify.org', ['format' => 'json']);
            $ip = $response->json('ip');

            if (!$ip) {
                return back()->with('error', 'Could not determine the outbound IP. Raw response: ' . $response->body());
            }

            return back()->with('success', "This server's outbound IP is: {$ip}\n\nAdd this to MarzPay Dashboard > IP Whitelist.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not reach the IP-check service: ' . $e->getMessage());
        }
    }
}
