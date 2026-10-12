{{-- Locked-Up loan → active loans (Run Loans). Opened by data-bs-target="#activateLoanModal". --}}
@php
    $activationProducts = \App\Models\LoanProduct::where('name', '!=', 'Locked-Up Loans')->where('is_active', true)->orderBy('name')->get();
    $lastRecovery = $loan->repayments->max('payment_date');
    $defaultStart = today()->toDateString();
@endphp
<div class="modal fade" id="activateLoanModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" action="{{ route('loans.activate', $loan) }}" class="modal-content" id="activateForm">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-arrow-repeat me-1 text-primary"></i>Move to active loans — {{ $loan->loan_number }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border small mb-3">
                    Owing now: principal <b>{{ number_format($loan->outstanding_principal, 0) }}</b> · interest <b>{{ number_format($loan->outstanding_interest, 0) }}</b>.
                    The loan gets a product, rate and monthly schedule from the start date; Run Loans then charges interest by days and recovers installments.
                    The interest owed is carried over as charged interest (not income). Principal moves from Locked-Up Loans Receivable to the product's receivable.
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Loan product</label>
                        <select name="loan_product_id" class="form-select act-in" required>
                            @foreach($activationProducts as $p)
                                <option value="{{ $p->id }}" data-rate="{{ $p->interest_rate }}" data-method="{{ $p->interest_method }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Start date</label>
                        <input type="date" name="start_date" class="form-control act-in" value="{{ $defaultStart }}" max="{{ today()->toDateString() }}" @if($lastRecovery) min="{{ \Carbon\Carbon::parse($lastRecovery)->toDateString() }}" @endif required>
                        <div class="form-text" style="font-size:.7rem">Installments fall on this day each month</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Installments (months)</label>
                        <input type="number" name="term_months" class="form-control act-in" min="1" max="240" value="12" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Interest rate (% / year)</label>
                        <input type="number" step="0.01" name="interest_rate" class="form-control act-in" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Method</label>
                        <select name="interest_method" class="form-select act-in">
                            <option value="reducing">Reducing balance</option>
                            <option value="flat">Flat</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Fixed installment <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="number" step="0.01" name="installment" class="form-control act-in" placeholder="Blank = fitted to clear by maturity">
                    </div>
                </div>

                <div id="actPreview" class="mt-3"><div class="text-muted small">Fill in the terms to see the schedule.</div></div>

                <div class="mt-3">
                    <label class="form-label small fw-semibold">Reason</label>
                    <input type="text" name="reason" class="form-control" placeholder="e.g. Member agreed a repayment plan" required maxlength="500">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="actSubmit" disabled><i class="bi bi-check2-circle me-1"></i>Move to active loans</button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
(function () {
    const form = document.getElementById('activateForm'), out = document.getElementById('actPreview'), submit = document.getElementById('actSubmit');
    const product = form.querySelector('[name=loan_product_id]');
    const fmt = v => Number(v || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
    const fillDefaults = () => {
        const o = product.selectedOptions[0]; if (!o) return;
        form.querySelector('[name=interest_rate]').value = o.dataset.rate || '';
        if (o.dataset.method) form.querySelector('[name=interest_method]').value = o.dataset.method === 'flat' ? 'flat' : 'reducing';
    };
    product.addEventListener('change', fillDefaults); fillDefaults();
    let timer;
    const preview = async () => {
        submit.disabled = true;
        const fd = new FormData(form); fd.delete('reason');
        out.innerHTML = '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Working out the schedule…</div>';
        const r = await fetch(@json(route('loans.activate.preview', $loan)), { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': fd.get('_token') }, body: fd });
        const j = await r.json().catch(() => ({ ok: false, errors: ['Could not work out the schedule.'] }));
        if (!j.ok) { out.innerHTML = '<div class="alert alert-danger small py-2 mb-0">' + (j.errors || []).join('<br>') + '</div>'; return; }
        const rows = j.schedule.map(s => `<tr><td>${s.no}</td><td>${s.due}</td><td class="text-end">${fmt(s.principal)}</td><td class="text-end">${fmt(s.interest)}</td><td class="text-end fw-semibold">${fmt(s.total)}</td><td class="text-end text-muted">${fmt(s.balance)}</td></tr>`).join('');
        const jr = j.journal.map(l => `<tr><td>${l.code} ${l.name}</td><td class="text-end">${l.debit ? fmt(l.debit) : ''}</td><td class="text-end">${l.credit ? fmt(l.credit) : ''}</td></tr>`).join('');
        out.innerHTML = `<div class="d-flex flex-wrap gap-3 small mb-2"><span>Product <b>${j.product}</b></span><span>Installment <b>${fmt(j.installment)}</b></span><span>Matures <b>${j.maturity}</b></span><span>Owing: P <b>${fmt(j.principal)}</b> · I <b>${fmt(j.interest)}</b></span></div>`
            + (jr ? `<table class="table table-sm small mb-2"><thead class="table-light"><tr><th>Journal</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead><tbody>${jr}</tbody></table>` : '')
            + `<div style="max-height:260px;overflow:auto"><table class="table table-sm small mb-0"><thead class="table-light" style="position:sticky;top:0"><tr><th>#</th><th>Due</th><th class="text-end">Principal</th><th class="text-end">Interest</th><th class="text-end">Total</th><th class="text-end">Balance</th></tr></thead><tbody>${rows}</tbody></table></div>`;
        submit.disabled = false;
    };
    form.querySelectorAll('.act-in').forEach(el => el.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(preview, 450); }));
    document.getElementById('activateLoanModal').addEventListener('shown.bs.modal', preview);
    form.addEventListener('submit', e => { if (submit.disabled || !confirm('Move {{ $loan->loan_number }} to active loans?')) { e.preventDefault(); return; } submit.disabled = true; });
})();
</script>
@endpush
