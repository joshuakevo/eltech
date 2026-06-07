@extends('layouts.group-portal')
@section('title', 'All Members')
@section('content')

<div class="mb-4 d-flex align-items-center justify-content-between">
    <div>
        <h5 class="fw-bold mb-0">All Members</h5>
        <div class="text-muted small">{{ $members->count() }} members &bull; Total balance: UGX {{ number_format($members->sum('balance'), 0) }}</div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th class="ps-3">#</th>
                    <th>Member</th>
                    <th>Phone</th>
                    <th>Last Deposit</th>
                    <th class="text-end">Total Deposited</th>
                    <th class="text-end">Balance</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($members as $i => $m)
            <tr>
                <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                <td class="fw-semibold">{{ $m->name }}</td>
                <td class="text-muted small">{{ $m->phone ?? '—' }}</td>
                <td class="small">
                    @if(isset($lastDepositByMember[$m->id]))
                        {{ \Carbon\Carbon::parse($lastDepositByMember[$m->id])->format('d M Y') }}
                        <div class="text-muted" style="font-size:.68rem">{{ \Carbon\Carbon::parse($lastDepositByMember[$m->id])->diffForHumans() }}</div>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="text-end font-monospace text-muted small">
                    {{ isset($totalDeposited[$m->id]) ? number_format($totalDeposited[$m->id], 0) : '—' }}
                </td>
                <td class="text-end fw-bold font-monospace {{ $m->balance > 0 ? 'text-primary' : 'text-muted' }}">
                    {{ number_format($m->balance, 0) }}
                </td>
                <td>
                    @if($m->is_leader)
                        <span class="badge bg-primary">Leader</span>
                    @else
                        <span class="badge bg-light text-dark border">Member</span>
                    @endif
                </td>
                <td>
                    <span class="badge {{ $m->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                        {{ ucfirst($m->status) }}
                    </span>
                </td>
                <td>
                    <a href="{{ route('group-portal.leader.member-statement', $m) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye me-1"></i>Statement
                    </a>
                </td>
            </tr>
            @endforeach
            </tbody>
            <tfoot class="table-secondary fw-semibold">
                <tr>
                    <td colspan="5" class="ps-3">Totals</td>
                    <td class="text-end font-monospace text-primary">{{ number_format($members->sum('balance'), 0) }}</td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
