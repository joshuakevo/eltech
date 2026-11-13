{{-- Bad debt recovered on a written-off loan (permission: repay loans) — LoanWriteOffService::recover. Needs $writeOff. --}}
@php
    $bdLeft     = round($writeOff->total() - $writeOff->recovered(), 2);
    $bdSources  = \App\Models\Account::where('is_payment_source', true)->where('is_active', true)->orderBy('account_code')->get();
    $bdSavings  = \App\Models\SavingsAccount::with('product')->where('client_id', $loan->client_id)->where('status', 'active')->get();
    $bdIncome   = app(\App\Services\LoanWriteOffService::class)->recoveredAccount();
@endphp
<div class="modal fade" id="badDebtRecoveryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('loans.bad-debt-recover', $loan) }}" class="modal-content" id="bdForm">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-arrow-down-circle me-1 text-success"></i>Bad debt recovered — {{ $loan->loan_number }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body small">
                <p class="text-muted mb-2">Money collected on this written-off loan is income: <b>DR</b> the account it came into (or the member's savings) /
                    <b>CR {{ $bdIncome?->account_code }} {{ $bdIncome?->account_name ?? 'Bad Debts Recovered' }}</b>. The loan stays written off.</p>
                <div class="d-flex flex-wrap gap-3 mb-3 text-muted">
                    <span>Written off: <b class="text-dark">{{ number_format($writeOff->total(), 2) }}</b></span>
                    <span>Recovered: <b class="text-dark">{{ number_format($writeOff->recovered(), 2) }}</b></span>
                    <span>Still to recover: <b class="text-danger">{{ number_format($bdLeft, 2) }}</b></span>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Date</label>
                        <input type="date" name="recovery_date" class="form-control" value="{{ today()->toDateString() }}" min="{{ $writeOff->writeoff_date->toDateString() }}" max="{{ today()->toDateString() }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Amount</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01" max="{{ $bdLeft }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Received from</label>
                        <div class="d-flex gap-3">
                            <label class="form-check"><input class="form-check-input" type="radio" name="source" value="cash" checked> Cash / bank / mobile money</label>
                            <label class="form-check"><input class="form-check-input" type="radio" name="source" value="savings" @disabled($bdSavings->isEmpty())> Member's savings</label>
                        </div>
                    </div>
                    <div class="col-12" id="bdCash">
                        <select name="payment_account_id" class="form-select">
                            @foreach($bdSources as $a)<option value="{{ $a->id }}" @selected($a->account_code === '1001')>{{ $a->account_code }} — {{ $a->account_name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12 d-none" id="bdSavings">
                        <select name="savings_account_id" class="form-select">
                            @foreach($bdSavings as $s)<option value="{{ $s->id }}">{{ $s->account_number }} — {{ $s->product->name ?? '' }} ({{ number_format($s->balance, 2) }})</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notes</label>
                        <input type="text" name="notes" class="form-control" maxlength="255" placeholder="Optional">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="bdSubmit" @disabled($bdLeft < 0.01)><i class="bi bi-check2 me-1"></i>Record recovery</button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
(function () {
    const f = document.getElementById('bdForm'); if (!f) return;
    const sync = () => {
        const sav = f.querySelector('input[name=source]:checked').value === 'savings';
        document.getElementById('bdCash').classList.toggle('d-none', sav);
        document.getElementById('bdSavings').classList.toggle('d-none', !sav);
    };
    f.querySelectorAll('input[name=source]').forEach(r => r.addEventListener('change', sync));
    f.addEventListener('submit', () => { document.getElementById('bdSubmit').disabled = true; });
})();
</script>
@endpush
