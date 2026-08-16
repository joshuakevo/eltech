@extends('layouts.app')
@section('title','System Settings')
@section('content')
<div class="mb-4">
    <h4 class="mb-0 fw-semibold">System Settings</h4>
    <p class="text-muted small mb-0">Configure application-wide parameters</p>
    <p class="text-muted small mb-0">Deploy check: 2026-08-16-a</p>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i><span style="white-space:pre-line">{{ session('success') }}</span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form method="POST" action="{{ route('settings.update') }}">
@csrf

<div class="row g-4">
@php
$groupIcons = [
    'general'  => ['icon'=>'bi-building','label'=>'Organisation','color'=>'primary'],
    'financial'=> ['icon'=>'bi-currency-dollar','label'=>'Financial','color'=>'success'],
    'mail'     => ['icon'=>'bi-envelope-fill','label'=>'Email / Mail','color'=>'info'],
    'modules'  => ['icon'=>'bi-toggles','label'=>'Modules','color'=>'warning'],
    'system'   => ['icon'=>'bi-gear','label'=>'System','color'=>'secondary'],
];
@endphp

@foreach($settings as $group => $items)
@php $meta = $groupIcons[$group] ?? ['icon'=>'bi-sliders','label'=>ucfirst($group),'color'=>'secondary'] @endphp
<div class="col-12">
    <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center gap-2 fw-semibold">
            <i class="bi {{ $meta['icon'] }} text-{{ $meta['color'] }}"></i>
            {{ $meta['label'] }} Settings
        </div>
        <div class="card-body">
            <div class="row g-3">
            @foreach($items as $setting)
            <div class="{{ in_array($setting->type,['textarea']) ? 'col-12' : 'col-sm-6 col-lg-4' }}">
                <label class="form-label fw-semibold">{{ $setting->label }}</label>

                @if($setting->type === 'boolean')
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox"
                               name="settings[{{ $setting->key }}]"
                               id="s_{{ $setting->key }}" value="1"
                               {{ $setting->value ? 'checked' : '' }}>
                        <label class="form-check-label text-muted" for="s_{{ $setting->key }}">
                            {{ $setting->value ? 'Enabled' : 'Disabled' }}
                        </label>
                    </div>
                @elseif($setting->type === 'textarea')
                    <textarea name="settings[{{ $setting->key }}]" class="form-control" rows="2">{{ $setting->value }}</textarea>
                @elseif($setting->type === 'number')
                    <input type="number" name="settings[{{ $setting->key }}]"
                           class="form-control" value="{{ $setting->value }}" step="any">
                @else
                    <input type="text" name="settings[{{ $setting->key }}]"
                           class="form-control" value="{{ $setting->value }}">
                @endif
            </div>
            @endforeach
            </div>
        </div>
    </div>
</div>
@endforeach

</div>

<div class="mt-4 d-flex align-items-center gap-3 flex-wrap">
    <button type="submit" class="btn btn-primary px-4">
        <i class="bi bi-floppy me-2"></i>Save All Settings
    </button>
</div>
</form>

{{-- Organisation Logo --}}
@php $logoPath = \App\Models\SystemSetting::get('org_logo'); @endphp
<div class="card mt-4 shadow-sm">
    <div class="card-header d-flex align-items-center gap-2 fw-semibold">
        <i class="bi bi-image text-primary"></i> Organisation Logo
    </div>
    <div class="card-body">
        <div class="row align-items-center g-4">
            <div class="col-auto">
                @if($logoPath)
                    <img src="{{ asset($logoPath) }}" alt="Logo"
                         class="rounded border" style="height:80px;max-width:200px;object-fit:contain">
                @else
                    <div class="rounded border d-flex align-items-center justify-content-center bg-light text-muted"
                         style="height:80px;width:160px;font-size:12px">
                        <i class="bi bi-image me-1"></i> No logo set
                    </div>
                @endif
            </div>
            <div class="col">
                <form method="POST" action="{{ route('settings.logo') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <input type="file" name="logo" id="logoInput" class="form-control form-control-sm @error('logo') is-invalid @enderror"
                               accept=".png,.jpg,.jpeg,.svg,.webp" style="max-width:280px">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-upload me-1"></i>Upload Logo
                        </button>
                    </div>
                    <div class="form-text mt-1">PNG, JPG, SVG or WebP. Max 2 MB. Recommended: transparent PNG, at least 200×60 px.</div>
                    @error('logo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </form>
                @if($logoPath)
                <form method="POST" action="{{ route('settings.logo.remove') }}" class="mt-2"
                      onsubmit="return confirm('Remove the logo?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash me-1"></i>Remove Logo
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Data Integrity --}}
<div class="card mt-4 border-warning">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-shield-check text-warning"></i>
        <span>Data Integrity &amp; Reconciliation</span>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Recalculates all denormalized/cached fields from their source of truth — savings balances,
            loan outstanding amounts, schedule statuses, share statuses, and membership fee statuses.
            Run this if you suspect any figures are out of sync.
        </p>
        <form method="POST" action="{{ route('settings.reconcile') }}"
              onsubmit="return confirm('Run reconciliation now? This will fix any inconsistencies across the database.')">
            @csrf
            <button type="submit" class="btn btn-warning">
                <i class="bi bi-arrow-repeat me-2"></i>Run Reconciliation
            </button>
        </form>
    </div>
</div>

{{-- Deployment Maintenance --}}
<div class="card mt-4 border-danger">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-hdd-stack text-danger"></i>
        <span>Deployment Maintenance</span>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Run these after deploying new code on a host with no terminal access. Run in order: Migrate, then Seed (if a release note says to), then Clear Cache.
        </p>
        <div class="d-flex flex-wrap gap-2 align-items-start">
            <form method="POST" action="{{ route('settings.migrate') }}"
                  onsubmit="return confirm('Run pending database migrations now? This changes the database schema.')">
                @csrf
                <button type="submit" class="btn btn-outline-danger">
                    <i class="bi bi-database-fill-up me-2"></i>Run Migrations
                </button>
            </form>

            <form method="POST" action="{{ route('settings.seed') }}" class="d-flex gap-2"
                  onsubmit="return confirm(this.seeder.value === 'RolesAndPermissionsSeeder' ? 'This will reset the super_admin/admin/cashier/staff roles\' permissions back to the code defaults, discarding any manual customization made via Users & Roles. Continue?' : 'Run this seeder now?')">
                @csrf
                <select name="seeder" class="form-select" required>
                    <option value="">— Select seeder —</option>
                    <option value="LoanPenaltyTierSeeder">Loan Penalty Tiers</option>
                    <option value="SavingsInterestTierSeeder">Savings Interest Tiers</option>
                    <option value="RolesAndPermissionsSeeder">Roles &amp; Permissions (resets roles to defaults)</option>
                    <option value="ChartOfAccountsSeeder">Chart of Accounts (adds new accounts only)</option>
                </select>
                <button type="submit" class="btn btn-outline-secondary text-nowrap">
                    <i class="bi bi-database-add me-2"></i>Run Seeder
                </button>
            </form>

            <form method="POST" action="{{ route('settings.clear-cache') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="bi bi-eraser-fill me-2"></i>Clear Cache
                </button>
            </form>

            <form method="POST" action="{{ route('settings.check-server-ip') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="bi bi-globe me-2"></i>Check Server IP
                </button>
            </form>
        </div>
        <p class="text-muted small mt-2 mb-0">
            "Check Server IP" shows the outbound IP this server uses to reach the internet -- needed to whitelist it with gateways like MarzPay (Dashboard &gt; IP Whitelist).
        </p>
    </div>
</div>

{{-- One-time statement migration -- remove this card once used --}}
<div class="card mt-4 border-danger">
    <div class="card-header bg-danger bg-opacity-10 text-danger fw-bold">
        <i class="bi bi-exclamation-octagon-fill me-2"></i>DANGER: One-Time Statement Migration
    </div>
    <div class="card-body">
        <p class="mb-2">
            This <strong>permanently wipes every client and all transactional data</strong> (every journal entry,
            savings/loan/FD/share/group history), then <strong>rebuilds the entire client roster and balances</strong>
            from the 31/07/2026 member statement. This cannot be undone once it runs.
        </p>
        <p class="mb-3 fw-semibold">
            Confirm you have a full database backup taken before running this. Type <code>MIGRATE JULY 2026</code> exactly to enable the button.
        </p>
        <form method="POST" action="{{ route('settings.run-july-statement-migration') }}" id="statementMigrationForm"
              onsubmit="return confirm('This is your last chance. Have you taken a full production backup? This action is irreversible.');">
            @csrf
            <div class="input-group" style="max-width: 420px;">
                <input type="text" name="confirmation_phrase" id="migrationPhrase" class="form-control"
                       placeholder="Type: MIGRATE JULY 2026" autocomplete="off">
                <button type="submit" class="btn btn-danger" id="migrationSubmitBtn" disabled>
                    <i class="bi bi-radioactive me-2"></i>Run Migration
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('migrationPhrase')?.addEventListener('input', function() {
    document.getElementById('migrationSubmitBtn').disabled = (this.value !== 'MIGRATE JULY 2026');
});
</script>

{{-- Follow-up fix for FD terms left generic by the migration above -- run after it --}}
<div class="card mt-4 border-warning">
    <div class="card-header bg-warning bg-opacity-10 text-warning-emphasis fw-bold">
        <i class="bi bi-tools me-2"></i>Fixed Deposit Term Fix
    </div>
    <div class="card-body">
        <p class="mb-2 small">
            The statement migration above placed every fixed deposit on 31/07/2026 using the active
            product's default term, since it only had each client's total balance. This corrects the
            real start date/term/maturity for the 17 clients with an active FD using the FixedSvChk
            ledger (BK00028 is split into the 4 separate deposits it actually holds). No GL changes --
            principal totals are unchanged. Run this once, after the migration above.
        </p>
        <form method="POST" action="{{ route('settings.fix-july-fd-terms') }}"
              onsubmit="return confirm('Run the FD term fix now?');">
            @csrf
            <button type="submit" class="btn btn-outline-warning">
                <i class="bi bi-wrench-adjustable me-2"></i>Fix FD Terms
            </button>
        </form>
    </div>
</div>

{{-- Follow-up fix for loan terms left generic by the migration above -- run after it --}}
<div class="card mt-4 border-warning">
    <div class="card-header bg-warning bg-opacity-10 text-warning-emphasis fw-bold">
        <i class="bi bi-tools me-2"></i>Loan Term Fix
    </div>
    <div class="card-body">
        <p class="mb-2 small">
            The statement migration above disbursed every loan on 31/07/2026 on a single generic loan
            product, since it only had each client's total balance. This corrects the real rate/term/
            category/disbursement date for the 54 clients with an active loan using LoanInfo.xlsx and
            the active-loans report, reclassifying the GL receivable account where the category changes
            which product it belongs to. Run this once, after the migration above.
        </p>
        <form method="POST" action="{{ route('settings.fix-july-loan-terms') }}"
              onsubmit="return confirm('Run the loan term fix now?');">
            @csrf
            <button type="submit" class="btn btn-outline-warning">
                <i class="bi bi-wrench-adjustable me-2"></i>Fix Loan Terms
            </button>
        </form>
    </div>
</div>
@endsection
