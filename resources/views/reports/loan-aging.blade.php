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
$colors = ['1-30'=>'success','31-60'=>'info','61-90'=>'warning','91-180'=>'orange','181+'=>'danger'];
$labels = ['1-30'=>'1-30 Days','31-60'=>'31-60 Days','61-90'=>'61-90 Days','91-180'=>'91-180 Days','181+'=>'181+ Days'];
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
@php $t = $totals[$bucket]; @endphp
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold text-{{ $colors[$bucket] ?? 'secondary' }}">{{ $labels[$bucket] }} Overdue</span>
        <span class="badge bg-{{ $colors[$bucket] ?? 'secondary' }}">{{ count($items) }} {{ \Illuminate\Support\Str::plural('loan', count($items)) }} | Balance {{ number_format($t['balance'], $dp) }}</span>
    </div>
    @if(count($items) > 0)
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle" style="min-width:1100px">
            <thead><tr>
                <th class="ps-3">Loan #</th>
                <th>Client</th>
                <th class="text-end">Amount Disbursed</th>
                <th>Due Date</th>
                <th class="text-end">Expected Installment</th>
                <th class="text-end">Amount Received</th>
                <th class="text-end">Balance</th>
                <th class="text-end">Days Overdue</th>
                <th class="text-end pe-3">Total Outstanding</th>
            </tr></thead>
            <tbody>
            @foreach($items as $item)
                <tr>
                    <td class="ps-3 font-monospace"><a href="{{ route('loans.show', $item['loan']) }}" class="text-decoration-none">{{ $item['loan']->loan_number }}</a></td>
                    <td>{{ $item['loan']->client->name }}</td>
                    <td class="text-end">{{ number_format($item['disbursed'], $dp) }}</td>
                    <td>{{ $item['due_date']->format('d M Y') }}
                        @if($item['installments'] > 1)<div class="text-muted" style="font-size:.72rem">{{ $item['installments'] }} installments in range</div>@endif
                    </td>
                    <td class="text-end">{{ number_format($item['expected'], $dp) }}</td>
                    <td class="text-end text-success">{{ number_format($item['received'], $dp) }}</td>
                    <td class="text-end text-danger fw-semibold">{{ number_format($item['balance'], $dp) }}</td>
                    <td class="text-end text-{{ $colors[$bucket] ?? 'secondary' }} fw-semibold">{{ $item['days'] }}</td>
                    <td class="text-end pe-3">{{ number_format($item['total_outstanding'], $dp) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td class="ps-3" colspan="2">Subtotal</td>
                    <td class="text-end">{{ number_format($t['disbursed'], $dp) }}</td>
                    <td></td>
                    <td class="text-end">{{ number_format($t['expected'], $dp) }}</td>
                    <td class="text-end text-success">{{ number_format($t['received'], $dp) }}</td>
                    <td class="text-end text-danger">{{ number_format($t['balance'], $dp) }}</td>
                    <td></td>
                    <td class="text-end pe-3">{{ number_format($t['total_outstanding'], $dp) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @else
        <div class="card-body text-muted text-center small">No overdue loans in this bracket.</div>
    @endif
</div>
@endforeach
@endsection
