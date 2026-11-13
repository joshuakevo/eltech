{{-- "Close Account": empty the member's savings into the closure fee / payout; overdrawn accounts written off (MemberClosureService) --}}
@php
    $mcSvc      = app(\App\Services\MemberClosureService::class);
    $mcDate     = today()->toDateString();
    $mcFee      = $mcSvc->defaultClosureFee();
    $mcPlan     = $mcSvc->plan($client, $mcDate, $mcFee);
    $mcBlockers = $mcSvc->blockers($client, $mcDate);
    $mcLoans    = $client->loans->whereNotIn('status', ['closed', 'rejected', 'written_off']);
    $mcFds      = $client->fixedDeposits->whereIn('status', ['active', 'matured']);
    $mcShares   = $client->shares->where('status', '!=', 'liquidated');
@endphp
<div class="modal fade" id="closeAccountModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" action="{{ route('clients.close', $client) }}" class="modal-content" id="closeAccountForm">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-door-closed me-1 text-danger"></i>Close account — {{ $client->name }} ({{ $client->client_number }})</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 class="small fw-bold text-uppercase text-muted mb-2">Balances</h6>
                <table class="table table-sm small align-middle mb-3">
                    <thead class="table-light"><tr><th>Savings account</th><th class="text-end">Balance</th><th class="text-end">Closure fee</th><th class="text-end">Paid out</th><th class="text-end">Written off</th></tr></thead>
                    <tbody id="mcRows">
                    @forelse($mcPlan['accounts'] as $r)
                        <tr data-balance="{{ $r['balance'] }}">
                            <td><span class="font-monospace">{{ $r['account']->account_number }}</span> <span class="text-muted">{{ $r['account']->product->name ?? '' }}</span></td>
                            <td class="text-end fw-semibold {{ $r['balance'] < 0 ? 'text-danger' : '' }}">{{ number_format($r['balance']) }}</td>
                            <td class="text-end mc-fee">{{ number_format($r['closure_fee']) }}</td>
                            <td class="text-end mc-payout">{{ number_format($r['payout']) }}</td>
                            <td class="text-end text-danger">{{ $r['write_off'] > 0 ? number_format($r['write_off']) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">No open savings accounts.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr><td>Total</td><td class="text-end">{{ number_format(collect($mcPlan['accounts'])->sum('balance')) }}</td>
                            <td class="text-end" id="mcTotFee">{{ number_format($mcPlan['closure_fee']) }}</td>
                            <td class="text-end text-success" id="mcTotPayout">{{ number_format($mcPlan['payout']) }}</td>
                            <td class="text-end text-danger">{{ number_format($mcPlan['write_off']) }}</td></tr>
                    </tfoot>
                </table>

                @if($mcPlan['write_off'] > 0)
                    <div class="alert alert-warning small py-2">
                        Overdrawn balances of <b>{{ number_format($mcPlan['write_off']) }}</b> are written off to the <b>Overdrawn Savings Write-Off</b> expense account.
                        @if($mcPlan['unpaid_charges'] > 0) That includes <b>{{ number_format($mcPlan['unpaid_charges']) }}</b> of unpaid overdraft charges, which are cleared from accrued overdraft fees (never earned). @endif
                    </div>
                @endif

                @if($mcLoans->isNotEmpty() || $mcFds->isNotEmpty() || $mcShares->isNotEmpty())
                <div class="small mb-3">
                    @foreach($mcLoans as $l)
                        <div><i class="bi bi-cash-coin me-1 text-danger"></i>Loan {{ $l->loan_number }} ({{ $l->product->name ?? '' }}) — {{ $l->status }}, owes {{ number_format((float) $l->outstanding_principal + (float) $l->outstanding_interest) }}</div>
                    @endforeach
                    @foreach($mcFds as $fd)
                        <div><i class="bi bi-safe me-1 text-warning"></i>Fixed deposit {{ $fd->deposit_number }} — {{ $fd->status }}, {{ number_format($fd->principal) }}</div>
                    @endforeach
                    @if($mcShares->isNotEmpty())
                        <div><i class="bi bi-pie-chart me-1 text-primary"></i>Shares paid {{ number_format($mcShares->sum('amount_paid')) }} — not touched by the closure (liquidate them separately if needed)</div>
                    @endif
                </div>
                @endif

                @if($mcBlockers)
                    <div class="alert alert-danger small py-2"><b>The account can't be closed yet:</b><ul class="mb-0 ps-3">@foreach($mcBlockers as $b)<li>{{ $b }}</li>@endforeach</ul></div>
                @endif

                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Account closure fee</label>
                        <input type="number" name="closure_fee" id="mcFee" class="form-control" step="1" min="0" value="{{ $mcFee + 0 }}" required>
                        <div class="form-text">Includes the withdrawal fee</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Closure date</label>
                        <input type="date" name="closure_date" class="form-control" value="{{ $mcDate }}" max="{{ $mcDate }}" required>
                    </div>
                    <div class="col-md-4" id="mcPayoutWrap">
                        <label class="form-label small fw-semibold">Pay the balance out from</label>
                        <select name="payout_account_id" class="form-select">
                            @foreach($paymentSourceAccounts as $a)
                                <option value="{{ $a->id }}" @selected($a->account_code === '1001')>{{ $a->account_code }} — {{ $a->account_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Reason</label>
                        <input type="text" name="reason" class="form-control" maxlength="500" placeholder="e.g. Member relocating">
                    </div>
                </div>
                <div class="alert alert-light border small mt-3 mb-0" id="mcSummary"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger" id="mcSubmit" @disabled($mcBlockers)><i class="bi bi-door-closed me-1"></i>Close account</button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
(function () {
    const rows = [...document.querySelectorAll('#mcRows tr[data-balance]')];
    const fee = document.getElementById('mcFee');
    const fmt = v => Math.round(v).toLocaleString();
    const total = rows.reduce((s, r) => s + Math.max(0, parseFloat(r.dataset.balance)), 0);
    const writeOff = {{ $mcPlan['write_off'] }};
    function recalc() {
        const f = Math.max(0, Math.round(parseFloat(fee.value) || 0));
        const charged = Math.min(f, total);   // less than the fee: everything goes to the fee
        let left = charged;
        rows.forEach(r => {
            const b = parseFloat(r.dataset.balance);
            if (b < 0) return;
            const c = Math.min(b, left); left -= c;
            r.querySelector('.mc-fee').textContent = fmt(c);
            r.querySelector('.mc-payout').textContent = fmt(b - c);
        });
        const payout = total - charged;
        document.getElementById('mcTotFee').textContent = fmt(charged);
        document.getElementById('mcTotPayout').textContent = fmt(payout);
        document.getElementById('mcPayoutWrap').style.display = payout > 0.5 ? '' : 'none';
        document.getElementById('mcSummary').innerHTML = (total >= f
            ? `Savings <b>${fmt(total)}</b> − closure fee <b>${fmt(f)}</b> = <b class="text-success">${fmt(payout)}</b> paid out to the member.`
            : `Savings <b>${fmt(total)}</b> are less than the closure fee (${fmt(f)}): <b>all of it goes to the account closure fee</b>, nothing is paid out.`)
            + (writeOff > 0 ? ` Overdrawn <b class="text-danger">${fmt(writeOff)}</b> written off.` : '') + ' All savings accounts are then closed.';
    }
    fee.addEventListener('input', recalc); recalc();
    document.getElementById('closeAccountForm').addEventListener('submit', e => {
        if (!confirm('Close {{ addslashes($client->name) }}\'s account? The savings accounts are emptied and closed.')) { e.preventDefault(); return; }
        document.getElementById('mcSubmit').disabled = true;
    });
})();
</script>
@endpush
