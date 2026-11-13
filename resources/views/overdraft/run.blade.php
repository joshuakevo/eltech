@extends('layouts.app')
@section('title', 'Overdraft Charges')
@section('breadcrumb')
    <li class="breadcrumb-item active">Overdraft Charges</li>
@endsection
@section('content')
@php
    $chargeable = collect($rows)->filter(fn ($r) => !$r['already']);
    $suggestedTotal = $chargeable->sum('suggested');
    $isMonthEnd = $date->isLastOfMonth() && $date->lte(today());
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0">Overdraft Charges</h5>
        <div class="text-muted small">Month-end charge on savings accounts with a negative balance — DR member savings / CR accrued overdraft fees; earned as income when the member pays.</div>
    </div>
    @can('manage settings')
    <a href="{{ route('settings.overdraft') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-gear me-1"></i>Default rate &amp; accounts</a>
    @endcan
</div>

<div class="card mb-3">
    <form method="GET" class="card-body row g-2 align-items-end">
        <div class="col-sm-4 col-md-3">
            <label class="form-label small fw-semibold">Month end</label>
            <input type="month" class="form-control" value="{{ $date->format('Y-m') }}" max="{{ today()->format('Y-m') }}" onchange="this.form.date.value = this.value ? this.value + '-01' : ''; this.form.submit()">
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <div class="form-text">Charged on {{ $date->format('d M Y') }}</div>
        </div>
        <div class="col-6 col-sm-3 col-md-2">
            <label class="form-label small fw-semibold">Rate</label>
            <div class="input-group"><input type="number" name="rate" class="form-control" step="0.0001" min="0" max="100" value="{{ $rate + 0 }}"><span class="input-group-text">%</span></div>
        </div>
        <div class="col-6 col-sm-3 col-md-2">
            <label class="form-label small fw-semibold">Minimum charge</label>
            <input type="number" name="minimum" class="form-control" step="0.01" min="0" value="{{ $minimum + 0 }}">
        </div>
        <div class="col-auto"><button class="btn btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>Recalculate</button></div>
    </form>
</div>

@unless($isMonthEnd)
    <div class="alert alert-warning small">{{ $date->format('F Y') }} has not ended yet — you can look, but the run is for month ends that have passed.</div>
@endunless

<form method="POST" action="{{ route('overdraft.run.process') }}" id="odRunForm"
      onsubmit="if (this.dataset.sent) return false; if (!confirm('Charge the ticked accounts for {{ $date->format('d M Y') }}?')) return false; this.dataset.sent = 1; document.getElementById('odRunBtn').disabled = true; return true;">
    @csrf
    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
    <input type="hidden" name="rate" value="{{ $rate }}">
    <input type="hidden" name="minimum" value="{{ $minimum }}">
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold">{{ count($rows) }} account(s) overdrawn at {{ $date->format('d M Y') }}</span>
            <span class="small">To charge: <b id="odCount">{{ $chargeable->count() }}</b> account(s), total <b id="odTotal">{{ number_format($suggestedTotal, $dp) }}</b></span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 small">
                <thead class="table-light"><tr>
                    <th class="ps-3" style="width:2rem"><input type="checkbox" class="form-check-input" id="odAll" @checked($chargeable->isNotEmpty())></th>
                    <th>Member</th><th>Account</th><th class="text-end">Balance at {{ $date->format('d M') }}</th>
                    <th class="text-end">{{ $rate + 0 }}%{{ $minimum > 0 ? ' (min ' . number_format($minimum, 0) . ')' : '' }}</th>
                    <th style="width:11rem">Charge</th><th class="text-end pe-3">Balance after</th>
                </tr></thead>
                <tbody>
                @forelse($rows as $r)
                    @php $a = $r['account']; @endphp
                    <tr class="{{ $r['already'] ? 'text-muted' : '' }}">
                        <td class="ps-3"><input type="checkbox" class="form-check-input od-pick" name="pick[]" value="{{ $a->id }}" @checked(!$r['already']) @disabled($r['already'])></td>
                        <td>{{ $a->client->name ?? '—' }} <span class="text-muted">{{ $a->client->client_number ?? '' }}</span></td>
                        <td><a href="{{ route('savings.show', $a) }}" class="font-monospace">{{ $a->account_number }}</a> <span class="text-muted">{{ $a->product->name ?? '' }}</span></td>
                        <td class="text-end text-danger fw-semibold">{{ number_format($r['balance'], $dp) }}</td>
                        <td class="text-end">{{ number_format($r['suggested'], $dp) }}</td>
                        <td>
                            @if($r['already'])
                                <span class="badge bg-light text-muted border">charged {{ \Carbon\Carbon::parse($r['already'])->format('d M') }}</span>
                            @else
                                <input type="number" name="amount[{{ $a->id }}]" class="form-control form-control-sm od-amt" step="0.01" min="0" value="{{ number_format($r['suggested'], 2, '.', '') }}" data-balance="{{ $r['balance'] }}">
                            @endif
                        </td>
                        <td class="text-end pe-3 od-after">{{ $r['already'] ? '—' : number_format($r['balance'] - $r['suggested'], $dp) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No savings account was overdrawn at {{ $date->format('d M Y') }}.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($chargeable->isNotEmpty() && $isMonthEnd)
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-muted">Change a charge if agreed, or untick the account to skip it. Each account is charged once per month.</span>
            <button class="btn btn-danger" id="odRunBtn"><i class="bi bi-play-fill me-1"></i>Run overdraft charges</button>
        </div>
        @endif
    </div>
</form>

<div class="card">
    <div class="card-header fw-semibold">Runs</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 small align-middle">
            <thead class="table-light"><tr><th class="ps-3">Month end</th><th class="text-end">Accounts</th><th class="text-end">Total charged</th><th>Rate</th><th>By</th><th>Status</th><th class="pe-3"></th></tr></thead>
            <tbody>
            @forelse($runs as $run)
                <tr>
                    <td class="ps-3"><a href="{{ route('overdraft.run', ['date' => $run->run_date->toDateString()]) }}">{{ $run->run_date->format('d M Y') }}</a></td>
                    <td class="text-end">{{ $run->accounts }}</td>
                    <td class="text-end fw-semibold">{{ number_format($run->total, $dp) }}</td>
                    <td>{{ $run->rate + 0 }}%{{ $run->minimum > 0 ? ', min ' . number_format($run->minimum, 0) : '' }}</td>
                    <td class="text-muted">{{ $run->createdBy->name ?? '—' }} · {{ $run->created_at->format('d M H:i') }}</td>
                    <td>@if($run->status === 'applied')<span class="badge bg-success">Applied</span>@else<span class="badge bg-secondary">Undone</span>@endif</td>
                    <td class="pe-3 text-end">
                        @if($latestApplied && $latestApplied->id === $run->id)
                        <form method="POST" action="{{ route('overdraft.run.undo', $run) }}" onsubmit="return confirm('Undo the overdraft charges for {{ $run->run_date->format('d M Y') }}? The statement lines are removed and the journals reversed.')">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-arrow-counterclockwise me-1"></i>Undo</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-3">No runs yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const fmt = v => Number(v).toLocaleString(undefined, { minimumFractionDigits: {{ $dp }}, maximumFractionDigits: {{ $dp }} });
    const picks = [...document.querySelectorAll('.od-pick:not(:disabled)')];
    const all = document.getElementById('odAll');
    const sum = () => {
        let n = 0, t = 0;
        picks.forEach(p => {
            const row = p.closest('tr'), amt = row.querySelector('.od-amt');
            const v = parseFloat(amt?.value) || 0;
            row.querySelector('.od-after').textContent = fmt(parseFloat(amt.dataset.balance) - v);
            amt.disabled = !p.checked; row.style.opacity = p.checked ? '' : '.5';
            if (p.checked) { n++; t += v; }
        });
        document.getElementById('odCount').textContent = n;
        document.getElementById('odTotal').textContent = fmt(t);
        const btn = document.getElementById('odRunBtn'); if (btn) btn.disabled = n === 0;
    };
    picks.forEach(p => p.addEventListener('change', sum));
    document.querySelectorAll('.od-amt').forEach(a => a.addEventListener('input', sum));
    if (all) all.addEventListener('change', () => { picks.forEach(p => p.checked = all.checked); sum(); });
    sum();
})();
</script>
@endpush
