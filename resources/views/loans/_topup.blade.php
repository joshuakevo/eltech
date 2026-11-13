{{-- Top up a running loan: more money + new rate / method / period (LoanTopUpService; permission: disburse loans) --}}
@php
    $tuSavings = \App\Models\SavingsAccount::with('product')->where('client_id', $loan->client_id)->where('status', 'active')->get();
    $tuPayout  = app(\App\Services\LoanTopUpService::class)->payoutAccounts();
    $tuMin     = collect([$loan->repayments()->max('payment_date'), $loan->interest_accrued_to?->toDateString(), $loan->disbursement_date?->toDateString()])
        ->filter()->map(fn ($d) => \Carbon\Carbon::parse($d)->toDateString())->max();
@endphp
<div class="modal fade" id="topUpLoanModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" action="{{ route('loans.topup', $loan) }}" class="modal-content" id="topUpForm">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-plus-circle me-1 text-success"></i>Top up loan — {{ $loan->loan_number }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border small py-2">
                    Balance now: principal <b>{{ number_format($loan->outstanding_principal, 0) }}</b> · interest owed <b>{{ number_format($loan->outstanding_interest, 0) }}</b>.
                    The loan is brought up to the top-up date on its current terms (interest by days, late penalties), then restarts with
                    <b>principal = balance + top-up</b> on the new rate and period. Interest and penalties still owed stay owed.
                </div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Top-up amount</label>
                        <input type="number" step="0.01" min="1" name="amount" class="form-control fw-bold tu-in" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Top-up date</label>
                        <input type="date" name="topup_date" class="form-control tu-in" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" @if($tuMin) min="{{ $tuMin }}" @endif required>
                        <div class="form-text" style="font-size:.7rem">Installments fall on this day each month</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">New period (months)</label>
                        <input type="number" name="term_months" class="form-control tu-in" min="1" max="240" value="{{ max(1, (int) $loan->term_months) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">New interest rate (% / year)</label>
                        <input type="number" step="0.01" name="interest_rate" class="form-control tu-in" value="{{ $loan->interest_rate + 0 }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Method</label>
                        <select name="interest_method" class="form-select tu-in">
                            <option value="flat" @selected($loan->interest_method === 'flat')>Flat</option>
                            <option value="reducing" @selected($loan->interest_method !== 'flat')>Reducing balance</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Fixed installment <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="number" step="0.01" name="installment" class="form-control tu-in" placeholder="Blank = worked out">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Pay the top-up</label>
                        <select name="payout" class="form-select tu-in" id="tuPayout">
                            <option value="savings" @selected($tuSavings->isNotEmpty())>Into member's savings</option>
                            <option value="account" @selected($tuSavings->isEmpty())>From cash / bank</option>
                        </select>
                    </div>
                    <div class="col-md-8" id="tuSavingsWrap">
                        <label class="form-label small fw-semibold">Savings account</label>
                        <select name="savings_account_id" class="form-select tu-in">
                            @forelse($tuSavings as $sa)
                                <option value="{{ $sa->id }}">{{ $sa->account_number }} — {{ $sa->product->name ?? '' }} ({{ number_format($sa->balance, 0) }})</option>
                            @empty
                                <option value="">No active savings account</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="col-md-8" id="tuAccountWrap">
                        <label class="form-label small fw-semibold">Paid from</label>
                        <select name="payout_account_id" class="form-select tu-in">
                            @foreach($tuPayout as $a)
                                <option value="{{ $a->id }}" @selected($a->account_code === '1001')>{{ $a->account_code }} — {{ $a->account_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-3">
                    <div class="small fw-semibold mb-1">Fees <span class="text-muted fw-normal">(as for a new loan, on the top-up amount)</span></div>
                    <table class="table table-sm align-middle small mb-0">
                        <thead class="table-light"><tr><th>Fee</th><th style="width:30%">Rate / amount</th><th class="text-end">Fee</th><th style="width:30%">Taken from</th></tr></thead>
                        <tbody>
                        @foreach([['application', 'Application', 'application_fee_amount', 50000, false], ['management', 'Management', 'management_fee_rate', 1.5, true], ['insurance', 'Insurance', 'insurance_fee_rate', 1.5, true]] as [$k, $label, $field, $default, $pct])
                            <tr>
                                <td class="fw-semibold">{{ $label }}</td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="{{ $field }}" class="form-control tu-in tu-fee" data-fee="{{ $k }}" data-pct="{{ $pct ? 1 : 0 }}" value="{{ $default }}" step="any" min="0" @if($pct) max="100" @endif required>
                                        @if($pct)<span class="input-group-text">%</span>@endif
                                    </div>
                                </td>
                                <td class="text-end fw-semibold" id="tuFee-{{ $k }}">0</td>
                                <td>
                                    <div class="btn-group btn-group-sm w-100" role="group">
                                        <input type="radio" class="btn-check tu-in tu-feem" name="{{ $k }}_fee_method" id="tu-{{ $k }}-loan" value="loan" checked>
                                        <label class="btn btn-outline-secondary" for="tu-{{ $k }}-loan">Loan</label>
                                        <input type="radio" class="btn-check tu-in tu-feem" name="{{ $k }}_fee_method" id="tu-{{ $k }}-sav" value="savings">
                                        <label class="btn btn-outline-primary" for="tu-{{ $k }}-sav">Savings</label>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-light fw-semibold">
                            <tr><td colspan="2">Total fees · from loan <span id="tuFeeLoan">0</span> · from savings <span id="tuFeeSav">0</span></td><td class="text-end" id="tuFeeTotal">0</td><td class="text-muted fw-normal">Paid out: <b id="tuNet">0</b></td></tr>
                        </tfoot>
                    </table>
                    <div class="form-text">Fees from the loan are deducted from the money paid out; fees from savings come out of the savings account below.</div>
                </div>

                <div class="mt-3" id="tuPreview"></div>

                <div class="mt-3">
                    <label class="form-label small fw-semibold">Reason</label>
                    <input type="text" name="reason" class="form-control" placeholder="e.g. Top-up approved by credit committee" required maxlength="500">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="tuSubmit" disabled><i class="bi bi-check2 me-1"></i>Top up loan</button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
(function () {
    const form = document.getElementById('topUpForm'), out = document.getElementById('tuPreview'), submit = document.getElementById('tuSubmit');
    const payout = document.getElementById('tuPayout');
    const fmt = v => Number(v || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
    const feeFromSavings = () => [...form.querySelectorAll('.tu-feem:checked')].some(r => r.value === 'savings');
    const togglePayout = () => {
        const s = payout.value === 'savings';
        document.getElementById('tuSavingsWrap').style.display = (s || feeFromSavings()) ? '' : 'none';
        document.getElementById('tuAccountWrap').style.display = s ? 'none' : '';
    };
    const recalcFees = () => {
        const amt = parseFloat(form.amount.value) || 0;
        let loan = 0, sav = 0;
        form.querySelectorAll('.tu-fee').forEach(el => {
            const v = parseFloat(el.value) || 0, k = el.dataset.fee;
            const fee = el.dataset.pct === '1' ? Math.round(amt * v / 100 * 100) / 100 : v;
            document.getElementById('tuFee-' + k).textContent = fmt(fee);
            const m = form.querySelector('[name=' + k + '_fee_method]:checked').value;
            if (m === 'savings') sav += fee; else loan += fee;
        });
        document.getElementById('tuFeeLoan').textContent = fmt(loan);
        document.getElementById('tuFeeSav').textContent = fmt(sav);
        document.getElementById('tuFeeTotal').textContent = fmt(loan + sav);
        document.getElementById('tuNet').textContent = fmt(Math.max(0, amt - loan));
        togglePayout();
    };
    payout.addEventListener('change', togglePayout);
    form.querySelectorAll('.tu-fee, .tu-feem, [name=amount]').forEach(el => el.addEventListener('input', recalcFees));
    form.querySelectorAll('.tu-feem').forEach(el => el.addEventListener('change', recalcFees));
    recalcFees();
    let timer, seq = 0;
    const preview = async () => {
        submit.disabled = true;
        if (!form.amount.value) { out.innerHTML = '<div class="text-muted small">Enter the top-up amount to see the new schedule.</div>'; return; }
        const mine = ++seq;
        const fd = new FormData(form); fd.delete('reason');
        out.innerHTML = '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Working out the new schedule…</div>';
        const r = await fetch(@json(route('loans.topup.preview', $loan)), { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': fd.get('_token') }, body: fd });
        const j = await r.json().catch(() => ({ ok: false, errors: ['Could not work out the schedule.'] }));
        if (mine !== seq) return;
        if (!j.ok) { out.innerHTML = '<div class="alert alert-danger small py-2 mb-0">' + (j.errors || []).join('<br>') + '</div>'; return; }
        const rows = j.schedule.map(s => `<tr><td>${s.no}</td><td>${s.due}</td><td class="text-end">${fmt(s.principal)}</td><td class="text-end">${fmt(s.interest)}</td>`
            + `<td class="text-end">${s.penalty ? fmt(s.penalty) : '—'}</td><td class="text-end fw-semibold">${fmt(s.total + s.penalty)}</td><td class="text-end text-muted">${fmt(s.balance)}</td></tr>`).join('');
        const jr = j.journal.map(l => `<tr><td>${l.code} ${l.name}</td><td class="text-end">${l.debit ? fmt(l.debit) : ''}</td><td class="text-end">${l.credit ? fmt(l.credit) : ''}</td></tr>`).join('');
        out.innerHTML = `<div class="d-flex flex-wrap gap-3 small mb-2">`
            + `<span>Balance <b>${fmt(j.balance)}</b> + top-up <b>${fmt(j.amount)}</b> = <b class="text-success">${fmt(j.principal)}</b></span>`
            + `<span>Paid out <b>${fmt(j.net_payout)}</b>${(j.fees.from_loan + j.fees.from_savings) > 0.5 ? ` (fees ${fmt(j.fees.from_loan + j.fees.from_savings)})` : ''}</span>`
            + (j.interest > 0.5 ? `<span>Interest owed carried <b>${fmt(j.interest)}</b></span>` : '')
            + (j.penalty > 0.5 ? `<span>Penalty carried <b>${fmt(j.penalty)}</b></span>` : '')
            + `<span>Installment <b>${fmt(j.installment)}</b></span><span>Matures <b>${j.maturity}</b></span></div>`
            + `<table class="table table-sm small mb-2"><thead class="table-light"><tr><th>Journal</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead><tbody>${jr}</tbody></table>`
            + `<div style="max-height:260px;overflow:auto"><table class="table table-sm small mb-0"><thead class="table-light" style="position:sticky;top:0"><tr><th>#</th><th>Due</th><th class="text-end">Principal</th><th class="text-end">Interest</th><th class="text-end">Penalty</th><th class="text-end">Total</th><th class="text-end">Balance</th></tr></thead><tbody>${rows}</tbody></table></div>`;
        submit.disabled = false;
    };
    form.querySelectorAll('.tu-in').forEach(el => el.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(preview, 450); }));
    document.getElementById('topUpLoanModal').addEventListener('shown.bs.modal', preview);
    form.addEventListener('submit', e => {
        if (submit.disabled || !confirm('Top up {{ $loan->loan_number }} by ' + fmt(form.amount.value) + '?')) { e.preventDefault(); return; }
        submit.disabled = true;
    });
})();
</script>
@endpush
