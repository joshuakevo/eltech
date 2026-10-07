@extends('layouts.app')
@section('title', 'Run Loans')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
    <li class="breadcrumb-item active">Run Loans</li>
@endsection
@php
    $ready = $previews->filter(fn ($p) => !$p['errors'] && count($p['steps']));
@endphp
@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">Run Loans</h4>
        <div class="text-muted small">Charge interest for the days since the last charge, then recover the installment from savings.</div>
    </div>
    @can('correct loans')
    <a href="{{ route('loans.reset') }}" class="btn btn-outline-danger btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset repayments…</a>
    @endcan
</div>

@if($runsDone->isNotEmpty())
<div class="card mb-3 border-warning">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-clock-history me-1"></i>Runs done for {{ $date->format('d M Y') }}</span>
        @can('repay loans')
        <form method="POST" action="{{ route('loans.run.undo') }}" onsubmit="return confirm('Undo ALL runs recorded for {{ $date->format('d M Y') }}? Interest charges, recoveries, savings withdrawals and journals they created will be deleted and the loans restored.');">
            @csrf <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-counterclockwise me-1"></i>Undo all runs for this date</button>
        </form>
        @endcan
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 small align-middle">
            <thead><tr><th class="ps-3">Loan</th><th>Client</th><th>Installment due</th><th class="text-end">Interest charged</th><th class="text-end">Recovered</th><th>By</th><th class="pe-3"></th></tr></thead>
            <tbody>
            @foreach($runsDone as $rd)
                <tr>
                    <td class="ps-3 font-monospace"><a href="{{ route('loans.show', $rd->loan_id) }}">{{ $rd->loan->loan_number ?? '#' . $rd->loan_id }}</a></td>
                    <td>{{ $rd->loan->client->name ?? '—' }}</td>
                    <td>{{ $rd->due_date->format('d M Y') }}</td>
                    <td class="text-end">{{ number_format($rd->accrued, $dp) }}</td>
                    <td class="text-end">{{ number_format($rd->recovered, $dp) }}</td>
                    <td>{{ $rd->createdBy->name ?? '—' }} <span class="text-muted">{{ $rd->created_at->format('H:i') }}</span></td>
                    <td class="pe-3 text-end">
                        @can('repay loans')
                        <form method="POST" action="{{ route('loans.run.undo') }}" class="d-inline" onsubmit="return confirm('Undo this run for {{ $rd->loan->loan_number ?? '' }}? (Later runs on the same loan must be undone first.)');">
                            @csrf <input type="hidden" name="date" value="{{ $date->toDateString() }}"><input type="hidden" name="run_id" value="{{ $rd->id }}">
                            <button class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-arrow-counterclockwise"></i> Undo</button>
                        </form>
                        @endcan
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if(session('run_results'))
<div class="card mb-3 border-success">
    <div class="card-header fw-semibold"><i class="bi bi-check2-circle text-success me-1"></i>Run results</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 small">
            <thead><tr><th class="ps-3">Loan</th><th>Client</th><th class="text-end">Interest charged</th><th class="text-end">Expected</th><th class="text-end">Recovered</th><th class="pe-3">Notes</th></tr></thead>
            <tbody>
            @foreach(session('run_results') as $r)
                <tr class="{{ $r['errors'] ? 'table-danger' : '' }}">
                    <td class="ps-3 font-monospace">{{ $r['loan'] }}</td><td>{{ $r['client'] }}</td>
                    <td class="text-end">{{ number_format($r['charged'], $dp) }}</td>
                    <td class="text-end">{{ number_format($r['expected'], $dp) }}</td>
                    <td class="text-end fw-semibold {{ $r['recovered'] + 0.01 < $r['expected'] ? 'text-warning' : 'text-success' }}">{{ number_format($r['recovered'], $dp) }}</td>
                    <td class="pe-3">{{ implode(' ', $r['errors']) ?: ($r['recovered'] + 0.01 < $r['expected'] ? 'Savings short — balance carries to next run' : 'OK') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-6 col-lg"><div class="card h-100"><div class="card-body py-2">
        <div class="text-muted small text-uppercase">Loans due — {{ $date->format('jS M') }}</div>
        <div class="fs-4 fw-bold">{{ number_format($totalCount) }}</div>
    </div></div></div>
    <div class="col-6 col-lg"><div class="card h-100"><div class="card-body py-2">
        <div class="text-muted small text-uppercase">Principal balance</div>
        <div class="fs-5 fw-bold text-warning">{{ number_format($totals['principal'], $dp) }}</div>
    </div></div></div>
    <div class="col-6 col-lg"><div class="card h-100"><div class="card-body py-2">
        <div class="text-muted small text-uppercase">Interest to charge</div>
        <div class="fs-5 fw-bold text-primary">{{ number_format($totals['charge'], $dp) }}</div>
    </div></div></div>
    <div class="col-6 col-lg"><div class="card h-100"><div class="card-body py-2">
        <div class="text-muted small text-uppercase">Due to recover</div>
        <div class="fs-5 fw-bold">{{ number_format($totals['expected'], $dp) }}</div>
    </div></div></div>
    <div class="col-12 col-lg"><div class="card h-100 border-success"><div class="card-body py-2">
        <div class="text-muted small text-uppercase">Will recover from savings</div>
        <div class="fs-5 fw-bold text-success">{{ number_format($totals['recover'], $dp) }}</div>
    </div></div></div>
</div>

<div class="card">
    <div class="card-body pb-2">
        <form class="row g-2 align-items-center" method="GET">
            <div class="col-auto">
                <a href="{{ route('loans.run', ['date' => $date->copy()->subDay()->toDateString(), 'search' => request('search')]) }}" class="btn btn-outline-secondary" title="Previous day"><i class="bi bi-chevron-left"></i></a>
            </div>
            <div class="col-auto"><input type="date" name="date" class="form-control" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}"></div>
            <div class="col-auto">
                <a href="{{ route('loans.run', ['date' => $date->copy()->addDay()->toDateString(), 'search' => request('search')]) }}" class="btn btn-outline-secondary {{ $date->gte(today()) ? 'disabled' : '' }}" title="Next day"><i class="bi bi-chevron-right"></i></a>
            </div>
            <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="Loan # or client name..." value="{{ request('search') }}"></div>
            <div class="col-auto"><button class="btn btn-outline-primary">View</button></div>
            <div class="col-auto"><a href="{{ route('loans.run') }}" class="btn btn-outline-secondary">Today</a></div>
        </form>
        <p class="small text-muted mt-2 mb-0">
            Interest = outstanding principal × annual rate × days since the last charge ÷ 365, added to outstanding interest.
            The installment (interest first, then principal) is recovered from the member's savings, dated the due date, as far as the balance on that date allows.
            Missed earlier due dates are caught up in order. Nothing is saved until you click <b>Run</b>.
        </p>
    </div>

    <form method="POST" action="{{ route('loans.run.process') }}" id="runForm" onsubmit="if (this.dataset.sent) return false; if (!confirm('Run the selected loans for {{ $date->format('d M Y') }}? Interest will be charged and installments recovered from savings.')) return false; this.dataset.sent = 1; const b = document.getElementById('runBtn'); if (b) { b.disabled = true; b.innerHTML = '<span class=&quot;spinner-border spinner-border-sm me-1&quot;></span>Running…'; } return true;">
        @csrf
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
        <table class="table table-sm table-hover align-middle mb-0 run-table">
            <colgroup><col style="width:2.2rem"><col style="width:21%"><col><col style="width:16%"><col><col><col><col style="width:14%"></colgroup>
            <thead class="table-light"><tr>
                <th class="ps-3"><input type="checkbox" class="form-check-input" id="runAll" {{ $ready->count() ? 'checked' : '' }}></th>
                <th>Client / Loan</th>
                <th class="text-end">Balance now</th>
                <th>Interest to charge</th>
                <th class="text-end">Installment due</th>
                <th class="text-end">Savings available</th>
                <th class="text-end">Will recover</th>
                <th class="pe-3">After run</th>
            </tr></thead>
            <tbody>
            @forelse($loans as $loan)
                @php
                    $p = $previews[$loan->id];
                    $steps = collect($p['steps']);
                    $charge = $steps->sum('accrued');
                    $expected = $steps->sum(fn ($s) => $s['recovery']['expected'] ?? 0);
                    $recover = $steps->sum(fn ($s) => $s['recovery']['recovered'] ?? 0);
                    $available = $steps->max(fn ($s) => $s['recovery']['available'] ?? 0);
                    $canRun = !$p['errors'] && $steps->count();
                @endphp
                <tr class="{{ $p['errors'] ? 'table-danger' : '' }}">
                    <td class="ps-3">
                        <input type="checkbox" class="form-check-input run-pick" name="loan_ids[]" value="{{ $loan->id }}" {{ $canRun ? 'checked' : 'disabled' }}>
                    </td>
                    <td>
                        <div class="fw-semibold text-truncate" title="{{ $loan->client->name ?? '' }}">{{ $loan->client->name ?? 'Deleted client' }}</div>
                        <a href="{{ route('loans.show', $loan) }}" class="font-monospace small text-decoration-none">{{ $loan->loan_number }}</a>
                        <span class="text-muted small">· {{ $loan->interest_rate }}%</span>
                        @if($p['conversion'])
                            <span class="badge bg-info text-dark" title="First run on day-based interest: interest counted from {{ \Carbon\Carbon::parse($p['conversion']['baseline'])->format('d M Y') }}; outstanding interest {{ number_format($p['conversion']['old_interest'], 0) }} → {{ number_format($p['conversion']['new_interest'], 0) }} (future scheduled interest is no longer counted until charged)">first run</span>
                        @endif
                    </td>
                    <td class="text-end small">
                        <div>P {{ number_format($p['before']['principal'], $dp) }}</div>
                        <div class="text-muted">I {{ number_format($p['conversion']['new_interest'] ?? $p['before']['interest'], $dp) }}</div>
                    </td>
                    <td class="small">
                        @forelse($steps->filter(fn ($s) => $s['installment_no']) as $s)
                            <div><b>{{ number_format($s['accrued'], $dp) }}</b>
                                <span class="text-muted">{{ $s['days'] }}d · {{ \Carbon\Carbon::parse($s['from'])->format('d M') }}→{{ \Carbon\Carbon::parse($s['due_date'])->format('d M') }}</span></div>
                            @if(($s['carried_after'] ?? 0) > 0.5)<div class="text-warning" style="font-size:.72rem">{{ number_format($s['carried_after'], 0) }} interest carried forward</div>@endif
                        @empty
                            <span class="text-muted">{{ $p['errors'] ? '—' : 'Already charged' }}</span>
                        @endforelse
                    </td>
                    <td class="text-end">{{ $expected ? number_format($expected, $dp) : '—' }}</td>
                    <td class="text-end {{ $available + 0.01 < $expected ? 'text-danger' : '' }}">{{ number_format($available, $dp) }}</td>
                    <td class="text-end fw-semibold {{ $recover + 0.01 < $expected ? 'text-warning' : 'text-success' }}">{{ number_format($recover, $dp) }}</td>
                    <td class="pe-3 small">
                        @if($p['errors'])
                            <span class="text-danger">{{ implode(' ', $p['errors']) }}</span>
                        @elseif(!$steps->count())
                            <span class="text-muted">Nothing due</span>
                        @else
                            <div>P {{ number_format($p['after']['principal'], $dp) }}</div>
                            <div class="text-muted">I {{ number_format($p['after']['interest'], $dp) }}</div>
                            @if($recover + 0.01 < $expected)<div class="text-warning" style="font-size:.72rem">{{ number_format($expected - $recover, 0) }} short</div>@endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No active loans due on {{ $date->format('d M Y') }}.</td></tr>
            @endforelse
            </tbody>
        </table>
        @can('repay loans')
        @if($loans->count())
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="small text-muted" id="runCount"></span>
            <button class="btn btn-primary" id="runBtn" {{ $ready->count() ? '' : 'disabled' }}><i class="bi bi-play-circle me-1"></i>Run selected loans</button>
        </div>
        @endif
        @endcan
    </form>
</div>
@endsection

@push('styles')
<style>
    .run-table { table-layout: fixed; width: 100%; font-size: .86rem; }
    .run-table td { overflow: hidden; }
    .run-table thead th { font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; color: #6b7280; vertical-align: bottom; }
    .run-table .text-end { font-variant-numeric: tabular-nums; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const picks = Array.from(document.querySelectorAll('.run-pick:not(:disabled)'));
    const all = document.getElementById('runAll'), btn = document.getElementById('runBtn'), count = document.getElementById('runCount');
    function update() {
        const n = picks.filter(p => p.checked).length;
        if (count) count.textContent = n + ' of ' + picks.length + ' loan' + (picks.length === 1 ? '' : 's') + ' selected';
        if (btn) btn.disabled = n === 0;
        if (all) all.checked = n > 0 && n === picks.length;
    }
    if (all) all.addEventListener('change', () => { picks.forEach(p => p.checked = all.checked); update(); });
    picks.forEach(p => p.addEventListener('change', update));
    update();
})();
</script>
@endpush
