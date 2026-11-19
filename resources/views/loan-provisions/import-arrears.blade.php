@extends('layouts.app')
@section('title', 'Old Provision Report')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loan-provisions.index', ['type' => 'specific']) }}">Specific Provisions</a></li>
    <li class="breadcrumb-item active">Old provision report</li>
@endsection
@section('content')
@php $f = fn ($v) => $v === null ? '—' : number_format($v); @endphp
<h4 class="fw-bold mb-3">Old system provision report — compare &amp; bring arrears forward</h4>

@if(!$preview)
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <p class="small text-muted">Upload the old system's provision report <b>PDF</b> as it comes out of the old system, or a CSV with the columns <code>member_id, loan_bal, arrears, days</code>
                    (optional <code>loan_number</code>; the old headings MemberID, LoanBal, Arrears, TtlDays also work).
                    The preview compares every member with the new system on that date. Confirming saves each loan's old arrears start date
                    (date − days) so the specific provision counts its days from there until those arrears are paid.</p>
                <form method="POST" action="{{ route('loan-provisions.import-arrears.preview') }}" enctype="multipart/form-data" class="row g-2">
                    @csrf
                    <div class="col-md-5"><label class="form-label small fw-semibold">Report date</label>
                        <input type="date" name="as_at_date" class="form-control" value="{{ \App\Services\LoanInterestService::TRANSFER_DATE }}" max="{{ today()->toDateString() }}" required></div>
                    <div class="col-md-7"><label class="form-label small fw-semibold">Report (PDF or CSV)</label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.csv,.txt" required></div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary"><i class="bi bi-eye me-1"></i>Preview &amp; compare</button>
                        <a href="{{ route('loan-provisions.import-arrears.template') }}" class="btn btn-outline-secondary">Template</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span>Loans with old arrears ({{ $imported->count() }})</span>
                @if($imported->isNotEmpty())
                <form method="POST" action="{{ route('loan-provisions.import-arrears.clear') }}" onsubmit="return confirm('Clear the old arrears on every loan?')">@csrf
                    <button class="btn btn-sm btn-outline-danger">Clear all</button></form>
                @endif
            </div>
            <div class="table-responsive" style="max-height:420px">
                <table class="table table-sm small mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Loan</th><th>Member</th><th>Arrears since</th><th class="text-end pe-3">Arrears (report date)</th></tr></thead>
                    <tbody>
                    @forelse($imported as $l)
                        <tr><td class="ps-3"><a href="{{ route('loans.show', $l) }}">{{ $l->loan_number }}</a></td><td>{{ $l->client->name ?? '' }}</td>
                            <td>{{ $l->arrears_bf_since->format('d M Y') }}</td><td class="text-end pe-3">{{ $f($l->arrears_bf_amount) }} <span class="text-muted">({{ $l->arrears_bf_as_at->format('d/m/Y') }})</span></td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">None imported yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@else
@php
    $lines = collect($preview['lines']);
    $oldTotal = $lines->sum('loan_bal');
@endphp
<div class="alert alert-light border small">
    Report date <b>{{ \Carbon\Carbon::parse($preview['as_at'])->format('d M Y') }}</b> ·
    old report total <b>{{ $f($oldTotal) }}</b> ({{ $lines->count() }} loans) · new system total <b>{{ $f($preview['new_total']) }}</b> ·
    old specific <b>{{ $f($lines->sum('old_specific')) }}</b> · new specific after import <b>{{ $f($lines->sum('new_specific')) }}</b>
</div>
<div class="card mb-3">
    <div class="table-responsive">
        <table class="table table-sm small align-middle mb-0">
            <thead class="table-light"><tr>
                <th class="ps-3">Member</th><th>New loan</th><th class="text-end">Old balance</th><th class="text-end">New balance</th>
                <th class="text-end">Old arrears</th><th class="text-end">Old days</th><th class="text-end">New days (schedule)</th>
                <th class="text-end">Old specific</th><th class="text-end">New specific</th><th class="pe-3">Note</th>
            </tr></thead>
            <tbody>
            @foreach($lines as $l)
                @php $balDiff = $l['new_bal'] !== null && abs($l['new_bal'] - $l['loan_bal']) > 1; @endphp
                <tr class="{{ $l['note'] ? 'table-warning' : '' }}">
                    <td class="ps-3">{{ $l['member_id'] }} <span class="text-muted">{{ $l['client'] }}</span></td>
                    <td>{{ $l['loan_number'] ?? '—' }}</td>
                    <td class="text-end">{{ $f($l['loan_bal']) }}</td>
                    <td class="text-end {{ $balDiff ? 'text-danger fw-semibold' : '' }}">{{ $f($l['new_bal']) }}</td>
                    <td class="text-end">{{ $f($l['arrears']) }}</td>
                    <td class="text-end">{{ $l['days'] }}</td>
                    <td class="text-end {{ $l['new_days'] !== null && $l['new_days'] != $l['days'] ? 'text-danger' : '' }}">{{ $l['new_days'] ?? '—' }}</td>
                    <td class="text-end">{{ $l['old_specific'] > 0 ? $f($l['old_specific']) : '' }}</td>
                    <td class="text-end">{{ $l['new_specific'] > 0 ? $f($l['new_specific']) : '' }}</td>
                    <td class="pe-3">{{ $l['note'] }}@if($l['since'] && $l['loan_id']) <span class="text-muted">arrears since {{ \Carbon\Carbon::parse($l['since'])->format('d/m/Y') }}</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@if($preview['only_new'])
<div class="card mb-3">
    <div class="card-header fw-semibold">In the new system on this date but not in the old report ({{ count($preview['only_new']) }})</div>
    <div class="table-responsive">
        <table class="table table-sm small mb-0">
            <thead class="table-light"><tr><th class="ps-3">Loan</th><th>Member</th><th class="text-end">Balance</th><th class="text-end">Days in arrears</th><th class="pe-3">Disbursed</th></tr></thead>
            <tbody>
            @foreach($preview['only_new'] as $o)
                <tr><td class="ps-3">{{ $o['loan_number'] }}</td><td>{{ $o['client_number'] }} {{ $o['client'] }}</td><td class="text-end">{{ $f($o['bal']) }}</td><td class="text-end">{{ $o['days'] }}</td><td class="pe-3">{{ $o['disbursed'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
<div class="d-flex gap-2">
    <form method="POST" action="{{ route('loan-provisions.import-arrears.confirm') }}">@csrf
        <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save old arrears on {{ $lines->filter(fn ($l) => $l['loan_id'] && $l['since'])->count() }} loan(s)</button></form>
    <form method="POST" action="{{ route('loan-provisions.import-arrears.cancel') }}">@csrf <button class="btn btn-outline-secondary">Cancel</button></form>
</div>
@endif
@endsection
