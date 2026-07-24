@extends('layouts.app')
@section('title', $saving->account_number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('savings.index') }}">Savings</a></li>
    <li class="breadcrumb-item active">{{ $saving->account_number }}</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">{{ $saving->account_number }}</h4>
        <span class="text-muted">{{ $saving->client->name }} &bull; {{ $saving->product->name }}</span>
    </div>
    <div class="d-flex gap-2">
        @if($saving->status === 'active')
        @can('deposit savings')
        <a href="{{ route('savings.deposit-form', $saving) }}" class="btn btn-success btn-sm"><i class="bi bi-plus-circle"></i> Deposit</a>
        @endcan
        @can('withdraw savings')
        <a href="{{ route('savings.withdraw-form', $saving) }}" class="btn btn-warning btn-sm"><i class="bi bi-dash-circle"></i> Withdraw</a>
        @endcan
        @can('transfer savings')
        <a href="{{ route('savings.transfer-form', $saving) }}" class="btn btn-info btn-sm text-white"><i class="bi bi-arrow-left-right"></i> Transfer</a>
        @endcan
        @endif
        <a href="{{ route('savings.statement-pdf', $saving) }}" target="_blank" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-download me-1"></i> Export Statement
        </a>
        @can('deposit savings')
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-gear me-1"></i>Manage
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @if($saving->status !== 'active')
                <li>
                    <button class="dropdown-item text-success" data-bs-toggle="modal" data-bs-target="#reactivateModal">
                        <i class="bi bi-check-circle me-2"></i>Reactivate Account
                    </button>
                </li>
                @endif
                @if($saving->status === 'active')
                <li>
                    <button class="dropdown-item text-warning" data-bs-toggle="modal" data-bs-target="#dormantModal">
                        <i class="bi bi-moon me-2"></i>Mark as Dormant
                    </button>
                </li>
                <li>
                    <button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#closeModal">
                        <i class="bi bi-lock me-2"></i>Close Account
                    </button>
                </li>
                @endif
                @role('super_admin')
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-trash me-2"></i>Delete Account
                    </button>
                </li>
                @endrole
            </ul>
        </div>
        @endcan
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show py-2">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show py-2">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Current Balance</div>
            <div class="fw-bold fs-4 text-primary">{{ number_format($saving->balance, $dp) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Min Balance</div>
            <div class="fw-bold fs-5">{{ number_format($saving->product->minimum_balance, $dp) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Interest Rate</div>
            <div class="fw-bold fs-5">{{ $saving->product->interest_rate }}% / yr</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Status</div>
            <div class="fw-bold fs-5"><span class="badge badge-status-{{ $saving->status }}">{{ ucfirst($saving->status) }}</span></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Transaction History</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-3">Date</th><th>Type</th><th>Receipt / Ref</th><th>Description</th>
                <th class="text-end">Charge</th><th class="text-end">Amount</th><th class="text-end pe-3">Balance</th>
            </tr></thead>
            <tbody>
            @forelse($saving->transactions as $txn)
                <tr>
                    <td class="ps-3">{{ $txn->transaction_date->format('d M Y') }}</td>
                    <td>
                        @php $types = ['deposit'=>'success','withdrawal'=>'danger','interest'=>'info','fee'=>'warning','loan_repayment'=>'secondary','transfer'=>'primary']; @endphp
                        <span class="badge bg-{{ $types[$txn->transaction_type] ?? 'secondary' }} bg-opacity-10 text-{{ $types[$txn->transaction_type] ?? 'secondary' }}">
                            {{ ucfirst(str_replace('_',' ',$txn->transaction_type)) }}
                        </span>
                    </td>
                    <td class="font-monospace small">{{ $txn->reference ?? '—' }}</td>
                    <td class="small">{{ $txn->description }}</td>
                    <td class="text-end small text-muted">{{ $txn->charge_amount > 0 ? number_format($txn->charge_amount, $dp) : '—' }}</td>
                    <td class="text-end fw-semibold {{ in_array($txn->transaction_type, ['deposit','interest']) ? 'text-success' : 'text-danger' }}">
                        {{ in_array($txn->transaction_type, ['deposit','interest']) ? '+' : '-' }}{{ number_format($txn->amount, $dp) }}
                    </td>
                    <td class="text-end pe-3">{{ number_format($txn->balance_after, $dp) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No transactions.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Mark Dormant Modal --}}
@can('deposit savings')
@if($saving->status === 'active')
<div class="modal fade" id="dormantModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('savings.dormant', $saving) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-moon me-2 text-warning"></i>Mark Account as Dormant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Account: <strong class="font-monospace">{{ $saving->account_number }}</strong></p>
                    <p class="text-muted small mb-0">Dormant accounts cannot accept deposits, withdrawals, or transfers. You can reactivate at any time.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm"><i class="bi bi-moon me-1"></i>Mark Dormant</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Close Account Modal --}}
@if($saving->status === 'active')
<div class="modal fade" id="closeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('savings.close', $saving) }}">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-lock me-2"></i>Close Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if(round($saving->balance, 2) != 0)
                    <div class="alert alert-danger py-2 mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Balance must be <strong>zero</strong> before closing. Current balance: <strong>{{ number_format($saving->balance, 2) }}</strong>.
                        Please withdraw or transfer the funds first.
                    </div>
                    @else
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Closing this account will stop all transactions. This can be reversed by reactivating.
                    </div>
                    @endif
                    <p class="mb-0">Account: <strong class="font-monospace">{{ $saving->account_number }}</strong> &mdash; {{ $saving->client->name }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm" @if(round($saving->balance, 2) != 0) disabled @endif>
                        <i class="bi bi-lock me-1"></i>Close Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Reactivate Modal --}}
@if($saving->status !== 'active')
<div class="modal fade" id="reactivateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('savings.reactivate', $saving) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-check-circle me-2 text-success"></i>Reactivate Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Account: <strong class="font-monospace">{{ $saving->account_number }}</strong> &mdash; {{ $saving->client->name }}</p>
                    <p class="text-muted small mb-0">The account status will be set back to <strong>Active</strong>, allowing deposits, withdrawals, and transfers.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-circle me-1"></i>Reactivate</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endcan

{{-- Delete Modal (super_admin only) --}}
@role('super_admin')
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('savings.destroy', $saving) }}">
                @csrf
                @method('DELETE')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Delete Savings Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if(round($saving->balance, 2) != 0 || $saving->transactions->isNotEmpty())
                    <div class="alert alert-danger py-2 mb-3">
                        <i class="bi bi-x-circle me-1"></i>
                        <strong>Cannot delete:</strong>
                        @if(round($saving->balance, 2) != 0)
                            Account has a non-zero balance ({{ number_format($saving->balance, 2) }}).
                        @elseif($saving->transactions->isNotEmpty())
                            Account has transaction history ({{ $saving->transactions->count() }} transaction(s)).
                        @endif
                    </div>
                    @else
                    <div class="alert alert-danger py-2 mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Warning:</strong> This permanently removes the account record. This action cannot be undone.
                    </div>
                    @endif
                    <p class="mb-0">Account: <strong class="font-monospace">{{ $saving->account_number }}</strong> &mdash; {{ $saving->client->name }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm"
                        @if(round($saving->balance, 2) != 0 || $saving->transactions->isNotEmpty()) disabled @endif>
                        <i class="bi bi-trash me-1"></i>Delete Permanently
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endrole

@endsection
