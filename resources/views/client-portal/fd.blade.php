@extends('layouts.client-portal')
@section('title', $fixedDeposit->product->name ?? $fixedDeposit->deposit_number)
@section('content')
@php
    $p = $fixedDeposit->product;
    $schedule = $fixedDeposit->monthlySchedule();
    $earned = collect($schedule)->where('earned', true)->last()['cumulative'] ?? 0;
    $next = collect($schedule)->firstWhere('earned', false);
    $isPlacement = $p && ($p->isMonthlyRate() || $p->isAnniversary());
    $days = max(0, today()->diffInDays($fixedDeposit->maturity_date, false));
    $progress = $fixedDeposit->interest_amount > 0 ? min(100, round($earned / $fixedDeposit->interest_amount * 100)) : 0;
@endphp

<div class="mb-3">
    <a href="{{ route('client-portal.accounts') }}" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>My Statements</a>
</div>

<div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h5 class="fw-bold mb-0">{{ $p->name ?? 'Fixed deposit' }}</h5>
        @php $assetClass = $fixedDeposit->asset_class ?: ($p->asset_class ?? ''); @endphp
        <span class="text-muted small">{{ $fixedDeposit->certificate_reference ?: $fixedDeposit->deposit_number }}@if($assetClass) · {{ $assetClass }}@endif</span>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge badge-status-{{ $fixedDeposit->status }} px-3 py-2 rounded-pill">{{ ucfirst($fixedDeposit->status) }}</span>
        <a href="{{ route('client-portal.fd.statement', $fixedDeposit) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar3 me-1"></i>Monthly statement (PDF)</a>
        <a href="{{ route('client-portal.fd.pdf', $fixedDeposit) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-award me-1"></i>{{ $isPlacement ? 'Placement certificate' : 'Certificate' }} (PDF)</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-card text-center h-100">
        <div class="text-muted small mb-1">Principal</div>
        <div class="fw-bold fs-5">{{ number_format($fixedDeposit->principal, 0) }}</div>
    </div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center h-100">
        <div class="text-muted small mb-1">Rate</div>
        <div class="fw-bold fs-5">{{ $p ? $p->rateLabel($fixedDeposit->interest_rate) : $fixedDeposit->interest_rate . '% p.a.' }}</div>
        @if($fixedDeposit->isCompounding())<div class="text-muted" style="font-size:.72rem">compounded monthly</div>@endif
    </div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center h-100">
        <div class="text-muted small mb-1">Earned so far</div>
        <div class="fw-bold fs-5 text-success">{{ number_format($earned, 0) }}</div>
        <div class="text-muted" style="font-size:.72rem">of {{ number_format($fixedDeposit->interest_amount, 0) }}</div>
    </div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center h-100">
        <div class="text-muted small mb-1">Payout at maturity</div>
        <div class="fw-bold fs-5 text-primary">{{ number_format($fixedDeposit->maturity_amount, 0) }}</div>
        <div class="text-muted" style="font-size:.72rem">{{ $fixedDeposit->maturity_date->format('d M Y') }}</div>
    </div></div>
</div>

<div class="card mb-3">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between small mb-1">
            <span class="text-muted">{{ $fixedDeposit->start_date->format('d M Y') }} — value date</span>
            <span class="fw-semibold">
                @if($fixedDeposit->status === 'active' && $days > 0) {{ $days }} days to maturity
                @elseif(in_array($fixedDeposit->status, ['active', 'matured'])) Matured — payout due
                @else {{ ucfirst($fixedDeposit->status) }} @endif
            </span>
            <span class="text-muted">{{ $fixedDeposit->maturity_date->format('d M Y') }} — maturity</span>
        </div>
        <div class="progress" style="height:8px"><div class="progress-bar bg-success" style="width: {{ $progress }}%"></div></div>
        @if($next && in_array($fixedDeposit->status, ['active']))
            <div class="small text-muted mt-2"><i class="bi bi-calendar-event me-1"></i>Next return: <b>{{ number_format($next['interest'], 0) }}</b> on {{ $next['date']->format('d M Y') }}</div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-calendar3 me-1 text-primary"></i>Monthly statement</span>
        <span class="small text-muted">Returns earned on the {{ $fixedDeposit->start_date->format('jS') }} of each month, paid with the principal at maturity</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Month</th><th>Date</th><th class="text-end">Return</th><th class="text-end">Total returns</th><th class="text-end pe-3">Investment value</th></tr></thead>
            <tbody>
                <tr class="text-muted"><td class="ps-3">—</td><td>{{ $fixedDeposit->start_date->format('d M Y') }}</td><td class="text-end">Principal placed</td><td class="text-end">0</td><td class="text-end pe-3">{{ number_format($fixedDeposit->principal, 0) }}</td></tr>
            @foreach($schedule as $row)
                <tr class="{{ $row['earned'] ? '' : 'text-muted' }}">
                    <td class="ps-3">{{ $row['month'] }}</td>
                    <td>{{ $row['date']->format('d M Y') }} @if(!$row['earned'])<span class="badge bg-light text-muted">upcoming</span>@endif</td>
                    <td class="text-end {{ $row['earned'] ? 'text-success fw-semibold' : '' }}">{{ number_format($row['interest'], 0) }}</td>
                    <td class="text-end">{{ number_format($row['cumulative'], 0) }}</td>
                    <td class="text-end pe-3 fw-semibold">{{ number_format($row['value'], 0) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot class="table-light fw-semibold"><tr><td class="ps-3" colspan="2">Total payout at maturity</td><td class="text-end">{{ number_format($fixedDeposit->interest_amount, 0) }}</td><td></td><td class="text-end pe-3">{{ number_format($fixedDeposit->maturity_amount, 0) }}</td></tr></tfoot>
        </table>
    </div>
</div>
@endsection
