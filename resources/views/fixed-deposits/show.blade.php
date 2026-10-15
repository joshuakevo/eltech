@extends('layouts.app')
@section('title', $fixedDeposit->deposit_number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('fixed-deposits.index') }}">Fixed Deposits</a></li>
    <li class="breadcrumb-item active">{{ $fixedDeposit->deposit_number }}</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">{{ $fixedDeposit->deposit_number }}</h4>
        <span class="text-muted">{{ $fixedDeposit->client->name }} &bull; {{ $fixedDeposit->product->name }}</span>
    </div>
    <div class="d-flex gap-2">
        @php $isPlacement = $fixedDeposit->product && ($fixedDeposit->product->isMonthlyRate() || $fixedDeposit->product->isAnniversary()); @endphp
        <a href="{{ route('fixed-deposits.statement', $fixedDeposit) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-calendar3 me-1"></i>Monthly Statement (PDF)
        </a>
        <a href="{{ route('fixed-deposits.certificate', $fixedDeposit) }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-file-earmark-text me-1"></i>{{ $isPlacement ? 'Placement Certificate' : 'FD Certificate' }} (PDF)
        </a>
        @can('mature fixed-deposits')
        @if($fixedDeposit->status === 'active' && !$fixedDeposit->isMatured())
            <a href="{{ route('fixed-deposits.break-form', $fixedDeposit) }}" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-scissors me-1"></i>Break FD
            </a>
        @endif
        @if(in_array($fixedDeposit->status, ['active','matured']))
            <a href="{{ route('fixed-deposits.mature-form', $fixedDeposit) }}" class="btn btn-success btn-sm">
                <i class="bi bi-check2-all me-1"></i>Process Maturity Payout
            </a>
        @endif
        @endcan
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-4 text-center">
                        <div class="text-muted small">Principal</div>
                        <div class="fw-bold fs-5">{{ number_format($fixedDeposit->principal, $dp) }}</div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="text-muted small">Interest Earned</div>
                        <div class="fw-bold fs-5 text-success">{{ number_format($fixedDeposit->interest_amount, $dp) }}</div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="text-muted small">Maturity Amount</div>
                        <div class="fw-bold fs-5 text-primary">{{ number_format($fixedDeposit->maturity_amount, $dp) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-6 fw-normal text-muted">Rate</dt><dd class="col-6">{{ $fixedDeposit->product ? $fixedDeposit->product->rateLabel($fixedDeposit->interest_rate) : $fixedDeposit->interest_rate . '% p.a.' }}{{ $fixedDeposit->isCompounding() ? ' · compounded monthly' : '' }}</dd>
                    <dt class="col-6 fw-normal text-muted">Term</dt><dd class="col-6">{{ $fixedDeposit->term_months }} months</dd>
                    <dt class="col-6 fw-normal text-muted">Start Date</dt><dd class="col-6">{{ $fixedDeposit->start_date->format('d M Y') }}</dd>
                    <dt class="col-6 fw-normal text-muted">Maturity</dt>
                    <dd class="col-6 {{ $fixedDeposit->isMatured() ? 'fw-bold text-warning' : '' }}">{{ $fixedDeposit->maturity_date->format('d M Y') }}</dd>
                    <dt class="col-6 fw-normal text-muted">Status</dt><dd class="col-6"><span class="badge badge-status-{{ $fixedDeposit->status }}">{{ ucfirst($fixedDeposit->status) }}</span></dd>
                    @if($fixedDeposit->status === 'active')
                        <dt class="col-6 fw-normal text-muted">Days Left</dt><dd class="col-6">{{ $fixedDeposit->daysToMaturity() }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>

@if($fixedDeposit->isMatured() && $fixedDeposit->status === 'active')
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        This fixed deposit has matured. Process the payout to release <strong>{{ number_format($fixedDeposit->maturity_amount, $dp) }}</strong> to the client.
        @can('mature fixed-deposits')
        <a href="{{ route('fixed-deposits.mature-form', $fixedDeposit) }}" class="btn btn-warning btn-sm ms-3">Process Now</a>
        @endcan
    </div>
@endif
@if($fixedDeposit->status === 'broken')
    <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-scissors fs-5"></i>
        <span>This fixed deposit was <strong>broken early</strong> on <strong>{{ $fixedDeposit->closed_date?->format('d M Y') }}</strong>.
        Only the principal of <strong>{{ number_format($fixedDeposit->principal, $dp) }}</strong> was returned. Interest was forfeited.</span>
    </div>
@endif

{{-- Monthly statement: interest earned on each monthly anniversary --}}
@php $schedule = $fixedDeposit->monthlySchedule(); $earnedRows = collect($schedule)->where('earned', true); @endphp
<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center bg-white">
        <span class="fw-semibold"><i class="bi bi-calendar3 me-1 text-primary"></i>Monthly statement</span>
        <span class="small text-muted d-flex align-items-center gap-2">Interest earned on the {{ $fixedDeposit->start_date->format('jS') }} of each month · earned so far
            <b class="text-success">{{ number_format($earnedRows->last()['cumulative'] ?? 0, $dp) }}</b> of {{ number_format($fixedDeposit->interest_amount, $dp) }}
            · booked <b>{{ number_format($fixedDeposit->accrued_interest, $dp) }}</b>
            @can('mature fixed-deposits')
            @if($fixedDeposit->status === 'active' && ($earnedRows->last()['cumulative'] ?? 0) > $fixedDeposit->accrued_interest + 0.01)
                <form method="POST" action="{{ route('fixed-deposits.accrue-one', $fixedDeposit) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success py-0">Book interest due</button></form>
            @endif
            @endcan
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Month</th><th>Anniversary date</th><th class="text-end">Interest earned</th><th class="text-end">Total interest</th><th class="text-end">Investment value</th><th class="pe-3">Status</th></tr></thead>
            <tbody>
                <tr class="text-muted"><td class="ps-3">—</td><td>{{ $fixedDeposit->start_date->format('d M Y') }}</td><td class="text-end">Principal placed</td><td class="text-end">0</td><td class="text-end">{{ number_format($fixedDeposit->principal, $dp) }}</td><td class="pe-3">Value date</td></tr>
            @foreach($schedule as $row)
                <tr class="{{ $row['earned'] ? '' : 'text-muted' }}">
                    <td class="ps-3">{{ $row['month'] }}</td>
                    <td>{{ $row['date']->format('d M Y') }}</td>
                    <td class="text-end {{ $row['earned'] ? 'text-success fw-semibold' : '' }}">{{ number_format($row['interest'], $dp) }}</td>
                    <td class="text-end">{{ number_format($row['cumulative'], $dp) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($row['value'], $dp) }}</td>
                    <td class="pe-3">
                        @if($row['earned'])<span class="badge bg-success-subtle text-success-emphasis">Earned</span>@else<span class="badge bg-light text-muted">Upcoming</span>@endif
                        @if($loop->last)<span class="badge bg-primary-subtle text-primary-emphasis">Maturity · payout {{ number_format($fixedDeposit->maturity_amount, $dp) }}</span>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
