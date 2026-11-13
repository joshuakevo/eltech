@extends('layouts.app')
@section('title', 'Account Closure')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Settings</a></li>
    <li class="breadcrumb-item active">Account Closure</li>
@endsection
@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-door-closed me-1 text-danger"></i>Account closure</div>
            <form method="POST" action="{{ route('settings.account-closure.save') }}" class="card-body">
                @csrf
                <p class="small text-muted">Used by <b>Close Account</b> on a member's page (each closure can change the fee). The member's savings pay the closure fee
                    (it includes the withdrawal fee) and the rest is paid out; if the savings are less than the fee, everything goes to the fee.
                    Overdrawn accounts are written off to the expense account below.</p>
                <div class="row g-2">
                    <div class="col-5">
                        <label class="form-label small fw-semibold">Account closure fee</label>
                        <input type="number" name="account_closure_fee" class="form-control" step="1" min="0" value="{{ $closureFee + 0 }}" required>
                    </div>
                    <div class="col-7">
                        <label class="form-label small fw-semibold">Credited to</label>
                        <select name="account_closure_fee_account" class="form-select">
                            @foreach($incomeAccounts as $a)<option value="{{ $a->account_code }}" @selected($a->account_code === $closureAccount)>{{ $a->account_code }} — {{ $a->account_name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Overdrawn balances written off to</label>
                        <select name="overdrawn_writeoff_account" class="form-select">
                            @foreach($expenseAccounts as $a)<option value="{{ $a->account_code }}" @selected($a->account_code === $writeOffAccount)>{{ $a->account_code }} — {{ $a->account_name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <button class="btn btn-primary mt-3"><i class="bi bi-check2 me-1"></i>Save</button>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header fw-semibold">Recent closures</div>
            <div class="table-responsive">
                <table class="table table-sm small mb-0 align-middle">
                    <thead class="table-light"><tr><th class="ps-3">Date</th><th>Member</th><th class="text-end">Savings</th><th class="text-end">Closure fee</th><th class="text-end">Paid out</th><th class="text-end">Written off</th><th class="pe-3">Status</th></tr></thead>
                    <tbody>
                    @forelse($closures as $c)
                        <tr>
                            <td class="ps-3">{{ $c->closure_date->format('d M Y') }}</td>
                            <td>@if($c->client)<a href="{{ route('clients.show', $c->client) }}">{{ $c->client->name }}</a>@endif</td>
                            <td class="text-end">{{ number_format($c->total_balance) }}</td>
                            <td class="text-end">{{ number_format($c->closure_fee) }}</td>
                            <td class="text-end">{{ number_format($c->payout) }}</td>
                            <td class="text-end {{ $c->written_off > 0 ? 'text-danger' : '' }}">{{ number_format($c->written_off) }}</td>
                            <td class="pe-3">@if($c->status === 'applied')<span class="badge bg-secondary">Closed</span>@else<span class="badge bg-light text-muted border">Reopened</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No closures yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
