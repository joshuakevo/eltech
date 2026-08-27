<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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

        return back()->with($fixed > 0 ? 'success' : 'success', $msg);
    }

    /** Seeders safe to trigger from the browser — deliberately not free-text input. */
    private const RUNNABLE_SEEDERS = [
        'LoanPenaltyTierSeeder'      => 'Loan Penalty Tiers',
        'SavingsInterestTierSeeder'  => 'Savings Interest Tiers',
        'RolesAndPermissionsSeeder'  => 'Roles & Permissions (resets super_admin/admin/cashier/staff to code defaults)',
        'ChartOfAccountsSeeder'      => 'Chart of Accounts (adds any new accounts; safe to re-run, existing accounts untouched)',
    ];

    public function migrate()
    {
        Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();

        return back()->with('success', "Migrations run.\n\n{$output}");
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

    /**
     * Additive follow-up: corrects start date/term/maturity on the 17 active FDs
     * the July migration created, using the real FixedSvChk ledger instead of the
     * generic product term it guessed. No GL changes -- principal is unchanged.
     * Run this after the migration above.
     */
    public function fixJulyFdTerms()
    {
        Artisan::call('eltech:fix-fd-terms-2026-07-31', ['--confirm' => true]);
        $output = Artisan::output();

        return back()->with('success', "FD term fix run.\n\n{$output}");
    }

    /**
     * Additive follow-up: corrects rate/term/category/disbursement date on the 54
     * active loans the July migration created, using the real LoanInfo.xlsx +
     * active-loans report. Reclassifies the GL receivable account where the loan
     * category changes the product. Run this after the migration above.
     */
    public function fixJulyLoanTerms()
    {
        Artisan::call('eltech:fix-loan-terms-2026-07-31', ['--confirm' => true]);
        $output = Artisan::output();

        return back()->with('success', "Loan term fix run.\n\n{$output}");
    }

    /**
     * Additive follow-up: builds a forward-looking repayment schedule (from
     * today to maturity) for loans the term fix corrected -- spreads the
     * existing reconciled outstanding balance rather than recomputing interest
     * from scratch. Run this after the loan term fix above.
     */
    public function generateJulyLoanSchedules()
    {
        Artisan::call('eltech:generate-loan-schedules-2026-07-31', ['--confirm' => true]);
        $output = Artisan::output();

        return back()->with('success', "Loan schedule generation run.\n\n{$output}");
    }

    /**
     * One-time additive import: the old system's separate Lock Up Report (76
     * frozen/non-performing loans) is not reflected anywhere in the statement
     * migration. Creates 14 new clients (from the XX-excluded list, since they
     * carry real locked-up debt) and 76 loans against a dedicated Locked-Up
     * Loans receivable account. Run the Chart of Accounts seeder first if it
     * hasn't been re-run since this was added (adds the 1104/1112 accounts).
     */
    public function importJulyLockedUpLoans()
    {
        Artisan::call('eltech:import-locked-up-loans-2026-07-31', ['--confirm' => true]);
        $output = Artisan::output();

        return back()->with('success', "Locked-up loans import run.\n\n{$output}");
    }

    /**
     * One-time additive import: posts the institutional balance sheet accounts
     * (cash/bank/mobile-money, investments, fixed assets, loan provisions,
     * other liabilities, combined Retained Earnings) from the old system's
     * 31/07/2026 Trial Balance that the statement migration never touched.
     * Not client-tagged. Independent of the locked-up loans import above (no
     * ordering dependency), but both need the Chart of Accounts seeder re-run
     * first for their new accounts to exist.
     */
    public function trueUpJulyBalanceSheet()
    {
        Artisan::call('eltech:true-up-balance-sheet-2026-07-31', ['--confirm' => true]);
        $output = Artisan::output();

        return back()->with('success', "Balance sheet true-up run.\n\n{$output}");
    }

    /**
     * One-time additive import: replaces the lumped Jan-Jul 2026 YTD deficit
     * inside 3002 Retained Earnings with the old system's 41 individual
     * income/expense account balances for that period. Run this after the
     * Chart of Accounts seeder (adds the new 4100/5100 series accounts) and
     * after the balance sheet true-up above (which is what posted the
     * lumped figure this unbundles).
     */
    public function importJulyPLDetail()
    {
        Artisan::call('eltech:import-pl-detail-jan-jul-2026', ['--confirm' => true]);
        $output = Artisan::output();

        return back()->with('success', "Jan-Jul 2026 P&L detail import run.\n\n{$output}");
    }

    /**
     * Dry-run preview for the interest-rate/rescheduling fix below -- shows exactly
     * what would change (old vs new rate, which loans get a full schedule rebuild
     * vs a date-only re-anchor) without writing anything. Always run this first.
     */
    public function previewLoanInterestFix()
    {
        Artisan::call('eltech:fix-loan-interest-rates');
        $output = Artisan::output();

        return back()->with('success', "Preview only -- nothing was changed.\n\n{$output}");
    }

    /**
     * Every loan's interest rate was entered as a MONTHLY percentage, but the
     * system treats interest_rate as annual everywhere -- understating interest
     * by 12x. This annualises every loan's rate (x12) and rebuilds/re-anchors
     * schedules so installments fall on each loan's real disbursement
     * day-of-month instead of the month-end dates the 31/07/2026 migration left
     * behind. Loans already reconciled against the old system's real balances
     * keep their principal/interest amounts untouched -- only their due dates
     * move. Preview above first.
     */
    public function fixLoanInterestRates()
    {
        $exitCode = Artisan::call('eltech:fix-loan-interest-rates', ['--confirm' => true]);
        $output = Artisan::output();

        if ($exitCode !== 0) {
            return back()->with('error', "Loan interest rate fix was NOT applied.\n\n{$output}");
        }

        return back()->with('success', "Loan interest rate fix applied.\n\n{$output}");
    }

    public function previewLoanInstallmentFix()
    {
        Artisan::call('eltech:fix-loan-installment-schedules');
        $output = Artisan::output();

        return back()->with('success', "Preview only -- nothing was changed.\n\n{$output}");
    }

    /**
     * Rebuilds active loans' installment schedules as a real amortization of
     * the amount disbursed over the full disbursement-to-maturity term,
     * replacing schedules that were instead spread evenly over the months
     * remaining to maturity. Does not touch interest_rate -- run the Loan
     * Interest Rate fix above first if it hasn't been applied yet.
     */
    public function fixLoanInstallments()
    {
        Artisan::call('eltech:fix-loan-installment-schedules', ['--confirm' => true]);
        $output = Artisan::output();

        return back()->with('success', "Loan installment schedule fix applied.\n\n{$output}");
    }

    public function previewNk00221LoanTermFix()
    {
        Artisan::call('eltech:fix-nk00221-loan-term');
        $output = Artisan::output();

        return back()->with('success', "Preview only -- nothing was changed.\n\n{$output}");
    }

    /**
     * One-off correction: LN-NK00221's real term is 2 months (matures
     * 13/07/2026), not the 4 months recorded from the 31/07/2026
     * reconciliation source. See FixNk00221LoanTerm for details.
     */
    public function fixNk00221LoanTerm()
    {
        Artisan::call('eltech:fix-nk00221-loan-term', ['--confirm' => true]);
        $output = Artisan::output();

        return back()->with('success', "LN-NK00221 term fix applied.\n\n{$output}");
    }

    public function previewLoanRatesBaselineRestore()
    {
        Artisan::call('eltech:restore-loan-rates-from-baseline');
        $output = Artisan::output();

        return back()->with('success', "Preview only -- nothing was changed.\n\n{$output}");
    }

    /**
     * One-off repair: a second run of the loan interest rate fix on
     * 2026-08-27 re-multiplied repayment-free loans' interest_rate by 12 and
     * corrupted a few newer loans' outstanding_interest. Restores exact
     * values from a database backup taken 2026-08-27 12:56, before that
     * second run -- see RestoreLoanRatesFromBaseline for the full story.
     */
    public function restoreLoanRatesBaseline()
    {
        $exitCode = Artisan::call('eltech:restore-loan-rates-from-baseline', ['--confirm' => true]);
        $output = Artisan::output();

        if ($exitCode !== 0) {
            return back()->with('error', "Loan rates baseline restore was NOT applied.\n\n{$output}");
        }

        return back()->with('success', "Loan rates restored from baseline.\n\n{$output}");
    }

    public function previewTruncateLegacyLoanSchedules()
    {
        Artisan::call('eltech:truncate-legacy-loan-schedules');
        $output = Artisan::output();

        return back()->with('success', "Preview only -- nothing was changed.\n\n{$output}");
    }

    /**
     * One-off correction: some legacy (31/07/2026 migration) loans had their
     * schedule wrongly rebuilt from the real disbursement date, producing
     * installments for months before August that shouldn't exist since the
     * client's balance was already reconciled and transferred as of
     * 31/07/2026. Removes only the pre-August rows -- every kept
     * installment's amount and due date is left exactly as it is, since
     * that's the client's real agreed installment, not something to
     * recalculate.
     */
    public function truncateLegacyLoanSchedules()
    {
        Artisan::call('eltech:truncate-legacy-loan-schedules', ['--confirm' => true]);
        $output = Artisan::output();

        return back()->with('success', "Legacy loan schedules truncated to August onward.\n\n{$output}");
    }

    public function previewRebuildLegacyLoanSchedules()
    {
        Artisan::call('eltech:rebuild-legacy-loan-schedules');
        $output = Artisan::output();

        return back()->with('success', "Preview only -- nothing was changed.\n\n{$output}");
    }

    /**
     * One-off correction: some legacy loans' real remaining balance is the one
     * implied by their original disbursement-anchored amortization table (the
     * 31/07/2026 migration's reconciled interest was incomplete for them) --
     * confirmed against LN-MK00106. Only rebuilds a loan when the table's
     * remaining principal already closely matches its reconciled
     * outstanding_principal (proof its real payments tracked the clean
     * schedule); every other loan is left untouched and flagged for manual
     * review, since guessing at a mismatch that large risks over- or
     * under-billing a real client.
     */
    public function rebuildLegacyLoanSchedules()
    {
        Artisan::call('eltech:rebuild-legacy-loan-schedules', ['--confirm' => true]);
        $output = Artisan::output();

        return back()->with('success', "Legacy loan schedules rebuilt from disbursement where safe.\n\n{$output}");
    }

    /**
     * One-time additive import: populates client_segments and each client's
     * segment_id / relationship_manager_id from the legacy system's client
     * export (database/data/2026-08-client-segments-rm-import.csv). Creates
     * a User (staff role) for each relationship manager name that doesn't
     * already exist. Also reports legacy rows marked CLOSE for manual review
     * via Administration > Close Accounts.
     */
    public function importClientSegmentsRm()
    {
        Artisan::call('eltech:import-client-segments-rm-2026-08', ['--confirm' => true]);
        $output = Artisan::output();

        return back()->with('success', "Client segments & relationship managers import run.\n\n{$output}");
    }

    /**
     * Follow-up to importClientSegmentsRm() above: assigns the Konnect Sacco
     * segment and Shaddai Kamoga as RM to any client the legacy export left
     * unassigned (blank in the source data, or not present in the export at
     * all). Run this after the import above. Safe to re-run.
     */
    public function assignDefaultSegmentRm()
    {
        Artisan::call('eltech:assign-default-segment-rm-2026-08', ['--confirm' => true]);
        $output = Artisan::output();

        return back()->with('success', "Default segment & RM assignment run.\n\n{$output}");
    }

    /**
     * Read-only diagnostic for "Income Statement segment filter always
     * returns zero" -- writes nothing, safe to run any time.
     */
    /**
     * One-time follow-up for installs that ran importClientSegmentsRm() above
     * before is_relationship_manager existed -- flags every user already
     * assigned as a client's RM so they don't vanish from the Clients RM
     * dropdown, which now filters on that flag. Safe to re-run.
     */
    public function flagExistingRms()
    {
        Artisan::call('eltech:flag-existing-rms-2026-08', ['--confirm' => true]);
        $output = Artisan::output();

        return back()->with('success', "Existing RM flagging run.\n\n{$output}");
    }

    public function diagnoseSegmentIncomeStatement(Request $request)
    {
        $args = [];
        if ($request->filled('from')) $args['--from'] = $request->from;
        if ($request->filled('to'))   $args['--to']   = $request->to;

        Artisan::call('eltech:diagnose-segment-income-statement', $args);
        $output = Artisan::output();

        return back()->with('success', "Segment/Income Statement diagnostic run.\n\n{$output}");
    }
}
