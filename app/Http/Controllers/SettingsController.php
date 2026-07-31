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
}
