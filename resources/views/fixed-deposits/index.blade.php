@extends('layouts.app')
@section('title', 'Fixed Deposits')
@section('breadcrumb')
    <li class="breadcrumb-item active">Fixed Deposits</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Fixed Deposits</h4>
    <div class="d-flex gap-2">
        @can('mature fixed-deposits')
        <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#bulkAccrueModal">
            <i class="bi bi-percent me-1"></i> Accrue Interest
        </button>
        @endcan
        <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-download me-1"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format' => 'excel']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel (CSV)</a></li>
                <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>PDF</a></li>
            </ul>
        </div>
        @can('create fixed-deposits')
        <a href="{{ route('fixed-deposits.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Fixed Deposit</a>
        @endcan
    </div>
</div>

{{-- Tabs: all running · one per product · closed --}}
@php
    $fdTabs = [['all', 'All running', 'bi-collection', '15, 36, 68']];
    foreach ($products as $p) {
        $hex = ltrim($p->color, '#');
        $fdTabs[] = ['p' . $p->id, $p->name, $p->isAnniversary() || $p->isMonthlyRate() ? 'bi-graph-up-arrow' : 'bi-safe', implode(', ', array_map('hexdec', str_split($hex, 2)))];
    }
    $fdTabs[] = ['closed', 'Closed', 'bi-archive', '100, 116, 139'];
    $tabLabel = collect($fdTabs)->firstWhere(0, $tab)[1] ?? 'All running';
@endphp
<style>
    .fd-tabs { border-bottom: 1px solid #e2e6ec; gap: .25rem; }
    .fd-tabs .nav-link { color: #5b6573; font-weight: 500; border: 0; border-bottom: 2px solid transparent; border-radius: .4rem .4rem 0 0; padding: .6rem .95rem; margin-bottom: -1px; }
    .fd-tabs .nav-link i { color: rgb(var(--tab)); opacity: .75; }
    .fd-tabs .nav-link:hover { color: rgb(var(--tab)); background: rgba(var(--tab), .05); }
    .fd-tabs .nav-link.active { color: rgb(var(--tab)); font-weight: 600; background: rgba(var(--tab), .09); border-bottom: 3px solid rgb(var(--tab)); }
    .fd-tabs .count { font-size: .7rem; background: rgba(var(--tab), .12); color: rgb(var(--tab)); border-radius: 999px; padding: .05rem .45rem; margin-left: .3rem; }
    .fd-tabs .tab-sep { width: 1px; background: #e2e6ec; margin: .55rem .5rem; }
</style>
<ul class="nav fd-tabs mb-4">
    @foreach($fdTabs as [$key, $label, $icon, $rgb])
        @if($key === 'closed')<li class="tab-sep" aria-hidden="true"></li>@endif
        <li class="nav-item">
            <a class="nav-link text-nowrap {{ $tab === $key ? 'active' : '' }}" style="--tab: {{ $rgb }}" href="{{ route('fixed-deposits.index', ['tab' => $key]) }}">
                <i class="bi {{ $icon }} me-1"></i>{{ $label }}<span class="count">{{ $tabCounts[$key] ?? 0 }}</span>
            </a>
        </li>
    @endforeach
</ul>

<div class="row g-3 mb-4">
    @foreach([
        ['Total principal', $totalPrincipal, ''],
        [$tab === 'closed' ? 'Interest paid' : 'Interest at maturity', $totalInterest, 'text-success'],
        [$tab === 'closed' ? 'Total paid out' : 'Payout at maturity', $totalPayout, ''],
    ] as [$l, $v, $cls])
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">{{ $l }}</div>
            <div class="fs-5 fw-bold {{ $cls }}">{{ number_format($v, $dp) }}</div>
        </div></div>
    </div>
    @endforeach
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">{{ $tabLabel }} — deposits</div>
            <div class="fs-5 fw-bold">{{ number_format($totalCount) }}
                @if($tab !== 'closed' && $maturingSoon)<span class="badge bg-warning text-dark ms-1" style="font-size:.7rem">{{ $maturingSoon }} due / maturing soon</span>@endif
            </div>
        </div></div>
    </div>
</div>

{{-- Bulk Accrue Interest Modal --}}
<div class="modal fade" id="bulkAccrueModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-percent me-1"></i> Accrue Interest — All Active Deposits</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('fixed-deposits.accrue-interest-bulk') }}"
                  onsubmit="return confirm('Accrue interest expense for all active fixed deposits up to the selected date?')">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info small mb-3">
                        Posts the interest expense accrued so far on <strong>every active fixed deposit</strong> —
                        the proportional share of each deposit's total interest, from its start date up to the selected date,
                        minus whatever has already been accrued. Safe to run repeatedly; it only posts the difference.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Accrue To Date <span class="text-danger">*</span></label>
                        <input type="date" name="interest_date" class="form-control"
                               value="{{ today()->toDateString() }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-success"><i class="bi bi-check-circle me-1"></i> Run Accrual</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body pb-0">
        <form class="row g-2 mb-3" method="GET">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Deposit # or client..." value="{{ request('search') }}"></div>
            @if($tab !== 'closed')
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="alert" @selected(request('status')=='alert')>Matured or maturing within {{ (int) \App\Models\SystemSetting::get('fd_alert_days', 7) }} days</option>
                    <option value="due" @selected(request('status')=='due')>Due — past maturity</option>
                    <option value="soon" @selected(request('status')=='soon')>Maturing within {{ (int) \App\Models\SystemSetting::get('fd_alert_days', 7) }} days</option>
                    <option value="active" @selected(request('status')=='active')>Active</option>
                    <option value="matured" @selected(request('status')=='matured')>Matured</option>
                </select>
            </div>
            @endif
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Deposit #</th><th>Client</th><th>Product</th>
                <th class="text-end">Principal</th><th class="text-end">Interest</th>
                <th class="text-end">Maturity Amount</th><th>Maturity Date</th><th>Status</th><th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($deposits as $fd)
                <tr class="{{ $fd->isMatured() && $fd->status === 'active' ? 'table-warning' : '' }}">
                    <td class="ps-3 font-monospace">{{ $fd->deposit_number }}</td>
                    <td>
                        @if($fd->client)
                            <a href="{{ route('clients.show', $fd->client) }}" class="text-decoration-none">{{ $fd->client->name }}</a>
                        @else
                            <span class="text-muted fst-italic">Deleted client</span>
                        @endif
                    </td>
                    @php $pc = $productColors[$fd->product_id] ?? '#64748b'; @endphp
                    <td><span class="badge rounded-pill fw-semibold" style="background: {{ $pc }}1a; color: {{ $pc }}; border: 1px solid {{ $pc }}55; font-size: .72rem">{{ $fd->product->name }}</span>
                        <div class="text-muted" style="font-size:.7rem">{{ $fd->product->rateLabel($fd->interest_rate) }}{{ $fd->isCompounding() ? ' · compounded' : '' }}</div></td>
                    <td class="text-end">{{ number_format($fd->principal, $dp) }}</td>
                    <td class="text-end">{{ number_format($fd->interest_amount, $dp) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($fd->maturity_amount, $dp) }}</td>
                    <td class="{{ $fd->isMatured() ? 'fw-semibold text-warning' : '' }}">{{ $fd->maturity_date->format('d M Y') }}</td>
                    <td><span class="badge badge-status-{{ $fd->status }}">{{ ucfirst($fd->status) }}</span></td>
                    <td class="pe-3">
                        <a href="{{ route('fixed-deposits.show', $fd) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        @can('mature fixed-deposits')
                        @if(in_array($fd->status, ['active','matured']))
                            <a href="{{ route('fixed-deposits.mature-form', $fd) }}" class="btn btn-sm btn-success"><i class="bi bi-check2-all"></i></a>
                        @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No fixed deposits found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $deposits->withQueryString()->links() }}</div>
</div>
@endsection
