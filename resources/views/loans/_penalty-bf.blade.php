{{-- Penalty owed at the 31/07/2026 transfer (old system): add / edit (permission: correct loans) --}}
@php
    $bfPaid = round((float) $loan->repayments()->sum('penalty_bf_paid'), 2);
@endphp
<div class="modal fade" id="penaltyBfModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('loans.penalty-bf', $loan) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-exclamation-octagon me-1 text-danger"></i>Penalty brought forward — {{ $loan->loan_number }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body small">
                <p class="text-muted">The penalty {{ $loan->client->name ?? 'the member' }} owed in the old system at the <strong>31 July 2026</strong> transfer. No journal is posted — when it is paid it goes to penalty income. It is collected before newer penalties, after interest and principal.</p>
                <label class="form-label fw-semibold">Penalty owed at 31 Jul 2026</label>
                <input type="number" step="0.01" min="0" name="amount" class="form-control" value="{{ $loan->penalty_bf_imported !== null ? $loan->penalty_bf_imported + 0 : '' }}" placeholder="0" required>
                <div class="mt-2 d-flex flex-wrap gap-3 text-muted">
                    <span>Already paid: <strong>{{ number_format($bfPaid, $dp) }}</strong></span>
                    <span>Still owed now: <strong class="text-danger">{{ number_format((float) $loan->penalty_brought_forward, $dp) }}</strong></span>
                </div>
                <div class="form-text">Still owed becomes this figure minus what has already been paid. Enter 0 to remove it.</div>
                @if($loan->status === 'closed')
                <div class="alert alert-warning py-2 mt-2 mb-0">This loan is closed. The penalty will show as owed on it, but the loan stays closed — collect it with a repayment once you reopen it, or record it on the member's running loan instead.</div>
                @endif
                <label class="form-label fw-semibold mt-2">Note (optional)</label>
                <input type="text" name="note" class="form-control" maxlength="200" placeholder="e.g. per old system penalty list">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
