@extends('layouts.app')
@php
    $specific = $type === 'specific';
    $pct = fn ($r) => rtrim(rtrim(number_format($r, 4), '0'), '.') . '%';
    $signed = fn ($v) => $v < 0 ? '(' . number_format(abs($v), $dp) . ')' : number_format($v, $dp);
    $glProv = $specific ? '1109' : '1110';
    $glExp  = $specific ? '5119' : '5120';
@endphp
@section('title', $specific ? 'Specific Provisions' : 'General Provisions')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
    <li class="breadcrumb-item active">{{ $specific ? 'Specific Provisions' : 'General Provisions' }}</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Loan Provisions</h4>
    <a href="{{ request()->fullUrlWithQuery(['format' => 'excel', 'type' => $type, 'as_at_date' => $asAt, 'rate' => $specific ? null : $rate]) }}" class="btn btn-outline-primary">
        <i class="bi bi-file-earmark-excel me-1"></i> Export Breakdown
    </a>
</div>

@include('loans._tabs', ['activeTab' => $specific ? 'specific-provisions' : 'general-provisions'])

{{-- Run parameters --}}
<div class="card mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Provision as at</label>
                <input type="date" name="as_at_date" class="form-control" value="{{ $asAt }}" max="{{ today()->toDateString() }}">
            </div>
            @unless($specific)
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Rate (%)</label>
                <input type="number" name="rate" class="form-control" value="{{ rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.') }}" step="0.01" min="0.01" max="100">
            </div>
            @endunless
            <div class="col-auto">
                <button class="btn btn-outline-primary"><i class="bi bi-calculator me-1"></i> Calculate</button>
            </div>
            <div class="col-auto">
                <a href="{{ route('loan-provisions.index', ['type' => $type]) }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
        <div class="small text-muted mt-2">
            <i class="bi bi-info-circle me-1"></i>
            @if($specific)
                Specific provision on loans in arrears as at the date selected (days since the oldest unpaid installment):
                <strong>more than 90 days → 50%</strong>, <strong>365 days and above → 100%</strong> of outstanding principal.
                <strong>Locked-Up Loans are excluded</strong>, and the old-system opening balance in GL 1109 is left untouched —
                runs post only the movement against the provision built up by previous specific runs (DR 5119 / CR 1109).
            @else
                General provision = rate × outstanding principal as at the date selected, for all disbursed loans
                <strong>excluding Locked-Up Loans</strong>. Posting books only the movement needed to bring
                GL 1110 (Loan Provisions — General) to the required balance, against GL 5120 (General Loan Provision Expense).
            @endif
        </div>
    </div>
</div>

{{-- Summary --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">{{ $specific ? 'Principal in Arrears > 90 Days' : 'Outstanding Principal' }}</div>
            <div class="fs-4 fw-bold">{{ number_format($preview['total_outstanding'], $dp) }}</div>
            <div class="small text-muted">{{ number_format($preview['lines']->count()) }} loans as at {{ \Carbon\Carbon::parse($asAt)->format('d M Y') }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">Required Provision{{ $specific ? '' : ' (' . $pct($rate) . ')' }}</div>
            <div class="fs-4 fw-bold text-primary">{{ number_format($preview['required'], $dp) }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">{{ $specific ? 'Held from Previous Runs' : 'Existing Provision (GL 1110)' }}</div>
            <div class="fs-4 fw-bold">{{ number_format($preview['existing'], $dp) }}</div>
            @if($specific)
                <div class="small text-muted">GL 1109 total: {{ number_format($preview['gl_balance'], $dp) }} (incl. old-system balance)</div>
            @endif
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">Adjustment to Post</div>
            <div class="fs-4 fw-bold {{ $preview['adjustment'] > 0 ? 'text-danger' : ($preview['adjustment'] < 0 ? 'text-success' : '') }}">
                {{ $signed($preview['adjustment']) }}
            </div>
            <div class="small text-muted">
                @if($preview['adjustment'] > 0) Charge: DR {{ $glExp }} / CR {{ $glProv }}
                @elseif($preview['adjustment'] < 0) Write-back: DR {{ $glProv }} / CR {{ $glExp }}
                @else No movement needed
                @endif
            </div>
        </div></div>
    </div>
</div>

@if($specific)
<div class="card mb-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Days in Arrears</th><th class="text-end">Rate</th><th class="text-end">Loans</th>
                <th class="text-end">Outstanding Principal</th><th class="text-end pe-3">Provision</th>
            </tr></thead>
            <tbody>
            @foreach($preview['bands'] as $band)
                <tr>
                    <td class="ps-3">{{ $band['label'] }}</td>
                    <td class="text-end">{{ $pct($band['rate']) }}</td>
                    <td class="text-end">{{ number_format($band['count']) }}</td>
                    <td class="text-end">{{ number_format($band['outstanding'], $dp) }}</td>
                    <td class="text-end pe-3 fw-semibold">{{ number_format($band['provision'], $dp) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Post --}}
@can('run loan provisions')
<div class="card mb-4">
    <div class="card-body">
        @if($alreadyPosted)
            <div class="alert alert-info mb-0">
                A {{ $type }} provision has already been posted as at {{ \Carbon\Carbon::parse($asAt)->format('d M Y') }}.
                <a href="{{ route('loan-provisions.show', $alreadyPosted) }}">View run</a>.
                Reverse its journal entry to re-run it.
            </div>
        @else
            <form method="POST" action="{{ route('loan-provisions.store') }}" class="row g-2 align-items-end"
                  onsubmit="return confirm('Post {{ $type }} provision as at {{ $asAt }}?');">
                @csrf
                <input type="hidden" name="provision_type" value="{{ $type }}">
                <input type="hidden" name="as_at_date" value="{{ $asAt }}">
                @unless($specific)<input type="hidden" name="rate" value="{{ $rate }}">@endunless
                <div class="col-md-7">
                    <label class="form-label small text-muted mb-1">Notes (optional)</label>
                    <input type="text" name="notes" class="form-control" maxlength="500" placeholder="e.g. September 2026 month-end provision">
                </div>
                <div class="col-auto">
                    <button class="btn btn-success"><i class="bi bi-check2-circle me-1"></i> Run Provision</button>
                </div>
            </form>
        @endif
    </div>
</div>
@endcan

{{-- Breakdown --}}
<div class="card mb-4">
    <div class="card-header bg-white fw-semibold">
        {{ $specific ? 'Breakdown — loans in arrears more than 90 days' : 'Breakdown — loans contributing to outstanding principal' }}
    </div>
    <div class="table-responsive" style="max-height:520px">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="sticky-top bg-white"><tr>
                <th class="ps-3">#</th><th>Loan #</th><th>Client</th><th>Product</th><th>Disbursed</th>
                @if($specific)
                    <th>Oldest Arrears</th><th class="text-end">Days</th>
                @else
                    <th>Status</th>
                @endif
                <th class="text-end">Outstanding Principal</th>
                <th class="text-end">Rate</th>
                <th class="text-end pe-3">Provision</th>
            </tr></thead>
            <tbody>
            @forelse($preview['lines'] as $i => $line)
                @php $loan = $line['loan']; @endphp
                <tr>
                    <td class="ps-3 text-muted small">{{ $i + 1 }}</td>
                    <td class="font-monospace"><a href="{{ route('loans.show', $loan) }}" class="text-decoration-none">{{ $loan->loan_number }}</a></td>
                    <td>{{ optional($loan->client)->name ?? '—' }}</td>
                    <td class="small text-muted">{{ optional($loan->product)->name }}</td>
                    <td class="small text-muted">{{ optional($loan->disbursement_date)->format('d M Y') }}</td>
                    @if($specific)
                        <td class="small text-muted">{{ \Carbon\Carbon::parse($line['arrears_date'])->format('d M Y') }}</td>
                        <td class="text-end fw-semibold {{ $line['days_in_arrears'] >= 365 ? 'text-danger' : 'text-warning' }}">{{ number_format($line['days_in_arrears']) }}</td>
                    @else
                        <td><span class="badge badge-status-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span></td>
                    @endif
                    <td class="text-end">{{ number_format($line['outstanding'], $dp) }}</td>
                    <td class="text-end small text-muted">{{ $pct($line['rate']) }}</td>
                    <td class="text-end pe-3 fw-semibold">{{ number_format($line['provision_amount'], $dp) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $specific ? 10 : 9 }}" class="text-center text-muted py-4">
                    {{ $specific ? 'No loans more than 90 days in arrears as at this date.' : 'No outstanding loans as at this date.' }}
                </td></tr>
            @endforelse
            </tbody>
            @if($preview['lines']->isNotEmpty())
            <tfoot class="fw-bold"><tr>
                <td colspan="{{ $specific ? 7 : 6 }}" class="ps-3 text-end">TOTAL</td>
                <td class="text-end">{{ number_format($preview['total_outstanding'], $dp) }}</td>
                <td></td>
                <td class="text-end pe-3">{{ number_format($preview['required'], $dp) }}</td>
            </tr></tfoot>
            @endif
        </table>
    </div>
</div>

{{-- History --}}
<div class="card">
    <div class="card-header bg-white fw-semibold">Previous {{ ucfirst($type) }} Provision Runs</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">As At</th><th class="text-end">Loans</th><th class="text-end">{{ $specific ? 'In Arrears' : 'Outstanding' }}</th>
                @unless($specific)<th class="text-end">Rate</th>@endunless
                <th class="text-end">Required</th><th class="text-end">Previous</th>
                <th class="text-end">Adjustment</th><th>Journal</th><th>Status</th><th>By</th><th class="pe-3"></th>
            </tr></thead>
            <tbody>
            @forelse($runs as $run)
                <tr>
                    <td class="ps-3">{{ $run->as_at_date->format('d M Y') }}</td>
                    <td class="text-end">{{ number_format($run->loan_count) }}</td>
                    <td class="text-end">{{ number_format($run->total_outstanding, $dp) }}</td>
                    @unless($specific)<td class="text-end">{{ $pct($run->rate) }}</td>@endunless
                    <td class="text-end fw-semibold">{{ number_format($run->required_provision, $dp) }}</td>
                    <td class="text-end text-muted">{{ number_format($run->previous_provision, $dp) }}</td>
                    <td class="text-end">{{ $signed($run->adjustment) }}</td>
                    <td class="small">
                        @if($run->transaction)
                            <a href="{{ route('transactions.show', $run->transaction) }}" class="text-decoration-none font-monospace">{{ $run->transaction->reference }}</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $run->status === 'posted' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($run->status) }}</span>
                    </td>
                    <td class="small text-muted">{{ optional($run->createdBy)->name }}</td>
                    <td class="pe-3"><a href="{{ route('loan-provisions.show', $run) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="{{ $specific ? 10 : 11 }}" class="text-center text-muted py-4">No provision runs yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($runs->hasPages())
    <div class="card-footer">{{ $runs->links() }}</div>
    @endif
</div>
@endsection
