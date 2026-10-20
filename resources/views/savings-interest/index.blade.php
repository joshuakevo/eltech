@extends('layouts.app')
@section('title', 'Running Interest')
@section('breadcrumb')
    <li class="breadcrumb-item">Accounting</li>
    <li class="breadcrumb-item active">Running Interest</li>
@endsection
@section('content')
<div class="page-header">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-percent me-1 text-primary"></i>Running Interest</h4>
        <div class="text-muted small">Preview, post and undo savings interest for each product.</div>
    </div>
</div>

@php
    $accents = ['#7c3aed', '#2563eb', '#0d9488', '#d97706'];
@endphp
<ul class="nav nav-tabs mb-3">
    @foreach($products as $i => $p)
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'p'.$p->id ? 'active fw-semibold' : '' }}" href="{{ route('savings-interest.index', ['tab' => 'p'.$p->id]) }}" style="{{ $tab === 'p'.$p->id ? 'border-top:3px solid '.$accents[$i % 4] : '' }}">
            <i class="bi bi-piggy-bank me-1" style="color:{{ $accents[$i % 4] }}"></i>{{ $p->name }}
        </a>
    </li>
    @endforeach
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'groups' ? 'active fw-semibold' : '' }}" href="{{ route('savings-interest.index', ['tab' => 'groups']) }}" style="{{ $tab === 'groups' ? 'border-top:3px solid #64748b' : '' }}">
            <i class="bi bi-people me-1 text-secondary"></i>Groups
        </a>
    </li>
</ul>

@if($product)
<div class="row g-3">
    {{-- Rates --}}
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">How interest is worked out</div>
            <div class="card-body small">
                @if(($product->interest_run_method ?: 'tiers') === 'bands')
                    <p class="text-muted mb-2">Each day, the <strong>whole balance</strong> earns the rate of the highest band it reaches (annual rate ÷ 365). Below the lowest band: no interest.</p>
                    <form method="POST" action="{{ route('savings-interest.bands', $product) }}">
                        @csrf
                        <table class="table table-sm align-middle mb-2" id="bandsTable">
                            <thead class="table-light"><tr><th>Balance from (UGX)</th><th style="width:90px">Rate % p.a.</th><th></th></tr></thead>
                            <tbody>
                            @foreach($bands as $k => $b)
                                <tr>
                                    <td><input type="number" step="0.01" min="0" name="bands[{{ $k }}][min]" value="{{ $b['min'] + 0 }}" class="form-control form-control-sm" required></td>
                                    <td><input type="number" step="0.01" min="0" max="100" name="bands[{{ $k }}][rate]" value="{{ $b['rate'] + 0 }}" class="form-control form-control-sm" required></td>
                                    <td><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="this.closest('tr').remove()"><i class="bi bi-x-circle"></i></button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addBand()"><i class="bi bi-plus"></i> Band</button>
                            <button class="btn btn-sm btn-primary ms-auto">Save rates</button>
                        </div>
                    </form>
                @else
                    <p class="text-muted mb-2">Uses the <strong>savings interest tiers</strong>: each slice of the day's balance earns its own tier's rate (annual rate ÷ 365).</p>
                    <table class="table table-sm mb-2">
                        <thead class="table-light"><tr><th>From</th><th>To</th><th class="text-end">Rate</th></tr></thead>
                        <tbody>
                        @foreach($tiers as $t)
                            <tr><td>{{ number_format($t->min_balance) }}</td><td>{{ $t->max_balance !== null ? number_format($t->max_balance) : 'and above' }}</td><td class="text-end">{{ $t->rate + 0 }}%</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                    @can('manage savings interest tiers')
                    <a href="{{ route('savings-interest-tiers.edit') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit tiers</a>
                    @endcan
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Special rates</span>
                <span class="badge bg-secondary-subtle text-secondary">{{ $specials->count() }}</span>
            </div>
            <div class="card-body small">
                <p class="text-muted mb-2">Accounts that don't follow the normal rates. The whole balance earns this rate every day (0 = no interest).</p>
                @if($specials->isNotEmpty())
                <ul class="list-group list-group-flush mb-2">
                    @foreach($specials as $sp)
                    <li class="list-group-item px-0 py-1 d-flex align-items-center gap-2">
                        <span class="font-monospace">{{ $sp->account_number }}</span>
                        <span class="text-muted text-truncate flex-fill">{{ $sp->client->name ?? '' }}</span>
                        <span class="fw-semibold">{{ $sp->interest_rate_override + 0 }}%</span>
                        <form method="POST" action="{{ route('savings-interest.special', $product) }}" class="d-inline">@csrf
                            <input type="hidden" name="account_number" value="{{ $sp->account_number }}"><input type="hidden" name="rate" value="">
                            <button class="btn btn-link btn-sm p-0 text-danger" title="Back to normal rates"><i class="bi bi-x-circle"></i></button>
                        </form>
                    </li>
                    @endforeach
                </ul>
                @endif
                <form method="POST" action="{{ route('savings-interest.special', $product) }}" class="row g-1">
                    @csrf
                    <div class="col-12">
                        <select name="account_number" class="form-select form-select-sm ts-select" required>
                            <option value="">Search member by name, number or account…</option>
                            @foreach($pickAccounts as $pa)
                                <option value="{{ $pa->account_number }}">{{ $pa->client->name ?? '—' }} · {{ $pa->client->client_number ?? '' }} · {{ $pa->account_number }} · {{ number_format($pa->balance, 0) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-8"><input name="rate" type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" placeholder="Special rate % p.a." required></div>
                    <div class="col-4"><button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-plus"></i> Add</button></div>
                </form>
            </div>
        </div>
    </div>

    {{-- Run --}}
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('savings-interest.index') }}" class="row g-2 align-items-end">
                    <input type="hidden" name="tab" value="p{{ $product->id }}">
                    <input type="hidden" name="preview" value="1">
                    @unless($latest)
                    <div class="col-sm-3">
                        <label class="form-label small fw-semibold mb-1" title="First run: no account counts from earlier than this date (blank = from each account's last interest date or first transaction)">Count from <i class="bi bi-info-circle text-muted"></i></label>
                        <input type="date" name="from" class="form-control" value="{{ $from }}">
                    </div>
                    @endunless
                    <div class="col-sm-3">
                        <label class="form-label small fw-semibold mb-1">Interest up to</label>
                        <input type="date" name="period_end" class="form-control" value="{{ $periodEnd }}" max="{{ today()->toDateString() }}" required>
                    </div>
                    <div class="col-sm-3">
                        <button class="btn btn-primary w-100"><i class="bi bi-eye me-1"></i>Preview</button>
                    </div>
                    <div class="col-sm-3 small text-muted">
                        @if($latest)
                            Last run: <strong>{{ $latest->period_end->format('d M Y') }}</strong><br>{{ number_format($latest->total_interest, $dp) }} · {{ $latest->accounts_count }} accounts
                        @else
                            Not run yet — accounts count from their last interest date / first transaction, or from "Count from".
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Runs</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Up to</th><th class="text-end">Accounts</th><th class="text-end">Interest</th><th>Run by</th><th>Posted</th><th class="pe-3"></th></tr></thead>
                    <tbody class="small">
                    @forelse($history as $h)
                        <tr>
                            <td class="ps-3 fw-semibold">{{ $h->period_end->format('d M Y') }}</td>
                            <td class="text-end">{{ $h->accounts_count }}</td>
                            <td class="text-end">{{ number_format($h->total_interest, $dp) }}</td>
                            <td>{{ $h->createdBy->name ?? '—' }}</td>
                            <td class="text-muted">{{ $h->created_at->format('d M Y H:i') }}</td>
                            <td class="pe-3 text-end text-nowrap"><a href="{{ route('savings-interest.show', $h) }}" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-file-text me-1"></i>Report</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No runs yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="mt-3">
        @if($preview)
        @php $rows = $preview['rows']; $total = round($rows->sum('interest'), 2); @endphp
        <div class="card mb-3 border-primary">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center bg-primary-subtle">
                <span><i class="bi bi-eye me-1"></i>Preview — {{ $product->name }} interest to {{ \Carbon\Carbon::parse($periodEnd)->format('d M Y') }} <span class="badge bg-warning text-dark ms-1">Not posted</span></span>
                <a href="{{ route('savings-interest.index', ['tab' => 'p'.$product->id, 'period_end' => $periodEnd, 'preview' => 1, 'from' => $from, 'format' => 'excel']) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
            </div>
            <div class="card-body py-2 small d-flex flex-wrap gap-4">
                <div><div class="text-muted">Total interest</div><div class="fs-5 fw-bold text-primary">{{ number_format($total, $dp) }}</div></div>
                <div><div class="text-muted">Accounts earning</div><div class="fs-5 fw-bold">{{ $rows->count() }}</div></div>
                <div><div class="text-muted">Earning nothing</div><div class="fs-5 fw-bold text-muted">{{ $preview['zero'] }}</div></div>
                @if($latest && $latest->period_end->toDateString() >= $periodEnd)
                    <div class="text-danger align-self-center"><i class="bi bi-exclamation-triangle me-1"></i>Already run to {{ $latest->period_end->format('d M Y') }} — choose a later date or undo that run.</div>
                @endif
            </div>
            <div class="table-responsive" style="max-height:60vh">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light" style="position:sticky;top:0">
                        <tr><th class="ps-3">#</th><th>Member</th><th>Account</th><th>Period</th><th class="text-end">Days</th><th class="text-end">Avg balance</th><th class="text-end">Closing balance</th><th>Rate</th><th class="text-end pe-3">Interest</th></tr>
                    </thead>
                    <tbody class="small">
                    @forelse($rows as $i => $r)
                        <tr>
                            <td class="ps-3">{{ $i + 1 }}</td>
                            <td class="text-nowrap">{{ $r['account']->client->name ?? '' }} <span class="text-muted">{{ $r['account']->client->client_number ?? '' }}</span></td>
                            <td class="font-monospace text-nowrap"><a href="{{ route('savings.show', $r['account']) }}" class="text-decoration-none">{{ $r['account']->account_number }}</a></td>
                            <td class="text-nowrap">{{ \Carbon\Carbon::parse($r['period_start'])->format('d M') }} – {{ \Carbon\Carbon::parse($r['period_end'])->format('d M Y') }}</td>
                            <td class="text-end">{{ $r['days'] }}</td>
                            <td class="text-end">{{ number_format($r['average_balance'], $dp) }}</td>
                            <td class="text-end">{{ number_format($r['closing_balance'], $dp) }}</td>
                            <td>{{ $r['rate_label'] }}</td>
                            <td class="text-end pe-3 fw-semibold text-nowrap">{{ number_format($r['interest'], $dp) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No account earns interest for this period.</td></tr>
                    @endforelse
                    </tbody>
                    @if($rows->isNotEmpty())
                    <tfoot class="table-light fw-bold small"><tr><td colspan="8" class="ps-3">Total</td><td class="text-end pe-3">{{ number_format($total, $dp) }}</td></tr></tfoot>
                    @endif
                </table>
            </div>
            @if($rows->isNotEmpty() && !($latest && $latest->period_end->toDateString() >= $periodEnd))
            <div class="card-footer bg-white d-flex flex-wrap gap-2 align-items-center">
                <div class="small text-muted flex-fill">Posting: DR {{ $posting['expense'] }} / CR {{ $posting['liability'] }} — one interest row on each member's statement, dated {{ \Carbon\Carbon::parse($periodEnd)->format('d M Y') }}.</div>
                <form method="POST" action="{{ route('savings-interest.run', $product) }}" onsubmit="return confirm('Post {{ number_format($total, $dp) }} interest to {{ $rows->count() }} {{ $product->name }} accounts?')">
                    @csrf
                    <input type="hidden" name="period_end" value="{{ $periodEnd }}">
                    <input type="hidden" name="from" value="{{ $from }}">
                    <input type="hidden" name="expected_total" value="{{ $total }}">
                    <button class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Run interest</button>
                </form>
            </div>
            @endif
        </div>
        @endif

</div>

<script>
let bandIdx = {{ count($bands ?? []) + 10 }};
function addBand() {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td><input type="number" step="0.01" min="0" name="bands[${bandIdx}][min]" class="form-control form-control-sm" required></td>
        <td><input type="number" step="0.01" min="0" max="100" name="bands[${bandIdx}][rate]" class="form-control form-control-sm" required></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="this.closest('tr').remove()"><i class="bi bi-x-circle"></i></button></td>`;
    document.querySelector('#bandsTable tbody').appendChild(tr);
    bandIdx++;
}
</script>
@else
<div class="alert alert-warning small"><i class="bi bi-hourglass-split me-1"></i><strong>Group interest is not agreed yet.</strong> Once the rate is agreed it will be run here like the other products. Group balances and the current per-group rate are shown below; a group's interest can still be posted from its own page.</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th class="ps-3">Group</th><th class="text-end">Members' balances</th><th class="text-end">Monthly rate</th><th class="pe-3"></th></tr></thead>
            <tbody class="small">
            @foreach($groups as $g)
                <tr>
                    <td class="ps-3">{{ $g->name }} <span class="text-muted">{{ $g->group_number }}</span></td>
                    <td class="text-end">{{ number_format($g->total_balance, $dp) }}</td>
                    <td class="text-end">{{ $g->monthly_interest_rate !== null ? round((float) $g->monthly_interest_rate) . '%' : '—' }}</td>
                    <td class="pe-3 text-end">@can('manage groups')<a href="{{ route('groups.interest', $g) }}" class="btn btn-sm btn-outline-secondary py-0">Interest page</a>@endcan</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot class="table-light fw-bold small"><tr><td class="ps-3">Total</td><td class="text-end">{{ number_format($groups->sum('total_balance'), $dp) }}</td><td colspan="2"></td></tr></tfoot>
        </table>
    </div>
</div>
@endif
@endsection
