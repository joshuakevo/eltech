@extends('layouts.app')
@section('title', 'Import penalties (31 Jul)')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loan-reports.penalties') }}">Loan Penalties</a></li>
    <li class="breadcrumb-item active">Import penalties</li>
@endsection
@section('content')
<div class="page-header">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-upload me-1 text-primary"></i>Import penalties owed at 31 July 2026</h4>
        <div class="text-muted small">Penalties members owed in the old system at the transfer. No journal is posted — when a member pays, it goes to penalty income. It is collected before newer penalties, after interest and principal.</div>
    </div>
    <a href="{{ route('loans.import-penalties.template') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Template</a>
</div>

@if(!$preview)
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('loans.import-penalties.preview') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-6">
                <label class="form-label small fw-semibold">CSV file</label>
                <input type="file" name="file" accept=".csv,.txt" class="form-control" required>
                <div class="form-text">Columns: <code>loan_number</code> and/or <code>client_number</code>, <code>penalty</code> (optional <code>name</code>). A member with more than one running loan needs the loan number.</div>
            </div>
            <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-eye me-1"></i>Preview</button></div>
        </form>
    </div>
</div>
@else
@php $rows = collect($preview['rows']); $ok = $rows->whereNull('issue'); @endphp
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span>{{ $preview['file'] }} — <strong>{{ $ok->count() }}</strong> to import ({{ number_format($ok->sum('amount'), $dp) }}) · {{ $rows->count() - $ok->count() }} with issues</span>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('loans.import-penalties.cancel') }}">@csrf<button class="btn btn-sm btn-outline-secondary">Start again</button></form>
            @if($ok->isNotEmpty())
            <form method="POST" action="{{ route('loans.import-penalties.confirm') }}" onsubmit="return confirm('Bring forward {{ number_format($ok->sum('amount'), $dp) }} of penalties on {{ $ok->count() }} loans?')">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-check2-circle me-1"></i>Import {{ $ok->count() }}</button></form>
            @endif
        </div>
    </div>
    <div class="table-responsive" style="max-height:65vh">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light" style="position:sticky;top:0"><tr><th class="ps-3">Line</th><th>Loan</th><th>Member</th><th>Status</th><th class="text-end">Penalty</th><th class="pe-3">Check</th></tr></thead>
            <tbody class="small">
            @foreach($rows as $r)
                <tr class="{{ $r['issue'] ? 'table-warning' : '' }}">
                    <td class="ps-3">{{ $r['line'] }}</td>
                    <td class="font-monospace">{{ $r['loan_number'] }}</td>
                    <td>{{ $r['client'] }} <span class="text-muted">{{ $r['client_number'] }}</span></td>
                    <td>{{ $r['status'] }}</td>
                    <td class="text-end">{{ number_format($r['amount'], $dp) }}</td>
                    <td class="pe-3">{!! $r['issue'] ? '<span class="text-danger">' . e($r['issue']) . '</span>' : '<span class="text-success">OK</span>' !!}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
