@extends('layouts.app')
@section('title', 'Loan Aging')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Loan Aging</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Loan Aging Report</h4>
    <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Export</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Export PDF</a></li>
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'excel']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Export Excel (CSV)</a></li>
        </ul>
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Due Date From</label>
                <input type="date" name="from" class="form-control @error('from') is-invalid @enderror" value="{{ $from }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Due Date To</label>
                <input type="date" name="to" class="form-control @error('to') is-invalid @enderror" value="{{ $to }}">
                @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-auto">
                <button class="btn btn-primary">Run Report</button>
                <a href="{{ route('reports.loan-aging') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
            <div class="col-12 form-text mt-1">
                Shows loans with unpaid installments falling due in this range (leave "From" blank for all earlier installments).
                Days overdue and amounts received are as of today.
            </div>
        </form>
    </div>
</div>

@php
$accents = ['1-30'=>'#059669','31-60'=>'#0891b2','61-90'=>'#d97706','91-180'=>'#ea580c','181+'=>'#dc2626'];
$labels  = ['1-30'=>'1-30 Days','31-60'=>'31-60 Days','61-90'=>'61-90 Days','91-180'=>'91-180 Days','181+'=>'181+ Days'];
$all = $totals['all'];
@endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-md"><div class="card h-100"><div class="card-body py-2"><div class="small text-muted">Loans in arrears</div><div class="fw-bold fs-5">{{ $all['loans'] }}</div></div></div></div>
    <div class="col-6 col-md"><div class="card h-100"><div class="card-body py-2"><div class="small text-muted">Expected</div><div class="fw-bold fs-5">{{ number_format($all['expected'], $dp) }}</div></div></div></div>
    <div class="col-6 col-md"><div class="card h-100"><div class="card-body py-2"><div class="small text-muted">Received</div><div class="fw-bold fs-5 text-success">{{ number_format($all['received'], $dp) }}</div></div></div></div>
    <div class="col-6 col-md"><div class="card h-100 border-danger"><div class="card-body py-2"><div class="small text-muted">Balance (arrears)</div><div class="fw-bold fs-5 text-danger">{{ number_format($all['balance'], $dp) }}</div></div></div></div>
    <div class="col-12 col-md"><div class="card h-100"><div class="card-body py-2"><div class="small text-muted">Total outstanding</div><div class="fw-bold fs-5">{{ number_format($all['total_outstanding'], $dp) }}</div></div></div></div>
</div>

@foreach($buckets as $bucket => $items)
@php $t = $totals[$bucket]; $accent = $accents[$bucket]; @endphp
<div class="card mb-3 aging-card" style="--accent: {{ $accent }}">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold aging-title"><i class="bi bi-clock-history me-1"></i>{{ $labels[$bucket] }} Overdue</span>
        <span class="aging-pill">{{ count($items) }} {{ \Illuminate\Support\Str::plural('loan', count($items)) }} &middot; Balance {{ number_format($t['balance'], $dp) }}</span>
    </div>
    @if(count($items) > 0)
    <table class="table table-sm table-hover mb-0 align-middle aging-table">
        <colgroup>
            <col class="c-client"><col><col class="c-due"><col><col class="c-recv"><col><col>
        </colgroup>
        <thead><tr>
            <th class="ps-3">Client</th>
            <th class="text-end">Disbursed</th>
            <th>Due Date</th>
            <th class="text-end">Expected</th>
            <th class="text-end">Received</th>
            <th class="text-end">Balance</th>
            <th class="text-end pe-3">Total Outstanding</th>
        </tr></thead>
        <tbody>
        @foreach($items as $item)
            @php $pct = $item['expected'] > 0 ? min(100, round($item['received'] / $item['expected'] * 100)) : 0; @endphp
            <tr>
                <td class="ps-3" data-label="Client">
                    <div class="aging-name" title="{{ $item['loan']->client->name }}">{{ $item['loan']->client->name }}</div>
                    <a href="{{ route('loans.show', $item['loan']) }}" class="aging-loan">{{ $item['loan']->loan_number }}</a>
                </td>
                <td class="text-end num" data-label="Disbursed">{{ number_format($item['disbursed'], $dp) }}</td>
                <td data-label="Due Date">
                    <div class="num">{{ $item['due_date']->format('d M Y') }}</div>
                    <span class="aging-days">{{ $item['days'] }} {{ \Illuminate\Support\Str::plural('day', $item['days']) }}</span>
                    @if($item['installments'] > 1)<span class="aging-inst" title="{{ $item['installments'] }} installments due in this range">&times;{{ $item['installments'] }}</span>@endif
                </td>
                <td class="text-end num" data-label="Expected">{{ number_format($item['expected'], $dp) }}</td>
                <td class="text-end num" data-label="Received">
                    <span class="text-success">{{ number_format($item['received'], $dp) }}</span>
                    <div class="aging-bar" title="{{ $pct }}% of expected received"><span style="width: {{ $pct }}%"></span></div>
                </td>
                <td class="text-end num fw-semibold text-danger" data-label="Balance">{{ number_format($item['balance'], $dp) }}</td>
                <td class="text-end num pe-3" data-label="Total Outstanding">{{ number_format($item['total_outstanding'], $dp) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot class="table-light fw-bold">
            <tr>
                <td class="ps-3">Subtotal</td>
                <td class="text-end num" data-label="Disbursed">{{ number_format($t['disbursed'], $dp) }}</td>
                <td class="d-none d-md-table-cell"></td>
                <td class="text-end num" data-label="Expected">{{ number_format($t['expected'], $dp) }}</td>
                <td class="text-end num text-success" data-label="Received">{{ number_format($t['received'], $dp) }}</td>
                <td class="text-end num text-danger" data-label="Balance">{{ number_format($t['balance'], $dp) }}</td>
                <td class="text-end num pe-3" data-label="Total Outstanding">{{ number_format($t['total_outstanding'], $dp) }}</td>
            </tr>
        </tfoot>
    </table>
    @else
        <div class="card-body text-muted text-center small">No overdue loans in this bracket.</div>
    @endif
</div>
@endforeach
@endsection

@push('styles')
<style>
    .aging-card { border-top: 3px solid var(--accent); overflow: hidden; }
    .aging-title { color: var(--accent); }
    .aging-pill { background: var(--accent); color: #fff; border-radius: 999px; padding: .2rem .7rem; font-size: .75rem; font-weight: 600; }

    /* Fixed layout: columns share the card width, so nothing scrolls sideways */
    .aging-table { table-layout: fixed; width: 100%; font-size: .84rem; }
    .aging-table col.c-client { width: 20%; }
    .aging-table col.c-due    { width: 13%; }
    .aging-table col.c-recv   { width: 13%; }
    .aging-table thead th { font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; color: #6b7280; line-height: 1.25; vertical-align: bottom; overflow-wrap: normal; word-break: keep-all; }
    @media (max-width: 1280px) {
        .aging-table { font-size: .78rem; }
        .aging-table thead th { font-size: .62rem; letter-spacing: 0; }
        .aging-table td.pe-3, .aging-table th.pe-3 { padding-right: .6rem !important; }
        .aging-table col.c-client { width: 17%; }
        .aging-table col.c-due    { width: 12%; }
        .aging-table col.c-recv   { width: 12%; }
        .aging-table td, .aging-table th { padding-left: .3rem; padding-right: .3rem; }
        .aging-table tfoot td { font-size: .74rem; }
    }
    .aging-table td { overflow: hidden; }
    .aging-table .num { font-variant-numeric: tabular-nums; white-space: nowrap; }

    .aging-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .aging-loan { font-family: var(--bs-font-monospace); font-size: .7rem; color: #6b7280; text-decoration: none; }
    .aging-loan:hover { color: var(--bs-primary); text-decoration: underline; }

    .aging-days { display: inline-block; margin-top: 2px; padding: 0 .45rem; border-radius: 999px; font-size: .66rem; font-weight: 700; line-height: 1.5;
                  color: var(--accent); background: color-mix(in srgb, var(--accent) 12%, transparent); border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent); }
    .aging-inst { display: inline-block; margin-left: 2px; padding: 0 .35rem; border-radius: 999px; font-size: .62rem; font-weight: 600; line-height: 1.5; color: #6b7280; background: #f3f4f6; }

    .aging-bar { height: 3px; background: #e5e7eb; border-radius: 2px; margin-top: 3px; margin-left: auto; width: 70%; overflow: hidden; }
    .aging-bar span { display: block; height: 100%; background: #10b981; }

    /* Phones: each loan becomes a compact card with labelled values */
    @media (max-width: 767.98px) {
        .aging-table colgroup, .aging-table thead { display: none; }
        .aging-table, .aging-table tbody, .aging-table tfoot, .aging-table tr, .aging-table td { display: block; width: 100%; }
        .aging-table tr { padding: .6rem .9rem; border-bottom: 1px solid #eef0f3; }
        .aging-table td { border: 0; padding: .1rem 0 !important; text-align: right !important; display: flex; justify-content: space-between; align-items: center; gap: .75rem; }
        .aging-table td::before { content: attr(data-label); font-size: .7rem; text-transform: uppercase; color: #9ca3af; font-weight: 600; text-align: left; }
        .aging-table td[data-label="Client"] { display: block; text-align: left !important; margin-bottom: .25rem; }
        .aging-table td[data-label="Client"]::before, .aging-table tfoot td:first-child::before { content: none; }
        .aging-table td[data-label="Due Date"] > div { margin-left: auto; }
        .aging-table td[data-label="Received"] > span { margin-left: auto; }
        .aging-table td[data-label="Received"] .aging-bar { margin-left: 0; }
        .aging-table tfoot td:first-child { display: block; text-align: left !important; }
        .aging-bar { width: 60px; }
    }
</style>
@endpush
