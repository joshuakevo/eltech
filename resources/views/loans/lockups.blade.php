@extends('layouts.app')
@section('title', 'Lock-up Approvals')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
    <li class="breadcrumb-item active">Lock-up approvals</li>
@endsection
@section('content')
@php $svc = app(\App\Services\LoanLockupService::class); @endphp
<div class="mb-3">
    <h4 class="fw-bold mb-0">Lock-up Approvals</h4>
    <div class="text-muted small">A loan is locked up only after one {{ implode(' and one ', $roles) }} approve it — two different people, neither the one who asked.</div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white fw-semibold d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span>Waiting for approval <span class="badge bg-warning text-dark ms-1">{{ $pending->count() }}</span></span>
        @if($pending->count() && auth()->user()->hasRole('super_admin'))
        <form method="POST" action="{{ route('loans.lockups.remind-all') }}" onsubmit="return confirm('Email every approver who still has to approve a request?')">@csrf
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-envelope-exclamation me-1"></i>Remind all approvers</button>
        </form>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Loan</th><th>Member</th><th class="text-end">Owing</th><th>Lock-up date</th><th>Requested</th><th>Reason</th>@foreach($roles as $label)<th>{{ $label }}</th>@endforeach<th class="pe-3"></th></tr></thead>
            <tbody>
            @forelse($pending as $p)
                @php $approvals = collect($p->approvals ?? []); $mine = $svc->rolesUserCanApprove($p, auth()->user()); @endphp
                <tr>
                    <td class="ps-3 font-monospace"><a href="{{ route('loans.show', $p->loan) }}" class="text-decoration-none">{{ $p->loan->loan_number }}</a><div class="text-muted small">{{ $p->loan->product->name ?? '' }}</div></td>
                    <td>{{ $p->loan->client->name ?? '—' }}</td>
                    <td class="text-end small">P {{ number_format($p->loan->outstanding_principal, 0) }}<div class="text-muted">I {{ number_format($p->loan->outstanding_interest, 0) }}</div></td>
                    <td class="small">{{ $p->lockup_date->format('d M Y') }}</td>
                    <td class="small">{{ $p->requester->name ?? '—' }}<div class="text-muted">{{ $p->created_at->format('d M H:i') }}</div></td>
                    <td class="small" style="max-width:220px">{{ $p->reason }}</td>
                    @foreach($roles as $role => $label)
                        @php $a = $approvals->firstWhere('role', $role); @endphp
                        <td class="small">{!! $a ? '<i class="bi bi-check-circle-fill text-success"></i> ' . e($a['name']) : '<span class="text-muted"><i class="bi bi-hourglass-split"></i> waiting</span>' !!}</td>
                    @endforeach
                    <td class="pe-3 text-end text-nowrap">
                        @if($mine)
                            <form method="POST" action="{{ route('loans.lockups.approve', $p) }}" class="d-inline" onsubmit="return confirm('Approve locking up {{ $p->loan->loan_number }}?')">@csrf<button class="btn btn-success btn-sm">Approve</button></form>
                        @endif
                        @php $toRemind = auth()->user()->hasRole('super_admin') ? $svc->approversFor($p) : collect(); @endphp
                        @if($toRemind->isNotEmpty())
                            <form method="POST" action="{{ route('loans.lockups.remind', $p) }}" class="d-inline">@csrf
                                <button class="btn btn-outline-primary btn-sm" title="Email {{ $toRemind->pluck('name')->implode(', ') }}"><i class="bi bi-envelope"></i> Remind</button>
                            </form>
                        @endif
                        <a href="{{ route('loans.show', $p->loan) }}" class="btn btn-outline-secondary btn-sm">Open</a>
                        @if($p->reminded_at)<div class="text-muted" style="font-size:.7rem">emailed {{ $p->reminded_at->diffForHumans() }}</div>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ 7 + count($roles) }}" class="text-center text-muted py-4">Nothing waiting for approval.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">Recent decisions</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light"><tr><th class="ps-3">Loan</th><th>Member</th><th>Status</th><th>Requested by</th><th>Approved / rejected by</th><th class="pe-3">When</th></tr></thead>
            <tbody>
            @forelse($recent as $r)
                <tr>
                    <td class="ps-3 font-monospace"><a href="{{ route('loans.show', $r->loan_id) }}" class="text-decoration-none">{{ $r->loan->loan_number ?? '—' }}</a></td>
                    <td>{{ $r->loan->client->name ?? '—' }}</td>
                    <td><span class="badge bg-{{ ['executed' => 'danger', 'rejected' => 'secondary', 'cancelled' => 'light text-dark', 'reversed' => 'info text-dark'][$r->status] ?? 'secondary' }}">{{ $r->status === 'executed' ? 'Locked up' : ucfirst($r->status) }}</span></td>
                    <td>{{ $r->requester->name ?? '—' }}</td>
                    <td>
                        @if(in_array($r->status, ['rejected', 'cancelled']))
                            {{ $r->rejecter->name ?? '—' }}: <span class="text-muted">{{ $r->rejection_reason }}</span>
                        @else
                            {{ collect($r->approvals ?? [])->pluck('name')->implode(', ') }}
                        @endif
                    </td>
                    <td class="pe-3">{{ $r->updated_at->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-3">No decisions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
