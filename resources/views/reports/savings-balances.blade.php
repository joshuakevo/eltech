@extends('layouts.app')
@section('title', 'Savings Report')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Savings Report</li>
@endsection
@php
    $fmt   = fn ($n) => number_format($n, $dp);
    $short = function ($n) {
        $a = abs($n); $sign = $n < 0 ? '−' : '';
        if ($a >= 1e9) return $sign . rtrim(rtrim(number_format($a / 1e9, 2), '0'), '.') . 'B';
        if ($a >= 1e6) return $sign . rtrim(rtrim(number_format($a / 1e6, 2), '0'), '.') . 'M';
        if ($a >= 1e3) return $sign . rtrim(rtrim(number_format($a / 1e3, 1), '0'), '.') . 'K';
        return $sign . number_format($a, 0);
    };
    $change = function ($pct, $invert = false) {
        if ($pct === null) return '<span class="sv-chg text-muted">no prior data</span>';
        $up = $pct >= 0; $good = $invert ? !$up : $up;
        return '<span class="sv-chg ' . ($good ? 'up' : 'down') . '"><i class="bi bi-arrow-' . ($up ? 'up' : 'down') . '-right"></i>' . abs($pct) . '% vs previous</span>';
    };
    $q = fn (array $extra) => route('reports.savings-balances', array_filter(array_merge(['product_id' => $productId, 'segment_id' => $segmentId], $extra)));
    $rangeLabel = $from->equalTo($to) ? $from->format('D, d M Y') : $from->format('d M Y') . ' – ' . $to->format('d M Y');
    $trendMeta = [
        'growing'   => ['Growing',   'bi-graph-up-arrow',   '#059669'],
        'steady'    => ['Steady',    'bi-dash-lg',          '#6b7280'],
        'declining' => ['Declining', 'bi-graph-down-arrow', '#d97706'],
        'dormant'   => ['Dormant',   'bi-moon',             '#64748b'],
        'overdrawn' => ['Overdrawn', 'bi-exclamation-triangle', '#dc2626'],
    ];
    $busiest = collect($weekdays)->sortByDesc('total')->first();
    $mixTotal = max(1, $flows['member_deposits'] + $flows['payroll'] + $flows['interest']);
@endphp
@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">Savings Report</h4>
        <div class="text-muted small">How members are saving — {{ $rangeLabel }}@if($segmentId) · <b>{{ $segments->firstWhere('id', $segmentId)?->name }}</b> segment @endif</div>
    </div>
    <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Export</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Export PDF</a></li>
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'excel']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Export Excel (CSV)</a></li>
        </ul>
    </div>
</div>

{{-- ── Snapshots: always today / week / month / last 2 months ─────────── --}}
<div class="row g-3 mb-3">
    @foreach($snapshots as $key => $s)
    <div class="col-6 col-xl-3">
        <a href="{{ $q(['period' => $key]) }}" class="card sv-snap h-100 text-decoration-none {{ $period === $key ? 'active' : '' }}">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="sv-snap-label">{{ $s['label'] }}</span>
                    <span class="sv-snap-savers" title="Members who deposited"><i class="bi bi-people"></i> {{ number_format($s['savers']) }}</span>
                </div>
                <div class="sv-snap-net {{ $s['net'] < 0 ? 'neg' : '' }}" title="Net saved: {{ $fmt($s['net']) }}">{{ $s['net'] >= 0 ? '+' : '' }}{{ $short($s['net']) }}</div>
                <div class="sv-snap-flows">
                    <span class="in" title="Deposits {{ $fmt($s['deposits']) }}"><i class="bi bi-arrow-down-circle"></i> {{ $short($s['deposits']) }}</span>
                    <span class="out" title="Withdrawals {{ $fmt($s['withdrawals']) }}"><i class="bi bi-arrow-up-circle"></i> {{ $short($s['withdrawals']) }}</span>
                </div>
            </div>
        </a>
    </div>
    @endforeach
</div>

{{-- ── Filters ───────────────────────────────────────────────────────── --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex flex-wrap align-items-end gap-2" id="svFilter">
            <div>
                <label class="form-label small fw-semibold mb-1 d-block">Period</label>
                <div class="btn-group btn-group-sm flex-wrap" role="group">
                    @foreach($presets as $key => $label)
                        <input type="radio" class="btn-check" name="period" id="p_{{ $key }}" value="{{ $key }}" {{ $period === $key ? 'checked' : '' }} onchange="svPeriod(this)">
                        <label class="btn btn-outline-primary" for="p_{{ $key }}">{{ $label }}</label>
                    @endforeach
                </div>
            </div>
            <div class="sv-custom {{ $period === 'custom' ? '' : 'd-none' }}">
                <label class="form-label small fw-semibold mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $from->toDateString() }}">
            </div>
            <div class="sv-custom {{ $period === 'custom' ? '' : 'd-none' }}">
                <label class="form-label small fw-semibold mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $to->toDateString() }}">
            </div>
            <div>
                <label class="form-label small fw-semibold mb-1">Product</label>
                <select name="product_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All products</option>
                    @foreach($products as $p)<option value="{{ $p->id }}" {{ $productId == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="form-label small fw-semibold mb-1">Segment</label>
                <select name="segment_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All segments</option>
                    @foreach($segments as $sg)<option value="{{ $sg->id }}" {{ $segmentId == $sg->id ? 'selected' : '' }}>{{ $sg->name }}</option>@endforeach
                </select>
            </div>
            <button class="btn btn-primary btn-sm sv-custom {{ $period === 'custom' ? '' : 'd-none' }}">Apply</button>
        </form>
    </div>
</div>

{{-- ── Period headline ───────────────────────────────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-lg">
        <div class="card sv-kpi h-100" style="--k:#059669"><div class="card-body">
            <div class="sv-kpi-label"><i class="bi bi-arrow-down-circle"></i> Deposits</div>
            <div class="sv-kpi-val">{{ $fmt($flows['deposits']) }}</div>
            {!! $change($insights['deposits_change']) !!}
            <div class="sv-kpi-sub">{{ number_format($flows['deposit_count']) }} deposits · avg {{ $fmt($flows['avg_deposit']) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="card sv-kpi h-100" style="--k:#dc2626"><div class="card-body">
            <div class="sv-kpi-label"><i class="bi bi-arrow-up-circle"></i> Withdrawals</div>
            <div class="sv-kpi-val">{{ $fmt($flows['withdrawals']) }}</div>
            <span class="sv-chg text-muted">{{ $insights['withdrawal_ratio'] !== null ? $insights['withdrawal_ratio'] . '% of deposits' : '—' }}</span>
            <div class="sv-kpi-sub">{{ number_format($flows['withdrawal_count']) }} withdrawals</div>
        </div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="card sv-kpi h-100" style="--k:#2563eb"><div class="card-body">
            <div class="sv-kpi-label"><i class="bi bi-piggy-bank"></i> Net saved</div>
            <div class="sv-kpi-val {{ $flows['net'] < 0 ? 'text-danger' : '' }}">{{ $flows['net'] >= 0 ? '+' : '' }}{{ $fmt($flows['net']) }}</div>
            {!! $change($insights['net_change']) !!}
            <div class="sv-kpi-sub">incl. {{ $fmt($flows['interest']) }} interest</div>
        </div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="card sv-kpi h-100" style="--k:#7c3aed"><div class="card-body">
            <div class="sv-kpi-label"><i class="bi bi-people"></i> Active savers</div>
            <div class="sv-kpi-val">{{ number_format($flows['savers']) }} <small class="text-muted fs-6">/ {{ number_format($insights['active_accounts']) }}</small></div>
            <div class="sv-meter"><span style="width: {{ min(100, $insights['participation']) }}%"></span></div>
            <div class="sv-kpi-sub">{{ $insights['participation'] }}% of accounts deposited</div>
        </div></div>
    </div>
    <div class="col-12 col-lg">
        <div class="card sv-kpi h-100" style="--k:#0f766e"><div class="card-body">
            <div class="sv-kpi-label"><i class="bi bi-bank"></i> Total savings</div>
            <div class="sv-kpi-val">{{ $fmt($insights['total_balance']) }}</div>
            @if($insights['portfolio_growth'] !== null)
                <span class="sv-chg {{ $insights['portfolio_growth'] >= 0 ? 'up' : 'down' }}"><i class="bi bi-arrow-{{ $insights['portfolio_growth'] >= 0 ? 'up' : 'down' }}-right"></i>{{ abs($insights['portfolio_growth']) }}% this period</span>
            @endif
            <div class="sv-kpi-sub">across {{ number_format($insights['active_accounts']) }} active accounts</div>
        </div></div>
    </div>
</div>

{{-- ── Insights ──────────────────────────────────────────────────────── --}}
@php
    $notes = [];
    if ($insights['deposits_change'] !== null)
        $notes[] = ['bi-graph-' . ($insights['deposits_change'] >= 0 ? 'up' : 'down') . '-arrow', $insights['deposits_change'] >= 0 ? 'good' : 'warn',
            'Deposits are <b>' . ($insights['deposits_change'] >= 0 ? 'up' : 'down') . ' ' . abs($insights['deposits_change']) . '%</b> on the previous ' . ($from->diffInDays($to) + 1) . ' days.'];
    if ($insights['withdrawal_ratio'] !== null)
        $notes[] = ['bi-cash-coin', $insights['withdrawal_ratio'] > 80 ? 'warn' : 'good',
            'For every 100 deposited, <b>' . round($insights['withdrawal_ratio']) . '</b> was withdrawn' . ($insights['withdrawal_ratio'] > 80 ? ' — members are withdrawing most of what they save.' : '.')];
    if ($busiest && $busiest['total'] > 0)
        $notes[] = ['bi-calendar-check', 'info', '<b>' . $busiest['day'] . '</b> is the busiest saving day (' . $short($busiest['total']) . ' from ' . $busiest['count'] . ' deposits).'];
    if ($insights['regular_savers'])
        $notes[] = ['bi-arrow-repeat', 'good', '<b>' . $insights['regular_savers'] . '</b> members saved in at least 3 of the last 4 weeks.'];
    if ($insights['new_savers'])
        $notes[] = ['bi-person-plus', 'good', '<b>' . $insights['new_savers'] . '</b> members made their first deposit this period.'];
    if ($insights['dormant'])
        $notes[] = ['bi-moon', 'warn', '<b>' . $insights['dormant'] . '</b> accounts holding ' . $short($insights['dormant_balance']) . ' have had no deposit for ' . \App\Services\SavingsInsightsService::DORMANT_DAYS . '+ days — worth a follow-up call.'];
    if ($insights['overdrawn'])
        $notes[] = ['bi-exclamation-triangle', 'bad', '<b>' . $insights['overdrawn'] . '</b> accounts are overdrawn by a total of ' . $short(abs($insights['overdrawn_balance'])) . '.'];
@endphp
@if($notes)
<div class="sv-notes mb-3">
    @foreach($notes as [$icon, $tone, $text])
        <div class="sv-note {{ $tone }}"><i class="bi {{ $icon }}"></i><span>{!! $text !!}</span></div>
    @endforeach
</div>
@endif

{{-- ── Trend + saver health ──────────────────────────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-bar-chart-line me-1"></i>Deposits vs withdrawals</span>
                <span class="small text-muted">by {{ $trend['grain'] }}</span>
            </div>
            <div class="card-body"><div style="height:280px"><canvas id="svTrend"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-heart-pulse me-1"></i>Saver health</div>
            <div class="card-body py-2">
                @php
                    $health = [
                        ['growing',   $insights['growing'],   'Saved more than they withdrew'],
                        ['declining', $insights['declining'], 'Withdrew more than they saved'],
                        ['dormant',   $insights['dormant'],   'No deposit in ' . \App\Services\SavingsInsightsService::DORMANT_DAYS . '+ days'],
                        ['overdrawn', $insights['overdrawn'], 'Balance below zero'],
                    ];
                    $hTotal = max(1, $insights['active_accounts']);
                @endphp
                @foreach($health as [$key, $count, $hint])
                    @php [$label, $icon, $color] = $trendMeta[$key]; @endphp
                    <button type="button" class="sv-health w-100 text-start" onclick="svTab('{{ $key }}')" style="--h: {{ $color }}">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi {{ $icon }} me-1"></i><b>{{ $label }}</b> <span class="text-muted small">· {{ $hint }}</span></span>
                            <span class="fw-bold">{{ number_format($count) }}</span>
                        </div>
                        <div class="sv-meter"><span style="width: {{ round($count / $hTotal * 100, 1) }}%"></span></div>
                    </button>
                @endforeach
                <div class="d-flex gap-2 mt-2">
                    <div class="sv-mini flex-fill"><div class="text-muted small">Regular savers</div><div class="fw-bold">{{ number_format($insights['regular_savers']) }}</div></div>
                    <div class="sv-mini flex-fill"><div class="text-muted small">New savers</div><div class="fw-bold">{{ number_format($insights['new_savers']) }}</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Leaders ───────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-trophy me-1 text-warning"></i>Top savers <span class="text-muted small fw-normal">· highest net saved</span></div>
            <div class="card-body p-0">
                @forelse($insights['top_savers'] as $i => $r)
                    <a href="{{ route('savings.show', $r->account) }}" class="sv-leader">
                        <span class="sv-rank {{ $i < 3 ? 'top' : '' }}">{{ $i + 1 }}</span>
                        <span class="flex-fill text-truncate"><b>{{ $r->account->client?->name }}</b><small class="d-block text-muted">{{ $r->deposit_count }} deposits · balance {{ $short($r->balance) }}</small></span>
                        <span class="text-success fw-bold">+{{ $fmt($r->net) }}</span>
                    </a>
                @empty
                    <div class="text-muted small text-center py-4">No net savings in this period.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-box-arrow-up-right me-1 text-danger"></i>Biggest withdrawals</div>
            <div class="card-body p-0">
                @forelse($insights['top_withdrawals'] as $i => $r)
                    <a href="{{ route('savings.show', $r->account) }}" class="sv-leader">
                        <span class="sv-rank">{{ $i + 1 }}</span>
                        <span class="flex-fill text-truncate"><b>{{ $r->account->client?->name }}</b><small class="d-block text-muted">deposited {{ $short($r->deposits) }} · balance {{ $short($r->balance) }}</small></span>
                        <span class="text-danger fw-bold">−{{ $fmt($r->withdrawals) }}</span>
                    </a>
                @empty
                    <div class="text-muted small text-center py-4">No withdrawals in this period.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ── Distribution, deposit mix, weekdays ───────────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-bar-chart-steps me-1"></i>Balance distribution</div>
            <div class="card-body">
                @php $bMax = max(1, collect($insights['bands'])->max('count')); @endphp
                @foreach($insights['bands'] as $b)
                    <div class="sv-band {{ $b['label'] === 'Overdrawn' ? 'neg' : '' }}">
                        <span class="sv-band-label">{{ $b['label'] }}</span>
                        <span class="sv-band-bar"><span style="width: {{ round($b['count'] / $bMax * 100, 1) }}%"></span></span>
                        <span class="sv-band-n">{{ number_format($b['count']) }}</span>
                        <span class="sv-band-t">{{ $short($b['total']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-pie-chart me-1"></i>Where deposits came from &amp; when</div>
            <div class="card-body">
                <div class="sv-mix mb-2">
                    <span style="width: {{ $flows['member_deposits'] / $mixTotal * 100 }}%; background:#059669" title="Member deposits"></span>
                    <span style="width: {{ $flows['payroll'] / $mixTotal * 100 }}%; background:#2563eb" title="Salary & staff savings"></span>
                    <span style="width: {{ $flows['interest'] / $mixTotal * 100 }}%; background:#f59e0b" title="Interest"></span>
                </div>
                <div class="d-flex flex-wrap gap-3 small mb-3">
                    <span><i class="bi bi-square-fill" style="color:#059669"></i> Member deposits <b>{{ $fmt($flows['member_deposits']) }}</b></span>
                    <span><i class="bi bi-square-fill" style="color:#2563eb"></i> Salary &amp; staff savings <b>{{ $fmt($flows['payroll']) }}</b></span>
                    <span><i class="bi bi-square-fill" style="color:#f59e0b"></i> Interest <b>{{ $fmt($flows['interest']) }}</b></span>
                </div>
                <div class="small fw-semibold text-muted mb-1">Member deposits by day of week</div>
                @php $wMax = max(1, collect($weekdays)->max('total')); @endphp
                <div class="sv-week">
                    @foreach($weekdays as $w)
                        <div class="sv-week-col {{ $busiest && $w['day'] === $busiest['day'] && $w['total'] > 0 ? 'best' : '' }}" title="{{ $w['day'] }}: {{ $fmt($w['total']) }} ({{ $w['count'] }} deposits)">
                            <div class="sv-week-bar"><span style="height: {{ max(2, round($w['total'] / $wMax * 100)) }}%"></span></div>
                            <div class="sv-week-amt">{{ $w['total'] > 0 ? $short($w['total']) : '' }}</div>
                            <div class="sv-week-day">{{ $w['day'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Accounts ──────────────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="nav nav-pills nav-sm sv-tabs" id="svTabs">
            @php $tabs = ['active' => 'Active this period', 'all' => 'All', 'growing' => 'Growing', 'declining' => 'Declining', 'dormant' => 'Dormant', 'overdrawn' => 'Overdrawn', 'new' => 'New']; @endphp
            @foreach($tabs as $key => $label)
                <button type="button" class="nav-link {{ $key === 'active' ? 'active' : '' }}" data-tab="{{ $key }}" onclick="svTab('{{ $key }}')">{{ $label }} <span class="sv-count" data-count="{{ $key }}"></span></button>
            @endforeach
        </div>
        <input type="search" class="form-control form-control-sm" style="max-width:220px" placeholder="Search client or account…" oninput="svSearch(this.value)">
    </div>
    <table class="table table-sm table-hover mb-0 align-middle sv-table" id="svTable">
        <colgroup><col style="width:24%"><col><col><col><col><col><col style="width:13%"></colgroup>
        <thead><tr>
            <th class="ps-3 sv-sort" data-key="name">Client</th>
            <th class="text-end sv-sort" data-key="opening">Start balance</th>
            <th class="text-end sv-sort" data-key="deposits">Deposits</th>
            <th class="text-end sv-sort" data-key="withdrawals">Withdrawals</th>
            <th class="text-end sv-sort" data-key="net">Net saved</th>
            <th class="text-end sv-sort" data-key="balance">Balance</th>
            <th class="pe-3 sv-sort" data-key="last">Last deposit</th>
        </tr></thead>
        <tbody>
        @foreach($rows->sortByDesc('net') as $r)
            @php [$tLabel, $tIcon, $tColor] = $trendMeta[$r->trend]; @endphp
            <tr data-trend="{{ $r->trend }}" data-new="{{ $r->is_new ? 1 : 0 }}" data-active="{{ ($r->deposits + $r->withdrawals) > 0 ? 1 : 0 }}"
                data-search="{{ strtolower(($r->account->client?->name ?? '') . ' ' . $r->account->account_number) }}"
                data-name="{{ strtolower($r->account->client?->name ?? '') }}" data-opening="{{ $r->opening }}" data-deposits="{{ $r->deposits }}"
                data-withdrawals="{{ $r->withdrawals }}" data-net="{{ $r->net }}" data-balance="{{ $r->balance }}" data-last="{{ $r->last_deposit?->format('Ymd') ?? 0 }}">
                <td class="ps-3" data-label="Client">
                    <div class="sv-name" title="{{ $r->account->client?->name }}">{{ $r->account->client?->name }}</div>
                    <a href="{{ route('savings.show', $r->account) }}" class="sv-acct">{{ $r->account->account_number }}</a>
                    <span class="sv-tag" style="--t: {{ $tColor }}"><i class="bi {{ $tIcon }}"></i> {{ $tLabel }}</span>
                    @if($r->is_new)<span class="sv-tag" style="--t:#7c3aed"><i class="bi bi-stars"></i> New</span>@endif
                </td>
                <td class="text-end num text-muted" data-label="Start balance">{{ $fmt($r->opening) }}</td>
                <td class="text-end num text-success" data-label="Deposits">{{ $r->deposits ? $fmt($r->deposits) : '—' }}</td>
                <td class="text-end num text-danger" data-label="Withdrawals">{{ $r->withdrawals ? $fmt($r->withdrawals) : '—' }}</td>
                <td class="text-end num fw-semibold {{ $r->net > 0 ? 'text-success' : ($r->net < 0 ? 'text-danger' : 'text-muted') }}" data-label="Net saved">{{ $r->net > 0 ? '+' : '' }}{{ $fmt($r->net) }}</td>
                <td class="text-end num fw-semibold {{ $r->balance < 0 ? 'text-danger' : '' }}" data-label="Balance">{{ $fmt($r->balance) }}</td>
                <td class="pe-3 small" data-label="Last deposit">
                    @if($r->last_deposit)
                        {{ $r->last_deposit->format('d M Y') }}
                        <div class="text-muted" style="font-size:.7rem">{{ $r->last_deposit->diffForHumans(now()->startOfDay(), ['parts' => 1, 'syntax' => \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW]) }}</div>
                    @else
                        <span class="text-muted">Never</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
        <tfoot class="table-light fw-bold">
            <tr>
                <td class="ps-3">Totals <span class="text-muted fw-normal small" id="svShown"></span></td>
                <td class="text-end num" data-label="Start balance" id="svT_opening"></td>
                <td class="text-end num text-success" data-label="Deposits" id="svT_deposits"></td>
                <td class="text-end num text-danger" data-label="Withdrawals" id="svT_withdrawals"></td>
                <td class="text-end num" data-label="Net saved" id="svT_net"></td>
                <td class="text-end num" data-label="Balance" id="svT_balance"></td>
                <td class="pe-3"></td>
            </tr>
        </tfoot>
    </table>
    <div class="card-footer text-center d-none" id="svMoreWrap">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="svLimit = Infinity; svRender()">Show all <span id="svMoreN"></span> accounts</button>
    </div>
</div>
@endsection

@push('styles')
<style>
    .sv-snap { border: 1px solid #e5e7eb; transition: transform .12s, box-shadow .12s; color: inherit; }
    .sv-snap:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(15,36,68,.08); }
    .sv-snap.active { border-color: var(--bs-primary); box-shadow: 0 0 0 2px rgba(37,99,235,.15); }
    .sv-snap-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; font-weight: 600; }
    .sv-snap-savers { font-size: .72rem; color: #6b7280; }
    .sv-snap-net { font-size: 1.6rem; font-weight: 700; color: #059669; line-height: 1.2; margin: .2rem 0; font-variant-numeric: tabular-nums; }
    .sv-snap-net.neg { color: #dc2626; }
    .sv-snap-flows { display: flex; gap: .8rem; font-size: .78rem; font-variant-numeric: tabular-nums; }
    .sv-snap-flows .in { color: #059669; } .sv-snap-flows .out { color: #dc2626; }

    .sv-kpi { border-top: 3px solid var(--k); }
    .sv-kpi-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--k); font-weight: 700; }
    .sv-kpi-val { font-size: 1.35rem; font-weight: 700; font-variant-numeric: tabular-nums; margin: .15rem 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sv-kpi-sub { font-size: .74rem; color: #6b7280; margin-top: .2rem; }
    .sv-chg { font-size: .74rem; font-weight: 600; }
    .sv-chg.up { color: #059669; } .sv-chg.down { color: #dc2626; }
    .sv-meter { height: 5px; background: #eef0f3; border-radius: 3px; overflow: hidden; margin-top: .35rem; }
    .sv-meter span { display: block; height: 100%; background: var(--h, var(--k, #2563eb)); border-radius: 3px; }

    .sv-notes { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: .6rem; }
    .sv-note { display: flex; gap: .6rem; align-items: flex-start; padding: .6rem .8rem; border-radius: .6rem; font-size: .84rem; background: #f8fafc; border: 1px solid #e5e7eb; }
    .sv-note i { font-size: 1.05rem; margin-top: 1px; }
    .sv-note.good { background: #ecfdf5; border-color: #a7f3d0; } .sv-note.good i { color: #059669; }
    .sv-note.warn { background: #fffbeb; border-color: #fde68a; } .sv-note.warn i { color: #d97706; }
    .sv-note.bad  { background: #fef2f2; border-color: #fecaca; } .sv-note.bad i  { color: #dc2626; }
    .sv-note.info { background: #eff6ff; border-color: #bfdbfe; } .sv-note.info i { color: #2563eb; }

    .sv-health { background: none; border: 0; border-radius: .5rem; padding: .45rem .5rem; font-size: .84rem; }
    .sv-health:hover { background: #f8fafc; }
    .sv-health i { color: var(--h); }
    .sv-mini { background: #f8fafc; border-radius: .5rem; padding: .4rem .6rem; }

    .sv-leader { display: flex; align-items: center; gap: .7rem; padding: .5rem 1rem; border-bottom: 1px solid #f1f3f5; color: inherit; text-decoration: none; font-size: .86rem; }
    .sv-leader:hover { background: #f8fafc; }
    .sv-leader:last-child { border-bottom: 0; }
    .sv-rank { width: 1.6rem; height: 1.6rem; flex: none; border-radius: 50%; background: #f1f5f9; color: #475569; display: grid; place-items: center; font-size: .75rem; font-weight: 700; }
    .sv-rank.top { background: #fef3c7; color: #b45309; }

    .sv-band { display: grid; grid-template-columns: 6.5rem 1fr 3rem 4.5rem; gap: .6rem; align-items: center; font-size: .82rem; margin-bottom: .45rem; }
    .sv-band-bar { height: 10px; background: #eef0f3; border-radius: 5px; overflow: hidden; }
    .sv-band-bar span { display: block; height: 100%; background: linear-gradient(90deg, #60a5fa, #2563eb); border-radius: 5px; }
    .sv-band.neg .sv-band-bar span { background: #f87171; }
    .sv-band-n { text-align: right; font-weight: 700; } .sv-band-t { text-align: right; color: #6b7280; font-variant-numeric: tabular-nums; }

    .sv-mix { display: flex; height: 14px; border-radius: 7px; overflow: hidden; background: #eef0f3; }
    .sv-week { display: grid; grid-template-columns: repeat(7, 1fr); gap: .4rem; align-items: end; }
    .sv-week-bar { height: 90px; display: flex; align-items: flex-end; background: #f8fafc; border-radius: .4rem; overflow: hidden; }
    .sv-week-bar span { display: block; width: 100%; background: #93c5fd; border-radius: .4rem .4rem 0 0; }
    .sv-week-col.best .sv-week-bar span { background: #2563eb; }
    .sv-week-amt { font-size: .66rem; text-align: center; color: #6b7280; margin-top: 2px; min-height: 1em; }
    .sv-week-day { font-size: .72rem; text-align: center; font-weight: 600; }

    .sv-tabs .nav-link { padding: .25rem .6rem; font-size: .8rem; }
    .sv-count { font-size: .68rem; opacity: .75; }
    .sv-table { table-layout: fixed; width: 100%; font-size: .84rem; }
    .sv-table thead th { font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; color: #6b7280; cursor: pointer; user-select: none; vertical-align: bottom; }
    .sv-table thead th.asc::after  { content: ' ▲'; font-size: .6rem; }
    .sv-table thead th.desc::after { content: ' ▼'; font-size: .6rem; }
    .sv-table td { overflow: hidden; }
    .sv-table .num { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sv-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sv-acct { font-family: var(--bs-font-monospace); font-size: .7rem; color: #6b7280; text-decoration: none; margin-right: .3rem; }
    .sv-acct:hover { color: var(--bs-primary); text-decoration: underline; }
    .sv-tag { display: inline-block; padding: 0 .4rem; border-radius: 999px; font-size: .64rem; font-weight: 700; line-height: 1.55; color: var(--t);
              background: color-mix(in srgb, var(--t) 11%, transparent); border: 1px solid color-mix(in srgb, var(--t) 30%, transparent); }

    @media (max-width: 1280px) {
        .sv-table { font-size: .78rem; }
        .sv-table td, .sv-table th { padding-left: .3rem; padding-right: .3rem; }
    }
    @media (max-width: 767.98px) {
        .sv-table colgroup, .sv-table thead { display: none; }
        .sv-table, .sv-table tbody, .sv-table tfoot, .sv-table tr, .sv-table td { display: block; width: 100%; }
        .sv-table tr { padding: .6rem .9rem; border-bottom: 1px solid #eef0f3; }
        .sv-table td { border: 0; padding: .1rem 0 !important; text-align: right !important; display: flex; justify-content: space-between; align-items: center; gap: .75rem; }
        .sv-table td::before { content: attr(data-label); font-size: .7rem; text-transform: uppercase; color: #9ca3af; font-weight: 600; }
        .sv-table td[data-label="Client"], .sv-table tfoot td:first-child { display: block; text-align: left !important; margin-bottom: .25rem; }
        .sv-table td[data-label="Client"]::before, .sv-table tfoot td:first-child::before { content: none; }
        .sv-table tr[style*="display: none"] { display: none !important; }
        .sv-band { grid-template-columns: 5.5rem 1fr 2.5rem 4rem; }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Period filter ───────────────────────────────────────────────
function svPeriod(el) {
    const custom = el.value === 'custom';
    document.querySelectorAll('.sv-custom').forEach(x => x.classList.toggle('d-none', !custom));
    if (!custom) {
        document.querySelectorAll('#svFilter [name=from], #svFilter [name=to]').forEach(i => i.disabled = true);
        el.form.submit();
    }
}

// ── Trend chart ─────────────────────────────────────────────────
(function () {
    const points = @json($trend['points']);
    const el = document.getElementById('svTrend');
    if (!el || typeof Chart === 'undefined') return;
    const short = v => { const a = Math.abs(v), s = v < 0 ? '-' : ''; return a >= 1e6 ? s + (a / 1e6).toFixed(1) + 'M' : a >= 1e3 ? s + Math.round(a / 1e3) + 'K' : s + a; };
    new Chart(el, {
        data: {
            labels: points.map(p => p.label),
            datasets: [
                { type: 'bar', label: 'Deposits', data: points.map(p => p.deposits), backgroundColor: 'rgba(16,185,129,.75)', borderRadius: 4, order: 2 },
                { type: 'bar', label: 'Withdrawals', data: points.map(p => -p.withdrawals), backgroundColor: 'rgba(239,68,68,.7)', borderRadius: 4, order: 2 },
                { type: 'line', label: 'Net', data: points.map(p => p.deposits - p.withdrawals), borderColor: '#2563eb', backgroundColor: '#2563eb', tension: .3, pointRadius: points.length > 40 ? 0 : 3, order: 1 },
            ],
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { stacked: true, grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
                y: { stacked: false, ticks: { callback: short }, grid: { color: '#f1f3f5' } },
            },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } },
                tooltip: { callbacks: { label: c => ' ' + c.dataset.label + ': ' + Math.abs(c.raw).toLocaleString() } },
            },
        },
    });
})();

// ── Accounts table: tabs, search, sort, totals ──────────────────
const svRows = Array.from(document.querySelectorAll('#svTable tbody tr'));
const svFmt = n => n.toLocaleString('en-US', { minimumFractionDigits: {{ $dp }}, maximumFractionDigits: {{ $dp }} });
let svTabKey = 'active', svQuery = '', svLimit = 100, svSortKey = null, svSortDir = -1;

function svMatchTab(r, key) {
    if (key === 'all') return true;
    if (key === 'active') return r.dataset.active === '1';
    if (key === 'new') return r.dataset.new === '1';
    return r.dataset.trend === key;
}
function svTab(key) {
    svTabKey = key; svLimit = 100;
    document.querySelectorAll('#svTabs .nav-link').forEach(b => b.classList.toggle('active', b.dataset.tab === key));
    svRender();
    if (event && event.currentTarget && event.currentTarget.classList.contains('sv-health')) document.getElementById('svTable').scrollIntoView({ behavior: 'smooth' });
}
function svSearch(v) { svQuery = v.trim().toLowerCase(); svLimit = 100; svRender(); }

document.querySelectorAll('.sv-sort').forEach(th => th.addEventListener('click', () => {
    const key = th.dataset.key;
    svSortDir = svSortKey === key ? -svSortDir : (key === 'name' ? 1 : -1);
    svSortKey = key;
    document.querySelectorAll('.sv-sort').forEach(x => x.classList.remove('asc', 'desc'));
    th.classList.add(svSortDir === 1 ? 'asc' : 'desc');
    const tbody = document.querySelector('#svTable tbody');
    svRows.sort((a, b) => {
        const x = a.dataset[key], y = b.dataset[key];
        return (key === 'name' ? x.localeCompare(y) : (parseFloat(x) - parseFloat(y))) * svSortDir;
    }).forEach(r => tbody.appendChild(r));
    svRender();
}));

function svRender() {
    const totals = { opening: 0, deposits: 0, withdrawals: 0, net: 0, balance: 0 };
    let matched = 0, shown = 0;
    svRows.forEach(r => {
        const ok = svMatchTab(r, svTabKey) && (!svQuery || r.dataset.search.includes(svQuery));
        if (ok) {
            matched++;
            for (const k in totals) totals[k] += parseFloat(r.dataset[k]) || 0;
        }
        const visible = ok && shown < svLimit;
        if (visible) shown++;
        r.style.display = visible ? '' : 'none';
    });
    for (const k in totals) document.getElementById('svT_' + k).textContent = svFmt(totals[k]);
    document.getElementById('svShown').textContent = '· ' + matched.toLocaleString() + ' account' + (matched === 1 ? '' : 's');
    document.getElementById('svMoreWrap').classList.toggle('d-none', matched <= shown);
    document.getElementById('svMoreN').textContent = matched.toLocaleString();
}

// Tab counts
document.querySelectorAll('.sv-count').forEach(el => {
    el.textContent = svRows.filter(r => svMatchTab(r, el.dataset.count)).length.toLocaleString();
});
svRender();
</script>
@endpush
