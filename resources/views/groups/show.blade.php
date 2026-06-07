@extends('layouts.app')
@section('title', $group->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('groups.index') }}">Groups</a></li>
    <li class="breadcrumb-item active">{{ $group->name }}</li>
@endsection
@section('content')
@php $dp = 0; @endphp
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">{{ $group->name }}</h4>
        <div class="text-muted small font-monospace">{{ $group->group_number }} &bull; Registered {{ $group->registration_date->format('d M Y') }}</div>
    </div>
    @can('manage groups')
    <div class="d-flex flex-wrap gap-2 align-items-center">
        @if($group->isPooled())
            <span class="badge bg-success me-1" style="font-size:.75rem"><i class="bi bi-safe2-fill me-1"></i>Pooled Group</span>
            <a href="{{ route('groups.contribute', $group) }}" class="btn btn-success btn-sm"><i class="bi bi-plus-circle me-1"></i> Record Contribution</a>
            <a href="{{ route('groups.pool-withdraw', $group) }}" class="btn btn-outline-danger btn-sm"><i class="bi bi-dash-circle me-1"></i> Pool Withdrawal</a>
        @else
            <a href="{{ route('groups.deposit', $group) }}" class="btn btn-success btn-sm"><i class="bi bi-plus-circle me-1"></i> Deposit</a>
            <a href="{{ route('groups.withdraw', $group) }}" class="btn btn-outline-danger btn-sm"><i class="bi bi-dash-circle me-1"></i> Withdraw</a>
            <a href="{{ route('groups.interest', $group) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-percent me-1"></i> Post Interest</a>
        @endif
        <a href="{{ route('groups.edit', $group) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i> Edit</a>
    </div>
    @endcan
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
    @if($group->isPooled())
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Group Pool Balance</div>
            <div class="stat-value text-success">{{ number_format($group->pool_balance, $dp) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Total Contributed</div>
            <div class="stat-value text-primary">{{ number_format($members->sum('balance'), $dp) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Expected / {{ ucfirst($group->contribution_cycle ?? 'cycle') }}</div>
            <div class="stat-value">{{ number_format($group->expected_contribution, $dp) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Active Members</div>
            <div class="stat-value">{{ $members->where('status','active')->count() }}</div>
        </div>
    </div>
    @else
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Total Balance</div>
            <div class="stat-value text-primary">{{ number_format($members->sum('balance'), $dp) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Active Members</div>
            <div class="stat-value">{{ $members->where('status','active')->count() }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Monthly Rate</div>
            <div class="stat-value">{{ (int) $group->monthly_interest_rate }}%</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Membership Fee</div>
            <div class="stat-value">{{ number_format($group->membership_fee, $dp) }}</div>
        </div>
    </div>
    @endif
</div>

{{-- Members table --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Members</span>
        @can('manage groups')
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
            <i class="bi bi-person-plus me-1"></i> Add Member
        </button>
        @endcan
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th class="ps-3">Name</th>
                    <th>Phone</th>
                    @if($group->isPooled())
                        <th class="text-end">Total Contributed</th>
                        <th class="text-end">This {{ ucfirst($group->contribution_cycle ?? 'cycle') }}</th>
                        <th>Payment Status</th>
                        <th>Last Payment</th>
                    @else
                        <th>Email</th>
                        <th class="text-end">Balance</th>
                    @endif
                    <th>Role</th>
                    <th>Portal</th>
                    <th>Status</th>
                    @can('manage groups')<th class="pe-3"></th>@endcan
                </tr>
            </thead>
            <tbody>
            @foreach($members as $m)
            <tr>
                <td class="ps-3 fw-semibold">{{ $m->name }}</td>
                <td class="text-muted">{{ $m->phone ?? '—' }}</td>
                @if($group->isPooled())
                    @php $cs = $memberContribStatus[$m->id] ?? null; @endphp
                    <td class="text-end fw-semibold text-primary">{{ number_format($m->balance, $dp) }}</td>
                    <td class="text-end fw-semibold">{{ $cs ? number_format($cs['paid_this_cycle'], $dp) : '—' }}</td>
                    <td>
                        @if($cs)
                            @if($cs['status'] === 'paid')
                                <span class="badge bg-success">Paid</span>
                            @elseif($cs['status'] === 'partial')
                                <span class="badge bg-warning text-dark">Partial</span>
                            @else
                                <span class="badge bg-danger">Unpaid</span>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-muted small">{{ $cs && $cs['last_payment'] ? \Carbon\Carbon::parse($cs['last_payment'])->format('d M Y') : '—' }}</td>
                @else
                    <td class="text-muted small">{{ $m->user?->email ?? $m->email ?? '—' }}</td>
                    <td class="text-end fw-semibold {{ $m->balance > 0 ? 'text-primary' : 'text-muted' }}">
                        {{ number_format($m->balance, $dp) }}
                    </td>
                @endif
                <td>
                    @if($m->is_leader)
                    <span class="badge bg-primary">Leader</span>
                    @else
                    <span class="badge bg-light text-dark">Member</span>
                    @endif
                </td>
                <td>
                    @if($m->user_id)
                    <span class="badge bg-success-subtle text-success">Portal active</span>
                    @else
                    <span class="text-muted small">—</span>
                    @endif
                </td>
                <td>
                    <span class="badge {{ $m->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                        {{ ucfirst($m->status) }}
                    </span>
                </td>
                @can('manage groups')
                <td class="text-end pe-3">
                    <a href="{{ route('groups.members.statement', [$group, $m]) }}" class="btn btn-sm btn-outline-info" title="View statement">
                        <i class="bi bi-receipt"></i>
                    </a>
                    @if($m->user_id && $m->user)
                    <button type="button" class="btn btn-sm btn-outline-success member-reset-btn"
                        title="Send password reset link"
                        data-action="{{ route('groups.members.send-reset', [$group, $m]) }}"
                        data-name="{{ $m->name }}"
                        data-email="{{ $m->user->email }}">
                        <i class="bi bi-envelope-arrow-up"></i>
                    </button>
                    @endif
                    @if((float)$m->balance > 0)
                    <button type="button" class="btn btn-sm btn-outline-primary"
                        title="Transfer to savings account"
                        data-bs-toggle="modal" data-bs-target="#transferModal{{ $m->id }}">
                        <i class="bi bi-arrow-right-square"></i>
                    </button>
                    @endif
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editMember{{ $m->id }}">Edit</button>
                    @if((float)$m->balance <= 0.001)
                    <form method="POST" action="{{ route('groups.members.destroy', [$group, $m]) }}" class="d-inline" onsubmit="return confirm('Remove {{ addslashes($m->name) }}?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Remove</button>
                    </form>
                    @endif
                </td>
                @endcan
            </tr>
            @endforeach
            </tbody>
            <tfoot class="table-secondary fw-bold small">
                <tr>
                    @if($group->isPooled())
                        <td class="ps-3" colspan="2">Totals ({{ $members->where('status','active')->count() }} active)</td>
                        <td class="text-end text-primary">{{ number_format($members->sum('balance'), $dp) }}</td>
                        <td colspan="{{ auth()->user()->can('manage groups') ? 6 : 5 }}">
                            <span class="text-muted fw-normal">Pool Balance:</span>
                            <strong class="text-success ms-1">{{ number_format($group->pool_balance, $dp) }}</strong>
                        </td>
                    @else
                        <td class="ps-3" colspan="3">Totals ({{ $members->where('status','active')->count() }} active)</td>
                        <td class="text-end text-primary">{{ number_format($members->sum('balance'), $dp) }}</td>
                        <td colspan="{{ auth()->user()->can('manage groups') ? 4 : 3 }}"></td>
                    @endif
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Transactions --}}
<div class="card">
    <div class="card-header">Recent Transactions</div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th class="ps-3">Date</th>
                    <th>Type</th>
                    <th>Mode</th>
                    <th>Member</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end pe-3">{{ $group->isPooled() ? 'Pool Balance After' : 'Balance After' }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse($transactions as $tx)
            <tr>
                <td class="ps-3">{{ $tx->transaction_date->format('d M Y') }}</td>
                <td>
                    <span class="badge {{ $tx->type === 'deposit' ? 'bg-success' : ($tx->type === 'withdrawal' ? 'bg-danger' : ($tx->type === 'interest' ? 'bg-primary' : 'bg-secondary')) }}">
                        {{ ucfirst($tx->type) }}
                    </span>
                </td>
                <td class="text-muted small">{{ ucwords(str_replace('_', ' ', $tx->posting_type)) }}</td>
                <td>{{ $tx->member?->name ?? '—' }}</td>
                <td class="text-end font-monospace {{ $tx->type === 'withdrawal' ? 'text-danger' : 'text-success' }}">
                    {{ number_format($tx->amount, $dp) }}
                </td>
                <td class="text-end pe-3 font-monospace">{{ number_format($tx->balance_after, $dp) }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-3">No transactions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer">{{ $transactions->withQueryString()->links() }}</div>
    @endif
</div>

@can('manage groups')
{{-- Add Member Modal --}}
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('groups.members.store', $group) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Add Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                    </div>
                    <div class="col">
                        <label class="form-label fw-semibold">National ID</label>
                        <input type="text" name="national_id" class="form-control" value="{{ old('national_id') }}">
                    </div>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" name="is_leader" value="1" class="form-check-input" id="isLeaderNew">
                    <label class="form-check-label" for="isLeaderNew">Group Leader</label>
                </div>
                <hr>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Portal Email <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="email" name="portal_email" class="form-control" autocomplete="off" placeholder="member@email.com">
                    <div class="form-text"><i class="bi bi-info-circle me-1"></i>A portal account will be created with default password <strong>Welcome@123</strong>. You can reset it after.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Member</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Member Modals --}}
@foreach($members as $m)
<div class="modal fade" id="editMember{{ $m->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('groups.members.update', [$group, $m]) }}" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Edit: {{ $m->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="{{ $m->name }}">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="{{ $m->phone }}">
                    </div>
                    <div class="col">
                        <label class="form-label">National ID</label>
                        <input type="text" name="national_id" class="form-control" value="{{ $m->national_id }}">
                    </div>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" name="is_leader" value="1" class="form-check-input" id="isLeader{{ $m->id }}" {{ $m->is_leader ? 'checked' : '' }}>
                    <label class="form-check-label" for="isLeader{{ $m->id }}">Group Leader</label>
                </div>
                <hr>
                <p class="small text-muted mb-2">Portal login @if($m->user_id)<span class="badge bg-success-subtle text-success ms-1">Active</span>@endif</p>
                <div class="mb-2">
                    <label class="form-label">Portal Email</label>
                    <input type="email" name="portal_email" class="form-control" value="{{ $m->user?->email }}" autocomplete="off">
                </div>
                <div class="mb-2">
                    <label class="form-label">New Password <small class="text-muted">(leave blank to keep)</small></label>
                    <input type="password" name="portal_password" class="form-control" autocomplete="new-password">
                </div>
                <div class="mb-2">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="portal_password_confirmation" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>
@endforeach
@endcan

{{-- Transfer to Savings Modals --}}
@can('manage groups')
@foreach($members as $m)
@if((float)$m->balance > 0)
<div class="modal fade" id="transferModal{{ $m->id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('groups.members.transfer-to-savings', [$group, $m]) }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-arrow-right-square text-primary me-2"></i>Transfer to Savings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="p-2 bg-light rounded mb-3 small">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Member</span><strong>{{ $m->name }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Group Balance</span>
                            <strong class="text-success">{{ number_format($m->balance, 2) }}</strong>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Target Savings Account <span class="text-danger">*</span></label>
                        <select name="savings_account_id" class="form-select" required>
                            <option value="">— Select account —</option>
                            @foreach($allSavingsAccounts as $sa)
                            <option value="{{ $sa->id }}">{{ $sa->account_number }} — {{ $sa->client->name ?? '?' }} — {{ $sa->product->name }} (Bal: {{ number_format($sa->balance, 0) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01" max="{{ $m->balance }}" required placeholder="0.00">
                        <div class="form-text">Available: {{ number_format($m->balance, 2) }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Transfer Date <span class="text-danger">*</span></label>
                        <input type="date" name="transfer_date" class="form-control" value="{{ today()->toDateString() }}" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional note">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-arrow-right-square me-1"></i>Transfer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endforeach
@endcan

{{-- Member Reset Link Modal --}}
<div class="modal fade" id="memberResetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-envelope-arrow-up text-success me-2"></i>Send Reset Link</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="resetConfirmBody">
                    <p class="mb-1">Send a password reset link to:</p>
                    <div class="d-flex align-items-center gap-2 p-2 bg-light rounded mb-3">
                        <i class="bi bi-person-circle fs-4 text-muted"></i>
                        <div>
                            <div class="fw-semibold" id="resetMemberName"></div>
                            <div class="text-muted small" id="resetMemberEmail"></div>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">The member will receive a link to set a new password and access their portal.</p>
                </div>
                <div id="resetSendingBody" class="d-none text-center py-3">
                    <div class="spinner-border text-success mb-3" role="status" style="width:2.5rem;height:2.5rem"></div>
                    <div class="fw-semibold">Sending…</div>
                    <div class="text-muted small">Please wait</div>
                </div>
                <div id="resetResultBody" class="d-none text-center py-2">
                    <div id="resetResultIcon" class="fs-1 mb-2"></div>
                    <div id="resetResultMsg" class="fw-semibold"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0" id="resetFooter">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success px-4" id="resetSendBtn">
                    <i class="bi bi-send me-1"></i>Send Reset Link
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    let resetAction = '';
    const modal       = new bootstrap.Modal(document.getElementById('memberResetModal'));
    const confirmBody = document.getElementById('resetConfirmBody');
    const sendingBody = document.getElementById('resetSendingBody');
    const resultBody  = document.getElementById('resetResultBody');
    const footer      = document.getElementById('resetFooter');

    function resetModal() {
        confirmBody.classList.remove('d-none');
        sendingBody.classList.add('d-none');
        resultBody.classList.add('d-none');
        footer.innerHTML = `
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success px-4" id="resetSendBtn">
                <i class="bi bi-send me-1"></i>Send Reset Link
            </button>`;
        document.getElementById('resetSendBtn').addEventListener('click', doSend);
    }

    document.querySelectorAll('.member-reset-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            resetAction = btn.dataset.action;
            document.getElementById('resetMemberName').textContent  = btn.dataset.name;
            document.getElementById('resetMemberEmail').textContent = btn.dataset.email;
            resetModal();
            modal.show();
        });
    });

    async function doSend() {
        confirmBody.classList.add('d-none');
        sendingBody.classList.remove('d-none');

        try {
            const res  = await fetch(resetAction, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                }
            });
            const data = await res.json();
            sendingBody.classList.add('d-none');
            resultBody.classList.remove('d-none');
            document.getElementById('resetResultIcon').innerHTML = data.success
                ? '<i class="bi bi-check-circle-fill text-success"></i>'
                : '<i class="bi bi-x-circle-fill text-danger"></i>';
            document.getElementById('resetResultMsg').textContent = data.message;
        } catch (e) {
            sendingBody.classList.add('d-none');
            resultBody.classList.remove('d-none');
            document.getElementById('resetResultIcon').innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
            document.getElementById('resetResultMsg').textContent = 'Network error. Please try again.';
        }

        footer.innerHTML = '<button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>';
    }

    document.getElementById('resetSendBtn').addEventListener('click', doSend);
})();
</script>
@endpush
@endsection
