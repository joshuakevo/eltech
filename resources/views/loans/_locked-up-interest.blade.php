{{-- Charge interest by hand on a Locked-Up loan (permission: correct loans) — LockedUpInterestService --}}
@php
    $luLast = $loan->lockedUpCharges->last();
    $luFrom = $luLast?->charge_date ?? $loan->disbursement_date;
@endphp
<div class="modal fade" id="lockedUpInterestModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('loans.locked-up-interest', $loan) }}" class="modal-content" id="luForm">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-percent me-1 text-primary"></i>Charge interest — {{ $loan->loan_number }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body small">
                <p class="text-muted mb-2">Adds interest to what {{ $loan->client->name ?? 'the member' }} owes and shows it as <b>Interest charged</b> on the loan statement.
                    No journal is posted: like all Locked-Up interest it becomes income (4001) when it is recovered.</p>
                <div class="d-flex flex-wrap gap-3 mb-3 text-muted">
                    <span>Principal owed: <b class="text-dark">{{ number_format($loan->outstanding_principal, $dp) }}</b></span>
                    <span>Interest owed now: <b class="text-dark">{{ number_format($loan->outstanding_interest, $dp) }}</b></span>
                    @if($luLast)<span>Charged up to: <b class="text-dark">{{ $luLast->charge_date->format('d M Y') }}</b></span>@endif
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label fw-semibold mb-1">From</label>
                        <input type="date" name="from_date" class="form-control lu-calc" value="{{ old('from_date', $luFrom?->toDateString()) }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold mb-1">Charge date (to)</label>
                        <input type="date" name="charge_date" class="form-control lu-calc" value="{{ old('charge_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold mb-1">On principal</label>
                        <input type="number" step="0.01" min="0" name="principal" class="form-control lu-calc" value="{{ old('principal', round($loan->outstanding_principal, 2)) }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold mb-1">Rate (% p.a.)</label>
                        <input type="number" step="0.0001" min="0" name="rate" class="form-control lu-calc" value="{{ old('rate', $luLast?->rate) }}" placeholder="e.g. 18">
                    </div>
                </div>
                <div class="form-text" id="luWorking">Principal × rate × days ÷ 365.</div>

                <label class="form-label fw-semibold mt-3 mb-1">Interest to charge</label>
                <input type="number" step="0.01" min="0.01" name="amount" id="luAmount" class="form-control form-control-lg fw-bold" value="{{ old('amount') }}" required>
                <div class="form-text">Filled in from the period and rate. You can type a different amount (e.g. a figure agreed with the member).</div>

                <label class="form-label fw-semibold mt-2 mb-1">Note (optional)</label>
                <input type="text" name="notes" class="form-control" maxlength="200" value="{{ old('notes') }}" placeholder="e.g. Interest computation to 30 Sep 2026">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Charge interest</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    const form = document.getElementById('luForm');
    if (!form) return;
    const amount = document.getElementById('luAmount'), working = document.getElementById('luWorking');
    let typed = amount.value !== '';
    amount.addEventListener('input', () => { typed = amount.value !== ''; });
    const fmt = v => v.toLocaleString(undefined, { maximumFractionDigits: 2 });
    function calc() {
        const from = form.from_date.value, to = form.charge_date.value;
        const p = parseFloat(form.principal.value), r = parseFloat(form.rate.value);
        if (!from || !to || isNaN(p) || isNaN(r)) { working.textContent = 'Principal × rate × days ÷ 365.'; return; }
        const days = Math.round((new Date(to) - new Date(from)) / 86400000);
        if (days <= 0) { working.textContent = '"From" must be before the charge date.'; return; }
        const v = Math.round(p * r / 100 * days / 365 * 100) / 100;
        working.textContent = fmt(p) + ' × ' + r + '% × ' + days + ' days ÷ 365 = ' + fmt(v);
        if (!typed) amount.value = v.toFixed(2);
    }
    form.querySelectorAll('.lu-calc').forEach(el => el.addEventListener('input', () => { typed = false; calc(); }));
    calc();
})();
</script>
