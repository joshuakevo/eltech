@extends('layouts.app')
@section('title', 'Reset Loan Repayments')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
    <li class="breadcrumb-item"><a href="{{ route('loans.run') }}">Run Loans</a></li>
    <li class="breadcrumb-item active">Reset repayments</li>
@endsection
@php
    $ready = $plans->filter(fn ($p) => !$p['skip']);
@endphp
@section('content')
<div class="mb-3">
    <h4 class="fw-bold mb-0">Reset Loan Repayments</h4>
    <div class="text-muted small">Remove repayments from a date — and everything they created — so the loans can be re-run afresh.</div>
</div>

@if(session('reset_results'))
@php $rr = session('reset_results'); @endphp
<div class="card mb-3 border-success">
    <div class="card-header fw-semibold"><i class="bi bi-check2-circle text-success me-1"></i>Reset results</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 small">
            <thead><tr><th class="ps-3">Loan</th><th class="text-end">Repayments removed</th><th class="text-end">Returned to savings</th><th class="text-end">Other journals</th><th class="text-end">Principal now</th><th class="text-end pe-3">Interest now</th></tr></thead>
            <tbody>
            @foreach($rr['done'] as $d)
                <tr><td class="ps-3 font-monospace">{{ $d['loan'] }}</td><td class="text-end">{{ $d['repayments'] }} ({{ number_format($d['repaid'], $dp) }})</td>
                    <td class="text-end">{{ $d['withdrawals'] }} ({{ number_format($d['returned'], $dp) }})</td><td class="text-end">{{ $d['loose'] }}</td>
                    <td class="text-end">{{ number_format($d['principal'], $dp) }}</td><td class="text-end pe-3">{{ number_format($d['interest'], $dp) }}</td></tr>
            @endforeach
            @foreach($rr['skipped'] as $s)
                <tr class="table-warning"><td class="ps-3" colspan="6">Skipped — {{ $s }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">Remove repayments dated from</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $from->toDateString() }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Loan / client (optional)</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="All loans">
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Preview</button></div>
        </form>
        <div class="small text-muted mt-2">
            For each loan below: every repayment dated on/after the date is deleted with its journal (and any reversal), the savings withdrawals that funded them are deleted with their journals
            (money returned to the member's savings, statements rebuilt), leftover reversed/orphaned repayment journals are deleted, and all interest charges and Run Loans records are removed.
            The loan goes back to its starting figures: schedule unpaid, principal = the schedule's principal, day-based interest restarting from 31 Jul 2026 (or its balance-correction date / disbursement).
            <b>This cannot be undone.</b>
        </div>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger small py-2">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

<form method="POST" action="{{ route('loans.reset.execute') }}" id="resetForm"
      onsubmit="if (this.dataset.sent) return false; if (!confirm('Reset the selected loans? This permanently deletes their repayments, savings withdrawals and journals from {{ $from->format('d M Y') }}.')) return false; this.dataset.sent = 1; document.getElementById('resetBtn').disabled = true; return true;">
    @csrf
    <input type="hidden" name="from" value="{{ $from->toDateString() }}">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold">{{ $plans->count() }} loan(s) affected · {{ $plans->sum(fn ($p) => $p['repayments']->count()) }} repayments ({{ number_format($plans->sum(fn ($p) => $p['repayments']->sum('amount')), $dp) }})
                · {{ $plans->sum(fn ($p) => $p['withdrawals']->count()) }} savings withdrawals ({{ number_format($plans->sum(fn ($p) => $p['withdrawals']->sum(fn ($w) => abs($w->amount))), $dp) }} back to savings)</span>
        </div>
        <table class="table table-sm table-hover mb-0 align-middle small" style="table-layout:fixed">
            <colgroup><col style="width:2.2rem"><col style="width:22%"><col><col><col><col><col style="width:24%"></colgroup>
            <thead class="table-light"><tr>
                <th class="ps-3"><input type="checkbox" class="form-check-input" id="resetAll" checked></th>
                <th>Loan</th>
                <th>Repayments removed</th>
                <th>Savings returned</th>
                <th class="text-end">Principal now → after</th>
                <th class="text-end">Interest now → after</th>
                <th class="pe-3">Also removed / notes</th>
            </tr></thead>
            <tbody>
            @forelse($plans as $p)
                <tr class="{{ $p['skip'] ? 'table-warning' : '' }}">
                    <td class="ps-3"><input type="checkbox" class="form-check-input reset-pick" name="loan_ids[]" value="{{ $p['loan']->id }}" {{ $p['skip'] ? 'disabled' : 'checked' }}></td>
                    <td>
                        <a href="{{ route('loans.show', $p['loan']) }}" class="font-monospace">{{ $p['loan']->loan_number }}</a>
                        <div class="text-truncate">{{ $p['loan']->client->name ?? '—' }}</div>
                    </td>
                    <td>
                        @forelse($p['repayments'] as $r)
                            <div>{{ $r->payment_date->format('d M') }} · {{ number_format($r->amount, $dp) }} <span class="text-muted">{{ $r->payment_method }}</span></div>
                        @empty <span class="text-muted">—</span> @endforelse
                    </td>
                    <td>
                        @forelse($p['withdrawals'] as $w)
                            <div>{{ $w->transaction_date->format('d M') }} · {{ number_format(abs($w->amount), $dp) }} <span class="text-muted font-monospace">{{ $w->savingsAccount->account_number ?? '' }}</span></div>
                        @empty <span class="text-muted">—</span> @endforelse
                    </td>
                    <td class="text-end">{{ number_format($p['now']['principal'], $dp) }}<br><b>{{ number_format($p['after']['principal'], $dp) }}</b></td>
                    <td class="text-end">{{ number_format($p['now']['interest'], $dp) }}<br><b>{{ number_format($p['after']['interest'], $dp) }}</b></td>
                    <td class="pe-3">
                        @if($p['skip'])<div class="text-danger">{{ $p['skip'] }}</div>@endif
                        @if($p['loose']->count())<div>{{ $p['loose']->count() }} reversed/orphaned journal(s): {{ $p['loose']->pluck('reference')->implode(', ') }}</div>@endif
                        @if($p['charges'])<div>{{ $p['charges'] }} interest charge(s) ({{ number_format($p['charges_total'], $dp) }})</div>@endif
                        @if($p['runs'])<div>{{ $p['runs'] }} run record(s)</div>@endif
                        @if(abs($p['leftover']) > 0.01)<div class="text-warning">{{ number_format($p['leftover'], $dp) }} paid on schedule with no repayment behind it — cleared</div>@endif
                        @if($p['correction'])<div class="text-muted">Keeps balance correction as at {{ $p['correction']->as_at_date->format('d M Y') }} (interest {{ number_format($p['correction']->interest_at_date, $dp) }} carried)</div>@endif
                        @if($p['locked_up'])<div class="text-muted">Locked-Up / no schedule: balances restored by the repayments removed</div>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Nothing to reset from {{ $from->format('d M Y') }}.</td></tr>
            @endforelse
            </tbody>
        </table>
        @if($ready->count())
        <div class="card-footer d-flex flex-wrap gap-2 align-items-center justify-content-end">
            <label class="small text-muted mb-0" for="resetConfirm">Type <b>RESET</b> to confirm</label>
            <input type="text" name="confirm" id="resetConfirm" class="form-control form-control-sm" style="max-width:120px" autocomplete="off">
            <button class="btn btn-danger btn-sm" id="resetBtn" disabled><i class="bi bi-arrow-counterclockwise me-1"></i>Reset selected loans</button>
        </div>
        @endif
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const picks = Array.from(document.querySelectorAll('.reset-pick:not(:disabled)'));
    const all = document.getElementById('resetAll'), btn = document.getElementById('resetBtn'), conf = document.getElementById('resetConfirm');
    function update() { if (btn) btn.disabled = !(conf && conf.value === 'RESET' && picks.some(p => p.checked)); }
    if (all) all.addEventListener('change', () => { picks.forEach(p => p.checked = all.checked); update(); });
    picks.forEach(p => p.addEventListener('change', update));
    if (conf) conf.addEventListener('input', update);
    update();
})();
</script>
@endpush
