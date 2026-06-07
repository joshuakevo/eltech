<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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

        // Delete old logo if exists
        $existing = SystemSetting::get('org_logo');
        if ($existing && Storage::disk('public')->exists($existing)) {
            Storage::disk('public')->delete($existing);
        }

        $path = $request->file('logo')->store('logos', 'public');

        SystemSetting::set('org_logo', $path);

        return back()->with('success', 'Organisation logo updated successfully.');
    }

    public function removeLogo()
    {
        $existing = SystemSetting::get('org_logo');
        if ($existing && Storage::disk('public')->exists($existing)) {
            Storage::disk('public')->delete($existing);
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
}
