@extends('layouts.app')
@section('title', 'Leftover loan recoveries')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loans.run') }}">Run Loans</a></li>
    <li class="breadcrumb-item active">Leftover recoveries</li>
@endsection
@section('content')
<div class="page-header">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-link-45deg me-1 text-warning"></i>Leftover loan recoveries</h4>
        <div class="text-muted small">Savings withdrawals marked "Loan repayment - …" whose loan repayment no longer exists. The money left the member's savings but never reached the loan.</div>
    </div>
</div>

@if($overpaid->isNotEmpty())
<form method="POST" action="{{ route('loans.orphan-recoveries.overpaid') }}" class="mb-4" onsubmit="return confirm('Correct the ticked repayments? Each is lowered to what was applied and the extra goes back on the member\'s savings.')">
    @csrf
    <div class="card border-danger">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-exclamation-triangle text-danger me-1"></i>{{ $overpaid->count() }} overpaid repayment(s) — recorded for more than was applied ({{ number_format($overpaid->sum('excess'), $dp) }} extra)</span>
            <button class="btn btn-sm btn-danger"><i class="bi bi-wrench me-1"></i>Correct ticked</button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th class="ps-3"></th><th>Date</th><th>Member</th><th>Loan</th><th class="text-end">Recorded</th><th class="text-end">Applied</th><th class="text-end">Extra</th><th class="pe-3">Paid from</th></tr></thead>
                <tbody class="small">
                @foreach($overpaid as $r)
                    <tr>
                        <td class="ps-3"><input type="checkbox" name="repayment_ids[]" value="{{ $r->id }}" class="form-check-input" checked></td>
                        <td class="text-nowrap">{{ $r->payment_date->format('d M Y') }}</td>
                        <td>{{ $r->loan->client->name ?? '—' }} <span class="text-muted">{{ $r->loan->client->client_number ?? '' }}</span></td>
                        <td>@if($r->loan)<a href="{{ route('loans.show', $r->loan) }}" class="font-monospace">{{ $r->loan->loan_number }}</a>@endif</td>
                        <td class="text-end">{{ number_format($r->amount, $dp) }}</td>
                        <td class="text-end">{{ number_format($r->amount - $r->excess, $dp) }}</td>
                        <td class="text-end fw-semibold text-danger">{{ number_format($r->excess, $dp) }}</td>
                        <td class="pe-3">{{ $r->funding ? ($r->funding->savingsAccount->account_number ?? 'savings') . ' — extra goes back' : ucfirst($r->payment_method) . ' — amount corrected only' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</form>
@endif

@if($orphans->isEmpty())
    <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>Every loan recovery withdrawal has its repayment.</div>
@else
<form method="POST" action="{{ route('loans.orphan-recoveries.destroy') }}" onsubmit="return confirm('Remove the ticked withdrawals? The money goes back on the members\' savings and their withdrawal journals are deleted.')">
    @csrf @method('DELETE')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>{{ $orphans->count() }} leftover withdrawal(s) · {{ number_format($orphans->sum('amount'), $dp) }}</span>
            <button class="btn btn-sm btn-danger"><i class="bi bi-trash me-1"></i>Remove ticked</button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th class="ps-3"><input type="checkbox" class="form-check-input" checked onclick="document.querySelectorAll('.orph').forEach(c => c.checked = this.checked)"></th>
                        <th>Date</th><th>Member</th><th>Savings account</th><th>Description</th><th>Loan</th><th class="text-end pe-3">Amount</th></tr>
                </thead>
                <tbody class="small">
                @foreach($orphans as $w)
                    <tr>
                        <td class="ps-3"><input type="checkbox" name="ids[]" value="{{ $w->id }}" class="form-check-input orph" checked></td>
                        <td class="text-nowrap">{{ $w->transaction_date->format('d M Y') }}</td>
                        <td>{{ $w->savingsAccount->client->name ?? '—' }} <span class="text-muted">{{ $w->savingsAccount->client->client_number ?? '' }}</span></td>
                        <td class="font-monospace">@if($w->savingsAccount)<a href="{{ route('savings.show', $w->savingsAccount) }}">{{ $w->savingsAccount->account_number }}</a>@endif</td>
                        <td>{{ $w->description }}</td>
                        <td>@if($w->loan)<a href="{{ route('loans.show', $w->loan) }}" class="font-monospace">{{ $w->loan->loan_number }}</a>@else<span class="text-danger">loan not found</span>@endif</td>
                        <td class="text-end pe-3 fw-semibold">{{ number_format($w->amount, $dp) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-muted">Removing a withdrawal deletes its savings row and its journal and rebuilds that account's running balance. If a repayment was deleted by mistake, re-post it from the loan page (Recover from savings) after removing the leftover.</div>
    </div>
</form>
@endif
@endsection
