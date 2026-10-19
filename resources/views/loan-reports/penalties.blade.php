@extends('layouts.app')
@section('title', 'Loan Penalties')
@section('breadcrumb')
    <li class="breadcrumb-item active">Loan Penalties</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Loan Penalties</h4>
        <div class="text-muted small">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }} · flat penalty per missed installment (Loan Penalty Tiers)</div>
    </div>
    <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Export</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format' => 'pdf', 'from_date' => $from, 'to_date' => $to]) }}"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Export PDF</a></li>
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format' => 'excel', 'from_date' => $from, 'to_date' => $to]) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Export Excel (CSV)</a></li>
        </ul>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
        <div class="text-muted small text-uppercase">Penalty outstanding</div>
        <div class="fs-4 fw-bold text-danger">{{ number_format($totals['outstanding'], $dp) }}</div>
        <div class="small text-muted">at {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
        <div class="text-muted small text-uppercase">Incurred in period</div>
        <div class="fs-4 fw-bold text-warning">{{ number_format($totals['incurred'], $dp) }}</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
        <div class="text-muted small text-uppercase">Paid in period</div>
        <div class="fs-4 fw-bold text-success">{{ number_format($totals['paid'], $dp) }}</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
        <div class="text-muted small text-uppercase">Loans / missed installments</div>
        <div class="fs-4 fw-bold">{{ $rows->count() }} <span class="fs-6 text-muted">/ {{ $totals['missed'] }}</span></div>
    </div></div></div>
</div>

<div class="card">
    <div class="card-body pb-0">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from_date" class="form-control" value="{{ $from }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to_date" class="form-control" value="{{ $to }}" max="{{ today()->toDateString() }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Loan # or client name..." value="{{ request('search') }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Segment</label>
                <select name="segment_id" class="form-select">
                    <option value="">All</option>
                    @foreach($segments as $s)<option value="{{ $s->id }}" @selected(request('segment_id') == $s->id)>{{ $s->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Relationship manager</label>
                <select name="rm_id" class="form-select">
                    <option value="">All</option>
                    @foreach($relationshipManagers as $rm)<option value="{{ $rm->id }}" @selected(request('rm_id') == $rm->id)>{{ $rm->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-auto d-flex align-items-end gap-2">
                <button class="btn btn-outline-primary">Filter</button>
                <a href="{{ route('loan-reports.penalties') }}" class="btn btn-outline-secondary">Clear</a>
            </div>
            <div class="col-12 d-flex flex-wrap gap-1">
                @foreach(['This month' => [now()->startOfMonth(), today()], 'Last month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()], 'Last 3 months' => [now()->subMonthsNoOverflow(3)->startOfMonth(), today()], 'This year' => [now()->startOfYear(), today()]] as $label => [$f, $t])
                    <button type="button" class="btn btn-light btn-sm py-0" onclick="this.form.from_date.value='{{ $f->toDateString() }}';this.form.to_date.value='{{ $t->toDateString() }}';this.form.submit()">{{ $label }}</button>
                @endforeach
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th class="ps-3">Loan #</th><th>Client</th><th>RM</th>
                <th class="text-end">Missed</th><th>Oldest missed</th>
                <th class="text-end">Incurred (period)</th><th class="text-end">Paid (period)</th><th class="text-end pe-3">Outstanding</th>
            </tr></thead>
            <tbody class="small">
            @forelse($rows as $r)
                <tr>
                    <td class="ps-3 font-monospace"><a href="{{ route('loans.show', $r->loan) }}" class="text-decoration-none">{{ $r->loan->loan_number }}</a></td>
                    <td>{{ $r->loan->client->name ?? '' }} <span class="text-muted">{{ $r->loan->client->client_number ?? '' }}</span><div class="text-muted" style="font-size:.72rem">{{ $r->loan->product->name ?? '' }}{{ $r->loan->client->segment ? ' · ' . $r->loan->client->segment->name : '' }}</div></td>
                    <td>{{ $r->loan->client->relationshipManager->name ?? '—' }}</td>
                    <td class="text-end">{{ $r->missed }}@if($r->missed_in_range)<div class="text-muted" style="font-size:.72rem">{{ $r->missed_in_range }} in period</div>@endif</td>
                    <td class="text-nowrap">{{ $r->oldest_due ? \Carbon\Carbon::parse($r->oldest_due)->format('d M Y') : '—' }}</td>
                    <td class="text-end">{{ $r->incurred > 0 ? number_format($r->incurred, $dp) : '—' }}</td>
                    <td class="text-end text-success">{{ $r->paid > 0 ? number_format($r->paid, $dp) : '—' }}</td>
                    <td class="text-end pe-3 fw-semibold text-danger">{{ $r->outstanding > 0 ? number_format($r->outstanding, $dp) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No penalties for this period.</td></tr>
            @endforelse
            </tbody>
            @if($rows->isNotEmpty())
            <tfoot class="table-light fw-bold small"><tr>
                <td colspan="3" class="ps-3">Total</td><td class="text-end">{{ $totals['missed'] }}</td><td></td>
                <td class="text-end">{{ number_format($totals['incurred'], $dp) }}</td>
                <td class="text-end text-success">{{ number_format($totals['paid'], $dp) }}</td>
                <td class="text-end pe-3 text-danger">{{ number_format($totals['outstanding'], $dp) }}</td>
            </tr></tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
