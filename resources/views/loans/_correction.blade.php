{{-- Loan balance correction: history card + pop-up with live preview. Included from loans/show. --}}
@php
    $lcDefaultAsAt = $loan->disbursement_date && $loan->disbursement_date->gt(\Carbon\Carbon::parse('2026-07-31'))
        ? $loan->disbursement_date->toDateString() : '2026-07-31';
    $lcLatestApplied = $corrections->firstWhere('status', 'applied');
    // Interest field starts at today's figure rolled back to the as-at date (current + interest repaid since),
    // so correcting only the principal never changes interest by accident.
    $lcInterestDefault = round($loan->outstanding_interest + $loan->repayments->filter(fn ($r) => $r->payment_date->gt(\Carbon\Carbon::parse($lcDefaultAsAt)))->sum('interest_paid'), 2);
@endphp

@if($corrections->isNotEmpty())
<div class="card mb-3">
    <div class="card-header fw-semibold"><i class="bi bi-wrench-adjustable me-1"></i>Balance Corrections</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle small">
            <thead><tr>
                <th class="ps-3">As at</th><th class="text-end">Principal</th><th class="text-end">Interest</th>
                <th>Journal</th><th>Reason</th><th>By</th><th class="pe-3"></th>
            </tr></thead>
            <tbody>
            @foreach($corrections as $c)
                <tr class="{{ $c->status === 'reversed' ? 'text-muted' : '' }}">
                    <td class="ps-3">{{ $c->as_at_date->format('d M Y') }}
                        @if($c->status === 'reversed')<span class="badge bg-secondary ms-1">Undone</span>@endif
                    </td>
                    <td class="text-end">{{ number_format($c->old_principal, $dp) }} → <b>{{ number_format($c->new_principal, $dp) }}</b></td>
                    <td class="text-end">{{ number_format($c->old_interest, $dp) }} → <b>{{ number_format($c->new_interest, $dp) }}</b></td>
                    <td class="font-monospace">
                        @if($c->transaction)
                            @can('view transactions')<a href="{{ route('transactions.show', $c->transaction) }}">{{ $c->transaction->reference }}</a>@else{{ $c->transaction->reference }}@endcan
                        @else — @endif
                    </td>
                    <td style="max-width:280px" class="text-truncate" title="{{ $c->reason }}">{{ $c->reason }}</td>
                    <td>{{ $c->createdBy->name ?? '—' }}<div class="text-muted" style="font-size:.7rem">{{ $c->created_at->format('d M Y H:i') }}</div></td>
                    <td class="pe-3 text-end">
                        @can('correct loans')
                        @if($lcLatestApplied && $c->id === $lcLatestApplied->id)
                            <form method="POST" action="{{ route('loans.correction.undo', [$loan, $c]) }}" class="d-inline"
                                  onsubmit="return confirm('Undo this correction? The previous balances and schedule will be restored{{ $c->transaction ? ' and the adjustment journal reversed' : '' }}.')">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-arrow-counterclockwise me-1"></i>Undo</button>
                            </form>
                        @endif
                        @endcan
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@can('correct loans')
<div class="modal fade" id="correctLoanModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="POST" action="{{ route('loans.correct', $loan) }}" class="modal-content" id="lcForm">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-wrench-adjustable me-2"></i>Correct Loan Balance — {{ $loan->loan_number }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @if($errors->has('correction'))
                    <div class="alert alert-danger small py-2">@foreach($errors->get('correction') as $m)<div>{{ is_array($m) ? implode(' ', $m) : $m }}</div>@endforeach</div>
                @endif
                <div class="row g-4">
                    {{-- Inputs --}}
                    <div class="col-lg-4">
                        <div class="lc-step"><span>1</span> What should the balance have been?</div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">As at date <span class="text-danger">*</span></label>
                            <input type="date" name="as_at_date" class="form-control form-control-sm lc-in" value="{{ old('as_at_date', $lcDefaultAsAt) }}"
                                   min="{{ $loan->disbursement_date?->toDateString() }}" max="{{ today()->toDateString() }}" required>
                            <div class="form-text">The date the correct figures apply to — usually the transfer date (31 Jul 2026).</div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">Correct outstanding principal <span class="text-danger">*</span></label>
                            <input type="number" name="principal" step="any" min="0" class="form-control form-control-sm lc-in" value="{{ old('principal', round($loan->outstanding_principal, 2)) }}" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">Correct outstanding interest</label>
                            <input type="number" name="interest" step="any" min="0" class="form-control form-control-sm lc-in" value="{{ old('interest', $lcInterestDefault) }}" placeholder="Blank = calculate from loan terms">
                            <div class="form-text">Starts at the interest currently on the system. Interest above the loan's normal amount is treated as arrears, due on the first installment.
                                <span id="lcCalcWrap" class="d-none"><br>Loan terms ({{ $loan->interest_rate }}% {{ $loan->interest_method }}) give <b id="lcCalcVal"></b> — <a href="#" id="lcUseCalc">use this</a></span></div>
                        </div>

                        <div class="lc-step mt-3"><span>2</span> Accounting</div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Journal date</label>
                                <input type="date" name="journal_date" class="form-control form-control-sm lc-in" value="{{ old('journal_date', $lcDefaultAsAt) }}" max="{{ today()->toDateString() }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Offset account</label>
                                <select name="offset_account_id" class="form-select form-select-sm lc-in">
                                    @foreach($offsetAccounts as $a)
                                        <option value="{{ $a->id }}" {{ (old('offset_account_id') ? old('offset_account_id') == $a->id : $a->account_code === \App\Services\LoanCorrectionService::DEFAULT_OFFSET_CODE) ? 'selected' : '' }}>{{ $a->account_code }} {{ $a->account_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-text mb-2">Only a principal change posts a journal (against Loan Receivables). Interest is booked when received, so it needs none.</div>

                        <div class="lc-step mt-3"><span>3</span> Why?</div>
                        <textarea name="reason" class="form-control form-control-sm" rows="2" maxlength="500" required placeholder="e.g. Transfer balance per old system statement of 31/07/2026">{{ old('reason') }}</textarea>
                    </div>

                    {{-- Live preview --}}
                    <div class="col-lg-8">
                        <div id="lcLoading" class="text-muted small d-none"><span class="spinner-border spinner-border-sm me-1"></span>Calculating…</div>
                        <div id="lcMessages"></div>

                        <div class="row g-2 mb-3" id="lcCompare"></div>

                        <div class="lc-section-title">Adjustment journal</div>
                        <div id="lcJournal" class="mb-3"></div>

                        <div id="lcReplayWrap" class="d-none mb-3">
                            <div class="lc-section-title">Repayments re-applied (after the as-at date)</div>
                            <div id="lcReplay"></div>
                        </div>

                        <div class="lc-section-title d-flex justify-content-between"><span>New schedule</span><span class="fw-normal text-muted" id="lcSchedNote"></span></div>
                        <div class="table-responsive border rounded">
                            <table class="table table-sm mb-0 align-middle lc-sched" style="font-size:.78rem">
                                <thead class="table-light"><tr>
                                    <th>#</th><th>Due date</th><th class="text-end">Principal</th><th class="text-end">Interest</th>
                                    <th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Balance after</th><th>Status</th>
                                </tr></thead>
                                <tbody id="lcSchedule"><tr><td colspan="8" class="text-muted text-center py-3">Enter the figures to see the schedule.</td></tr></tbody>
                                <tfoot class="table-light fw-semibold" id="lcSchedFoot"></tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="me-auto small text-muted">Nothing is saved until you click Apply. A correction can be undone later.</span>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="lcApply" disabled><i class="bi bi-check2-circle me-1"></i>Apply correction</button>
            </div>
        </form>
    </div>
</div>

@push('styles')
<style>
    .lc-step { font-weight: 700; font-size: .85rem; margin-bottom: .5rem; display: flex; align-items: center; gap: .5rem; }
    .lc-step span { width: 1.4rem; height: 1.4rem; border-radius: 50%; background: var(--bs-primary); color: #fff; display: grid; place-items: center; font-size: .72rem; }
    .lc-section-title { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; font-weight: 700; margin-bottom: .35rem; }
    .lc-box { border: 1px solid #e5e7eb; border-radius: .5rem; padding: .5rem .7rem; height: 100%; }
    .lc-box .lbl { font-size: .7rem; color: #6b7280; text-transform: uppercase; letter-spacing: .03em; }
    .lc-box .v { font-weight: 700; font-variant-numeric: tabular-nums; }
    .lc-box .s { font-size: .74rem; color: #6b7280; font-variant-numeric: tabular-nums; }
    .lc-box.new { border-color: #93c5fd; background: #eff6ff; }
    .lc-delta.up { color: #059669; } .lc-delta.down { color: #dc2626; }
    .lc-sched th, .lc-sched td { padding: .3rem .4rem; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const form = document.getElementById('lcForm');
    if (!form) return;
    const dp = {{ $dp }};
    const n = v => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const fmtDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const statusBadge = { paid: 'bg-success', partial: 'bg-info text-dark', overdue: 'bg-danger', pending: 'bg-secondary' };
    let timer = null, seq = 0;

    // Journal date follows the as-at date until the user changes it.
    const asAt = form.querySelector('[name=as_at_date]'), jDate = form.querySelector('[name=journal_date]');
    let jTouched = {{ old('journal_date') ? 'true' : 'false' }};
    jDate.addEventListener('input', () => jTouched = true);
    asAt.addEventListener('input', () => { if (!jTouched) jDate.value = asAt.value; });

    function delta(oldV, newV) {
        const d = newV - oldV;
        if (Math.abs(d) < 0.005) return '<span class="text-muted">no change</span>';
        return '<span class="lc-delta ' + (d > 0 ? 'up' : 'down') + '">' + (d > 0 ? '+' : '−') + n(Math.abs(d)) + '</span>';
    }

    async function preview() {
        const my = ++seq;
        const fd = new FormData(form);
        fd.delete('reason');
        document.getElementById('lcLoading').classList.remove('d-none');
        try {
            const res = await fetch(@json(route('loans.correction.preview', $loan)), {
                method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            if (my !== seq) return;
            if (!res.ok) {
                const msgs = data.errors ? Object.values(data.errors).flat() : [data.message || 'Could not calculate.'];
                render({ errors: msgs, warnings: [], installments: [], journal: [], replayed: [] });
                return;
            }
            render(data);
        } catch (e) {
            if (my === seq) render({ errors: ['Could not calculate the preview. Check your connection and try again.'], warnings: [], installments: [], journal: [], replayed: [] });
        } finally {
            if (my === seq) document.getElementById('lcLoading').classList.add('d-none');
        }
    }

    function render(d) {
        const msgs = [];
        (d.errors || []).forEach(m => msgs.push('<div class="alert alert-danger small py-2 mb-2"><i class="bi bi-x-octagon me-1"></i>' + esc(m) + '</div>'));
        (d.warnings || []).forEach(m => msgs.push('<div class="alert alert-warning small py-2 mb-2"><i class="bi bi-exclamation-triangle me-1"></i>' + esc(m) + '</div>'));
        document.getElementById('lcMessages').innerHTML = msgs.join('');
        document.getElementById('lcApply').disabled = (d.errors || []).length > 0 || !d.new;

        if (d.natural_interest !== undefined && (d.installments || []).length) {
            document.getElementById('lcCalcVal').textContent = n(d.natural_interest);
            document.getElementById('lcCalcWrap').classList.remove('d-none');
            document.getElementById('lcUseCalc').dataset.v = d.natural_interest;
        }
        if (d.new) {
            const interestHint = d.interest_at_date !== undefined && form.querySelector('[name=interest]').value === ''
                ? ' (calculated)' : '';
            document.getElementById('lcCompare').innerHTML = `
                <div class="col-md-4"><div class="lc-box"><div class="lbl">Now on the system</div>
                    <div class="v">${n(d.old.principal)}</div><div class="s">principal</div>
                    <div class="v mt-1">${n(d.old.interest)}</div><div class="s">interest</div></div></div>
                <div class="col-md-4"><div class="lc-box"><div class="lbl">Correct as at ${fmtDate(d.as_at)}</div>
                    <div class="v">${n(d.principal_at_date)}</div><div class="s">principal</div>
                    <div class="v mt-1">${n(d.interest_at_date)}</div><div class="s">interest${interestHint}</div>
                    ${d.paid_after.principal + d.paid_after.interest > 0 ? `<div class="s mt-1">less paid since: ${n(d.paid_after.principal)} / ${n(d.paid_after.interest)}</div>` : ''}</div></div>
                <div class="col-md-4"><div class="lc-box new"><div class="lbl">After correction</div>
                    <div class="v">${n(d.new.principal)} <small>${delta(d.old.principal, d.new.principal)}</small></div><div class="s">principal</div>
                    <div class="v mt-1">${n(d.new.interest)} <small>${delta(d.old.interest, d.new.interest)}</small></div><div class="s">interest</div></div></div>`;
        } else {
            document.getElementById('lcCompare').innerHTML = '';
        }

        document.getElementById('lcJournal').innerHTML = (d.journal || []).length
            ? '<table class="table table-sm small mb-0 border"><thead class="table-light"><tr><th>Account</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead><tbody>'
              + d.journal.map(l => `<tr><td><span class="font-monospace">${esc(l.code)}</span> ${esc(l.name)}</td><td class="text-end">${l.debit ? n(l.debit) : ''}</td><td class="text-end">${l.credit ? n(l.credit) : ''}</td></tr>`).join('')
              + `</tbody></table><div class="form-text">Dated ${fmtDate(d.journal_date)}.</div>`
            : '<div class="small text-muted border rounded p-2">No journal — outstanding principal does not change.</div>';

        const rp = d.replayed || [];
        document.getElementById('lcReplayWrap').classList.toggle('d-none', rp.length === 0);
        document.getElementById('lcReplay').innerHTML = rp.map(r =>
            `<div class="small border-bottom py-1 d-flex justify-content-between"><span>${fmtDate(r.date)} <span class="font-monospace text-muted">${esc(r.reference || '')}</span></span><span>principal ${n(r.principal)} · interest ${n(r.interest)}</span></div>`).join('');

        const rows = d.installments || [];
        document.getElementById('lcSchedNote').textContent = rows.length ? rows.length + ' installment' + (rows.length === 1 ? '' : 's') + ' to maturity' : '';
        document.getElementById('lcSchedule').innerHTML = rows.length ? rows.map(r => `
            <tr><td>${r.installment_no}</td><td class="text-nowrap">${fmtDate(r.due_date)}</td>
                <td class="text-end">${n(r.principal_due)}</td><td class="text-end">${n(r.interest_due)}</td><td class="text-end fw-semibold">${n(r.total_due)}</td>
                <td class="text-end ${r.principal_paid + r.interest_paid > 0 ? 'text-success' : 'text-muted'}">${n(r.principal_paid + r.interest_paid)}</td>
                <td class="text-end">${n(r.balance_after)}</td>
                <td><span class="badge ${statusBadge[r.status] || 'bg-secondary'}" style="font-size:.62rem">${r.status}</span></td></tr>`).join('')
            : '<tr><td colspan="8" class="text-muted text-center py-3">' + ((d.errors || []).length ? 'Fix the issues above to see the schedule.' : 'No schedule (balances only).') + '</td></tr>';
        const sum = k => rows.reduce((a, r) => a + Number(r[k] || 0), 0);
        document.getElementById('lcSchedFoot').innerHTML = rows.length
            ? `<tr><td colspan="2">Totals</td><td class="text-end">${n(sum('principal_due'))}</td><td class="text-end">${n(sum('interest_due'))}</td><td class="text-end">${n(sum('total_due'))}</td><td class="text-end">${n(sum('principal_paid') + sum('interest_paid'))}</td><td colspan="2"></td></tr>` : '';
    }

    document.getElementById('lcUseCalc').addEventListener('click', e => {
        e.preventDefault();
        form.querySelector('[name=interest]').value = e.target.dataset.v;
        preview();
    });
    form.querySelectorAll('.lc-in').forEach(el => el.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(preview, 350); }));
    document.getElementById('correctLoanModal').addEventListener('shown.bs.modal', preview);
    form.addEventListener('submit', () => { document.getElementById('lcApply').disabled = true; });

    @if($errors->has('correction') || old('reason'))
        document.addEventListener('DOMContentLoaded', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('correctLoanModal')).show());
    @endif
})();
</script>
@endpush
@endcan
