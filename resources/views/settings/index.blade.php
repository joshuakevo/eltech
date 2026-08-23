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
            The statement migration above disbursed every loan on 31/07/2026 with a generic term, since
            it only had each client's total balance. This corrects the real rate/term/disbursement date
            for the 54 clients with an active loan using LoanInfo.xlsx and the active-loans report.
            Loan product stays whatever the migration assigned -- no GL changes. Run this once, after
            the migration above.
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

{{-- Forward-looking repayment schedule for the loans fixed above -- run after it --}}
<div class="card mt-4 border-warning">
    <div class="card-header bg-warning bg-opacity-10 text-warning-emphasis fw-bold">
        <i class="bi bi-tools me-2"></i>Generate Loan Schedules
    </div>
    <div class="card-body">
        <p class="mb-2 small">
            Builds a repayment schedule from today to each loan's maturity date, spreading its existing
            outstanding balance evenly across the remaining months -- not a reconstruction of the
            original schedule (we don't have day-by-day repayment history), just a forward plan. Loans
            already past maturity get a single overdue installment for the full balance. Run this once,
            after the loan term fix above.
        </p>
        <form method="POST" action="{{ route('settings.generate-july-loan-schedules') }}"
              onsubmit="return confirm('Generate loan schedules now?');">
            @csrf
            <button type="submit" class="btn btn-outline-warning">
                <i class="bi bi-wrench-adjustable me-2"></i>Generate Schedules
            </button>
        </form>
    </div>
</div>

{{-- Locked-up loans from the old system's separate Lock Up Report -- not covered by the statement migration at all --}}
<div class="card mt-4 border-warning">
    <div class="card-header bg-warning bg-opacity-10 text-warning-emphasis fw-bold">
        <i class="bi bi-tools me-2"></i>Import Locked-Up Loans
    </div>
    <div class="card-body">
        <p class="mb-2 small">
            The old system's Lock Up Report (76 frozen, non-performing loans) was never part of the
            statement migration -- it uses a completely different code scheme and none of it shows up in
            any client's Loan Value. This creates 14 new clients (real locked-up debt, excluded from the
            main import for other reasons) and 76 loans total, booked to a dedicated Locked-Up Loans
            receivable account. Re-run the Chart of Accounts seeder first if you haven't since this was
            added.
        </p>
        <form method="POST" action="{{ route('settings.import-july-locked-up-loans') }}"
              onsubmit="return confirm('Import the 76 locked-up loans now?');">
            @csrf
            <button type="submit" class="btn btn-outline-warning">
                <i class="bi bi-wrench-adjustable me-2"></i>Import Locked-Up Loans
            </button>
        </form>
    </div>
</div>

{{-- Institutional balance sheet accounts from the old system's Trial Balance, never imported --}}
<div class="card mt-4 border-warning">
    <div class="card-header bg-warning bg-opacity-10 text-warning-emphasis fw-bold">
        <i class="bi bi-tools me-2"></i>True Up Balance Sheet
    </div>
    <div class="card-body">
        <p class="mb-2 small">
            The statement migration only ever posted member-facing balances (savings, loans, FDs, shares,
            groups) against the Opening Balance Equity suspense account -- it never had cash/bank
            balances, investments, fixed assets, loan provisions, or other institutional liabilities, so
            that suspense account currently sits at a net debit of over 1 billion. This posts everything
            from the old system's 31/07/2026 Trial Balance that's still missing, plus the combined
            Retained Earnings position. Independent of the Locked-Up Loans import above (no ordering
            dependency), but also needs the Chart of Accounts seeder re-run first.
        </p>
        <form method="POST" action="{{ route('settings.true-up-july-balance-sheet') }}"
              onsubmit="return confirm('Post the balance sheet true-up now?');">
            @csrf
            <button type="submit" class="btn btn-outline-warning">
                <i class="bi bi-wrench-adjustable me-2"></i>True Up Balance Sheet
            </button>
        </form>
    </div>
</div>

{{-- Unbundles the lumped Jan-Jul YTD deficit inside Retained Earnings into the old system's individual income/expense accounts --}}
<div class="card mt-4 border-warning">
    <div class="card-header bg-warning bg-opacity-10 text-warning-emphasis fw-bold">
        <i class="bi bi-tools me-2"></i>Import Jan&ndash;Jul 2026 P&amp;L Detail
    </div>
    <div class="card-body">
        <p class="mb-2 small">
            The balance sheet true-up above folded the whole Jan-Jul 2026 YTD deficit (315,792,628) into
            a single Retained Earnings opening entry, since there was no day-by-day transaction data to
            rebuild the period's P&amp;L from. This replaces that lump figure with the old system's actual
            41 income and expense account balances for the period, taken straight from its Trial Balance.
            Retained Earnings drops to the old system's own prior-period figure (832,747,593) once this
            runs. Run this <strong>after</strong> the Chart of Accounts seeder (adds the new accounts) and
            the True Up Balance Sheet above (which posted the lump figure this unbundles).
        </p>
        <form method="POST" action="{{ route('settings.import-july-pl-detail') }}"
              onsubmit="return confirm('Import the Jan-Jul 2026 P&amp;L detail now?');">
            @csrf
            <button type="submit" class="btn btn-outline-warning">
                <i class="bi bi-wrench-adjustable me-2"></i>Import Jan&ndash;Jul 2026 P&amp;L Detail
            </button>
        </form>
    </div>
</div>

{{-- Client segments & relationship managers, from the legacy system's 2026-08 client export --}}
<div class="card mt-4 border-warning">
    <div class="card-header bg-warning bg-opacity-10 text-warning-emphasis fw-bold">
        <i class="bi bi-tools me-2"></i>Import Client Segments &amp; Relationship Managers
    </div>
    <div class="card-body">
        <p class="mb-2 small">
            Matches the legacy system's client export (database/data/2026-08-client-segments-rm-import.csv,
            511 clients) to existing clients by client number, falling back to name. Creates the real
            segments (Konnect Sacco, Konnect Businness, KDF, Venture Capital, Option 1) and a staff User
            for each relationship manager name, then sets each matched client's segment and relationship
            manager. Rows the old system flagged <strong>CLOSE</strong> under Relationship Manager are not
            closed automatically &mdash; they're listed in the output for you to review and close via
            Administration &gt; Close Accounts. Safe to re-run.
        </p>
        <form method="POST" action="{{ route('settings.import-client-segments-rm') }}"
              onsubmit="return confirm('Import client segments and relationship managers now?');">
            @csrf
            <button type="submit" class="btn btn-outline-warning">
                <i class="bi bi-wrench-adjustable me-2"></i>Import Segments &amp; RM
            </button>
        </form>
    </div>
</div>

{{-- Follow-up to the import above: assigns a default segment/RM to whatever it left unassigned --}}
<div class="card mt-4 border-warning">
    <div class="card-header bg-warning bg-opacity-10 text-warning-emphasis fw-bold">
        <i class="bi bi-tools me-2"></i>Assign Default Segment &amp; RM to Unassigned Clients
    </div>
    <div class="card-body">
        <p class="mb-2 small">
            Run this <strong>after</strong> the Import Segments &amp; RM step above. Any client still
            without a segment (blank in the legacy export, or not present in it at all) is assigned to
            <strong>Konnect Sacco</strong>; any client still without a relationship manager is assigned to
            <strong>Shaddai Kamoga</strong>. Only touches clients with no segment/RM already set &mdash;
            safe to re-run.
        </p>
        <form method="POST" action="{{ route('settings.assign-default-segment-rm') }}"
              onsubmit="return confirm('Assign Konnect Sacco / Shaddai Kamoga to all unassigned clients now?');">
            @csrf
            <button type="submit" class="btn btn-outline-warning">
                <i class="bi bi-wrench-adjustable me-2"></i>Assign Defaults
            </button>
        </form>
    </div>
</div>

{{-- Read-only diagnostic: why does the Income Statement segment filter return zero? --}}
<div class="card mt-4 border-info">
    <div class="card-header bg-info bg-opacity-10 text-info-emphasis fw-bold">
        <i class="bi bi-search me-2"></i>Diagnose Income Statement Segment Filter
    </div>
    <div class="card-body">
        <p class="mb-2 small">
            Read-only &mdash; writes nothing. Scans every revenue/expense transaction line (all time) and
            reports why each one would or wouldn't show under a segment filter: no resolvable client at
            all, a resolved client with no segment assigned, or a resolved client with a segment
            (broken down by segment).
        </p>
        <form method="POST" action="{{ route('settings.diagnose-segment-income-statement') }}">
            @csrf
            <button type="submit" class="btn btn-outline-info">
                <i class="bi bi-search me-2"></i>Run Diagnostic
            </button>
        </form>
    </div>
</div>
@endsection
