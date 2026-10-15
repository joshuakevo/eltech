@extends('layouts.client-portal')
@section('title', 'My Statements')
@section('content')
@php
    $savings = $client->savingsAccounts->sortByDesc(fn ($a) => $a->status === 'active');
    $loans = $client->loans->sortByDesc(fn ($l) => in_array($l->status, ['active', 'defaulted', 'overdue', 'disbursed']));
    $fds = $client->fixedDeposits->sortByDesc(fn ($f) => in_array($f->status, ['active', 'matured']));
    $shares = $client->shares->where('status', '!=', 'liquidated');
@endphp
<style>
    .stmt-section { margin-bottom: 1.25rem; }
    .stmt-head { display: flex; align-items: center; gap: .6rem; margin-bottom: .6rem; }
    .stmt-head .ic { width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 1.05rem; }
    .stmt-head h6 { margin: 0; font-weight: 700; }
    .stmt-item { background: #fff; border: 1px solid #e8ecf1; border-radius: 12px; padding: .85rem 1rem; display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; margin-bottom: .5rem; }
    .stmt-item .main { flex: 1 1 220px; min-width: 0; }
    .stmt-item .title { font-weight: 600; }
    .stmt-item .sub { font-size: .76rem; color: #6b7280; }
    .stmt-item .amt { text-align: right; min-width: 130px; }
    .stmt-item .amt .v { font-weight: 700; font-size: 1.05rem; }
    .stmt-item .amt .l { font-size: .7rem; color: #6b7280; }
    .stmt-item .acts { display: flex; gap: .35rem; }
    .stmt-empty { color: #9ca3af; font-size: .85rem; padding: .4rem 0 .8rem; }
</style>

<div class="mb-4 d-flex align-items-center gap-3">
    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center fw-bold text-primary" style="width:46px;height:46px;flex-shrink:0">
        {{ strtoupper(substr($client->name, 0, 1)) }}
    </div>
    <div>
        <h5 class="fw-bold mb-0">My Statements</h5>
        <div class="text-muted small">{{ $client->name }} · {{ $client->client_number }} · Member since {{ $client->joining_date?->format('d M Y') ?? optional($client->created_at)->format('d M Y') }}</div>
    </div>
</div>

{{-- Savings --}}
<div class="stmt-section">
    <div class="stmt-head"><div class="ic bg-success bg-opacity-10 text-success"><i class="bi bi-piggy-bank"></i></div><h6>Savings</h6></div>
    @forelse($savings as $sa)
        <div class="stmt-item">
            <div class="main">
                <div class="title">{{ $sa->product->name ?? 'Savings' }}</div>
                <div class="sub">{{ $sa->account_number }} · <span class="badge badge-status-{{ $sa->status }}">{{ ucfirst($sa->status) }}</span></div>
            </div>
            <div class="amt"><div class="v {{ $sa->balance < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($sa->balance, 0) }}</div><div class="l">balance</div></div>
            <div class="acts">
                <a href="{{ route('client-portal.savings', $sa) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Statement</a>
                <a href="{{ route('client-portal.savings.pdf', $sa) }}" class="btn btn-sm btn-outline-danger" title="Download PDF"><i class="bi bi-file-earmark-pdf"></i></a>
            </div>
        </div>
    @empty
        <div class="stmt-empty">No savings accounts.</div>
    @endforelse
</div>

{{-- Investments & fixed deposits --}}
<div class="stmt-section">
    <div class="stmt-head"><div class="ic bg-warning bg-opacity-10 text-warning"><i class="bi bi-graph-up-arrow"></i></div><h6>Investments &amp; fixed deposits</h6></div>
    @forelse($fds as $fd)
        @php $earned = collect($fd->monthlySchedule())->where('earned', true)->last()['cumulative'] ?? 0; @endphp
        <div class="stmt-item">
            <div class="main">
                <div class="title">{{ $fd->product->name ?? 'Fixed deposit' }}</div>
                <div class="sub">{{ $fd->certificate_reference ?: $fd->deposit_number }}
                    · {{ $fd->product ? $fd->product->rateLabel($fd->interest_rate) : $fd->interest_rate . '% p.a.' }}{{ $fd->isCompounding() ? ' compounded' : '' }}
                    · {{ $fd->start_date->format('d M Y') }} → {{ $fd->maturity_date->format('d M Y') }}
                    · <span class="badge badge-status-{{ $fd->status }}">{{ ucfirst($fd->status) }}</span></div>
            </div>
            <div class="amt"><div class="v">{{ number_format($fd->principal, 0) }}</div><div class="l">principal</div></div>
            <div class="amt"><div class="v text-success">{{ number_format($earned, 0) }}</div><div class="l">earned so far</div></div>
            <div class="amt"><div class="v text-primary">{{ number_format($fd->maturity_amount, 0) }}</div><div class="l">payout at maturity</div></div>
            <div class="acts">
                <a href="{{ route('client-portal.fd', $fd) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Details</a>
                <a href="{{ route('client-portal.fd.statement', $fd) }}" class="btn btn-sm btn-outline-secondary" title="Monthly statement (PDF)"><i class="bi bi-calendar3"></i></a>
                <a href="{{ route('client-portal.fd.pdf', $fd) }}" class="btn btn-sm btn-outline-danger" title="Certificate (PDF)"><i class="bi bi-award"></i></a>
            </div>
        </div>
    @empty
        <div class="stmt-empty">No investments or fixed deposits.</div>
    @endforelse
</div>

{{-- Loans --}}
<div class="stmt-section">
    <div class="stmt-head"><div class="ic bg-danger bg-opacity-10 text-danger"><i class="bi bi-cash-coin"></i></div><h6>Loans</h6></div>
    @forelse($loans as $loan)
        <div class="stmt-item">
            <div class="main">
                <div class="title">{{ $loan->product->name ?? 'Loan' }}</div>
                <div class="sub">{{ $loan->loan_number }}
                    @if($loan->disbursement_date) · from {{ $loan->disbursement_date->format('d M Y') }}@endif
                    · <span class="badge badge-status-{{ $loan->status }}">{{ $loan->isLockedUp() ? 'Under recovery' : ucfirst($loan->status) }}</span></div>
            </div>
            <div class="amt"><div class="v">{{ number_format($loan->principal, 0) }}</div><div class="l">loan amount</div></div>
            <div class="amt"><div class="v {{ $loan->total_outstanding > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($loan->total_outstanding, 0) }}</div><div class="l">balance owed</div></div>
            <div class="acts">
                <a href="{{ route('client-portal.loan', $loan) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Statement</a>
                <a href="{{ route('client-portal.loan.pdf', $loan) }}" class="btn btn-sm btn-outline-danger" title="Download PDF"><i class="bi bi-file-earmark-pdf"></i></a>
            </div>
        </div>
    @empty
        <div class="stmt-empty">No loans.</div>
    @endforelse
</div>

{{-- Shares --}}
@if($shares->count())
<div class="stmt-section">
    <div class="stmt-head"><div class="ic bg-primary bg-opacity-10 text-primary"><i class="bi bi-pie-chart"></i></div><h6>Shares</h6></div>
    @foreach($shares as $sh)
        <div class="stmt-item">
            <div class="main">
                <div class="title">Share {{ $sh->share_number }}</div>
                <div class="sub">Value {{ number_format($sh->share_value, 0) }} · <span class="badge badge-status-{{ $sh->status }}">{{ ucfirst($sh->status) }}</span></div>
            </div>
            <div class="amt"><div class="v text-primary">{{ number_format($sh->amount_paid, 0) }}</div><div class="l">paid</div></div>
        </div>
    @endforeach
</div>
@endif

{{-- Groups --}}
@if($groupMemberships->count())
<div class="stmt-section">
    <div class="stmt-head"><div class="ic bg-info bg-opacity-10 text-info"><i class="bi bi-people"></i></div><h6>Savings groups</h6></div>
    @foreach($groupMemberships as $gm)
        <div class="stmt-item">
            <div class="main">
                <div class="title">{{ $gm->group->name ?? 'Group' }}</div>
                <div class="sub">{{ $gm->group->group_number ?? '' }} · {{ $gm->is_leader ? 'Group leader' : 'Member' }}</div>
            </div>
            <div class="amt"><div class="v text-info">{{ number_format($gm->balance, 0) }}</div><div class="l">my balance</div></div>
        </div>
    @endforeach
</div>
@endif
@endsection
