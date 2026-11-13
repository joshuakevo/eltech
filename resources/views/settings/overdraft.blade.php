@extends('layouts.app')
@section('title', 'Overdraft Charges')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Settings</a></li>
    <li class="breadcrumb-item active">Overdraft Charges</li>
@endsection
@section('content')
<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-dash-circle me-1 text-danger"></i>Overdraft charge settings</div>
            <form method="POST" action="{{ route('settings.overdraft.update') }}" class="card-body">
                @csrf
                <p class="small text-muted">Defaults for the month-end run (<a href="{{ route('overdraft.run') }}">Accounting → Overdraft Charges</a>): every account negative at the
                    month end is charged this rate of its negative balance; the run can change the rate, minimum and each amount.
                    The charge is added to the member's negative balance. When the member pays money in, the charge moves to income.</p>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Rate (% of the negative balance)</label>
                        <div class="input-group">
                            <input type="number" name="overdraft_charge_rate" class="form-control" step="0.0001" min="0" max="100" value="{{ old('overdraft_charge_rate', $rate + 0) }}" required>
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Minimum charge (per account)</label>
                        <input type="number" name="overdraft_charge_minimum" class="form-control" step="1" min="0" value="{{ old('overdraft_charge_minimum', $minimum + 0) }}" required>
                        <div class="form-text">0 = no minimum</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Accrued overdraft fees account <span class="text-muted fw-normal">(credited when charged)</span></label>
                        <select name="overdraft_accrued_account" class="form-select">
                            @foreach($accounts as $a)<option value="{{ $a->account_code }}" @selected($a->account_code === $accruedCode)>{{ $a->account_code }} — {{ $a->account_name }} ({{ $a->account_type }})</option>@endforeach
                        </select>
                        @unless($accruedOk)<div class="text-danger small mt-1">Account {{ $accruedCode }} not found — charges can't be posted until one is chosen.</div>@endunless
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Overdraft fee income account <span class="text-muted fw-normal">(credited when the member pays)</span></label>
                        <select name="overdraft_income_account" class="form-select">
                            @foreach($accounts as $a)<option value="{{ $a->account_code }}" @selected($a->account_code === $incomeCode)>{{ $a->account_code }} — {{ $a->account_name }} ({{ $a->account_type }})</option>@endforeach
                        </select>
                        @unless($incomeOk)<div class="text-danger small mt-1">Account {{ $incomeCode }} not found — fees stay accrued until one is chosen.</div>@endunless
                    </div>
                </div>
                <button class="btn btn-primary mt-3"><i class="bi bi-check2 me-1"></i>Save</button>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header fw-semibold">How it posts</div>
            <div class="card-body small">
                <table class="table table-sm mb-3">
                    <thead class="table-light"><tr><th>When</th><th>Debit</th><th>Credit</th></tr></thead>
                    <tbody>
                        <tr><td>Month end — every account negative that day is charged</td><td>Member savings</td><td>{{ $accruedCode }} Accrued overdraft fees</td></tr>
                        <tr><td>The member pays money in (deposit, transfer in, salary…)</td><td>Cash / bank / mobile money</td><td>Member savings</td></tr>
                        <tr><td>…and the fee is earned, up to the amount paid (oldest charge first)</td><td>{{ $accruedCode }} Accrued overdraft fees</td><td>{{ $incomeCode }} Overdraft fee income</td></tr>
                    </tbody>
                </table>
                <div class="d-flex flex-wrap gap-4">
                    <div><div class="text-muted">Charged</div><div class="fs-5 fw-bold">{{ number_format($totals['charged'], 0) }}</div></div>
                    <div><div class="text-muted">Earned (paid)</div><div class="fs-5 fw-bold text-success">{{ number_format($totals['recognised'], 0) }}</div></div>
                    <div><div class="text-muted">Still accrued</div><div class="fs-5 fw-bold text-danger">{{ number_format($totals['charged'] - $totals['recognised'], 0) }}</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span>Overdraft charges</span>
        <div class="btn-group btn-group-sm">
            <a href="{{ route('settings.overdraft') }}" class="btn btn-outline-secondary {{ request('status') !== 'unpaid' ? 'active' : '' }}">All</a>
            <a href="{{ route('settings.overdraft', ['status' => 'unpaid']) }}" class="btn btn-outline-secondary {{ request('status') === 'unpaid' ? 'active' : '' }}">Not yet paid</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 small align-middle">
            <thead class="table-light"><tr><th class="ps-3">Date</th><th>Member</th><th>Account</th><th class="text-end">Overdrawn by</th><th class="text-end">Charge</th><th class="text-end">Paid → income</th><th class="text-end">Still accrued</th><th class="pe-3">By</th></tr></thead>
            <tbody>
            @forelse($charges as $c)
                <tr>
                    <td class="ps-3">{{ $c->charge_date->format('d M Y') }}</td>
                    <td>{{ $c->savingsAccount->client->name ?? '—' }}</td>
                    <td>@if($c->savingsAccount)<a href="{{ route('savings.show', $c->savingsAccount) }}" class="font-monospace">{{ $c->savingsAccount->account_number }}</a>@endif</td>
                    <td class="text-end">{{ number_format(abs($c->overdrawn_balance), 0) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($c->amount, 0) }}</td>
                    <td class="text-end text-success">{{ number_format($c->recognised_amount, 0) }}</td>
                    <td class="text-end {{ $c->unrecognised() > 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($c->unrecognised(), 0) }}</td>
                    <td class="pe-3 text-muted">{{ $c->createdBy->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No overdraft charges yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($charges->hasPages())<div class="card-footer">{{ $charges->links() }}</div>@endif
</div>
@endsection
