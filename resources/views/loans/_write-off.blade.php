{{-- Write off a Locked-Up loan (permission: write off loans) — LoanWriteOffService --}}
@php
    $woSvc      = app(\App\Services\LoanWriteOffService::class);
    $woAcc      = $woSvc->accounts();
    $woBlockers = $woSvc->blockers($loan);
    $woP        = round((float) $loan->outstanding_principal, 2);
    $woI        = round((float) $loan->outstanding_interest, 2);
    $woRec      = \App\Models\Account::find(optional($loan->product)->receivable_account_id);
    $woName     = fn ($a) => $a ? "{$a->account_code} {$a->account_name}" : '— not set —';
@endphp
<div class="modal fade" id="writeOffLoanModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="{{ route('loans.write-off', $loan) }}" class="modal-content" id="woForm">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-x-octagon me-1 text-danger"></i>Write off — {{ $loan->loan_number }} ({{ $loan->client->name ?? '' }})</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body small">
                <p class="text-muted mb-2">The whole balance is written off: outstanding principal and the interest already charged and unpaid as at the write-off date (charge any interest still due first with <b>Charge interest</b>). The loan moves to <b>Written Off</b>, no longer accepts recoveries or interest charges, and the journal below is posted.</p>
                <table class="table table-sm align-middle mb-3">
                    <thead class="table-light"><tr><th>Account</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
                    <tbody>
                    @if($woP > 0)
                        <tr><td>{{ $woName($woAcc['expense']) }} <span class="text-muted">(principal)</span></td><td class="text-end">{{ number_format($woP, 2) }}</td><td></td></tr>
                        <tr><td class="ps-4">{{ $woName($woRec) }}</td><td></td><td class="text-end">{{ number_format($woP, 2) }}</td></tr>
                    @endif
                    @if($woI > 0)
                        <tr><td>{{ $woName($woAcc['interest_debit']) }} <span class="text-muted">(interest)</span></td><td class="text-end">{{ number_format($woI, 2) }}</td><td></td></tr>
                        <tr><td class="ps-4">{{ $woName($woAcc['interest_credit']) }}</td><td></td><td class="text-end">{{ number_format($woI, 2) }}</td></tr>
                    @endif
                    </tbody>
                    <tfoot class="table-light fw-semibold"><tr><td>Total written off</td><td class="text-end">{{ number_format($woP + $woI, 2) }}</td><td class="text-end">{{ number_format($woP + $woI, 2) }}</td></tr></tfoot>
                </table>
                @if((float) $loan->penalty_brought_forward > 0)
                    <div class="alert alert-light border py-2">Penalty brought forward of <b>{{ number_format($loan->penalty_brought_forward, 2) }}</b> is also cleared (it is off the books, so no journal).</div>
                @endif
                @if($woBlockers)
                    <div class="alert alert-danger py-2"><ul class="mb-0 ps-3">@foreach($woBlockers as $b)<li>{{ $b }}</li>@endforeach</ul></div>
                @endif
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Write-off date</label>
                        <input type="date" name="writeoff_date" class="form-control" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Reason</label>
                        <input type="text" name="reason" class="form-control" maxlength="500" required placeholder="e.g. Board approval of dd/mm/yyyy — member untraceable">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger" id="woSubmit" @disabled($woBlockers)><i class="bi bi-x-octagon me-1"></i>Write off {{ number_format($woP + $woI, 2) }}</button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
document.getElementById('woForm')?.addEventListener('submit', e => {
    if (!confirm('Write off {{ $loan->loan_number }}? {{ number_format($woP + $woI, 2) }} is written off and the loan moves to Written Off.')) { e.preventDefault(); return; }
    document.getElementById('woSubmit').disabled = true;
});
</script>
@endpush
