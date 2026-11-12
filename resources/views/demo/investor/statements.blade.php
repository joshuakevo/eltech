@extends('demo.investor.layout')
@section('title', 'My Statements')
@section('content')
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
    .stmt-item .acts { display: flex; gap: .35rem; flex-wrap: wrap; }
</style>

<div class="mb-4 d-flex align-items-center gap-3">
    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center fw-bold text-primary" style="width:46px;height:46px">D</div>
    <div>
        <h5 class="fw-bold mb-0">My Statements</h5>
        <div class="text-muted small">{{ $client->name }} · {{ $client->client_number }} · Member since {{ $client->joining_date->format('d M Y') }}</div>
    </div>
</div>

<div class="stmt-section">
    <div class="stmt-head"><div class="ic bg-warning bg-opacity-10 text-warning"><i class="bi bi-graph-up-arrow"></i></div><h6>Investments &amp; fixed deposits</h6></div>
    @foreach($investments as $fd)
        @php $earned = collect($fd->monthlySchedule())->where('earned', true)->last()['cumulative'] ?? 0; @endphp
        <div class="stmt-item">
            <div class="main">
                <div class="title">{{ $fd->product->name }}</div>
                <div class="sub">{{ $fd->certificate_reference ?: $fd->deposit_number }} · {{ $fd->product->rateLabel($fd->interest_rate) }} · matures {{ $fd->maturity_date->format('d M Y') }}
                    · <span class="badge badge-status-active">Active</span></div>
            </div>
            <div class="amt"><div class="v text-warning">{{ number_format($fd->principal, 0) }}</div><div class="l">earned so far <b class="text-success">{{ number_format($earned, 0) }}</b></div></div>
            <div class="acts">
                <a href="{{ route('demo.investor.investment', $fd->id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Statement</a>
                <a href="{{ route('demo.investor.investment.statement', $fd->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Monthly statement PDF"><i class="bi bi-calendar3"></i></a>
                <a href="{{ route('demo.investor.investment.certificate', $fd->id) }}" target="_blank" class="btn btn-sm btn-outline-danger" title="Certificate PDF"><i class="bi bi-award"></i></a>
            </div>
        </div>
    @endforeach
</div>

<div class="stmt-section">
    <div class="stmt-head"><div class="ic bg-success bg-opacity-10 text-success"><i class="bi bi-piggy-bank"></i></div><h6>Savings</h6></div>
    <div class="stmt-item">
        <div class="main">
            <div class="title">{{ $savings->product->name }}</div>
            <div class="sub">{{ $savings->account_number }} · <span class="badge badge-status-active">Active</span></div>
        </div>
        <div class="amt"><div class="v text-success">{{ number_format($savings->balance, 0) }}</div><div class="l">balance</div></div>
        <div class="acts"><a href="{{ route('demo.investor.savings') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Statement</a></div>
    </div>
</div>
@endsection
