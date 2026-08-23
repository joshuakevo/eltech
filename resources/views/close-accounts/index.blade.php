@extends('layouts.app')
@section('title', 'Close Accounts')
@section('breadcrumb')
    <li class="breadcrumb-item active">Close Accounts</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Close Accounts</h4>
</div>

<div class="alert alert-info small">
    <i class="bi bi-info-circle me-1"></i>
    Select one or more savings accounts below and close them in bulk. Only accounts with a
    <strong>zero balance</strong> can be closed — withdraw or transfer remaining funds first.
</div>

<form class="row g-2 mb-3" method="GET">
    <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Account # or client..." value="{{ request('search') }}"></div>
    <div class="col-md-4 d-flex align-items-center">
        <div class="form-check">
            <input type="checkbox" name="zero_balance_only" value="1" class="form-check-input" id="zeroBalanceOnly" @checked(request('zero_balance_only')) onchange="this.form.submit()">
            <label class="form-check-label" for="zeroBalanceOnly">Zero balance only</label>
        </div>
    </div>
    <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
</form>

<form method="POST" action="{{ route('close-accounts.close') }}" id="closeAccountsForm">
    @csrf
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    <th class="ps-3"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                    <th>Account #</th><th>Client</th><th>Product</th>
                    <th class="text-end">Balance</th><th class="pe-3">Status</th>
                </tr></thead>
                <tbody>
                @forelse($accounts as $acc)
                    <tr>
                        <td class="ps-3">
                            <input type="checkbox" name="account_ids[]" value="{{ $acc->id }}" class="form-check-input account-checkbox" @disabled(round($acc->balance, 2) != 0)>
                        </td>
                        <td class="font-monospace">{{ $acc->account_number }}</td>
                        <td>
                            @if($acc->client)
                                <a href="{{ route('clients.show', $acc->client) }}" class="text-decoration-none">{{ $acc->client->name }}</a>
                            @else
                                <span class="text-muted fst-italic">Deleted client</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $acc->product->name ?? '—' }}</td>
                        <td class="text-end fw-semibold {{ round($acc->balance, 2) != 0 ? 'text-danger' : '' }}">{{ number_format($acc->balance, $dp) }}</td>
                        <td class="pe-3"><span class="badge badge-status-{{ $acc->status }}">{{ ucfirst($acc->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No open savings accounts found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            {{ $accounts->links() }}
            @if($accounts->count())
            <button type="submit" class="btn btn-danger" onclick="return confirm('Close the selected account(s)? This cannot be undone from here.')">
                <i class="bi bi-x-circle me-1"></i> Close Selected
            </button>
            @endif
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.getElementById('selectAll')?.addEventListener('change', function () {
    document.querySelectorAll('.account-checkbox:not(:disabled)').forEach(cb => cb.checked = this.checked);
});
</script>
@endpush
