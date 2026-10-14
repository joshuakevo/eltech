{{-- Four-eyes lock-up: request button + modal, pending approvals banner, completed lock-up banner. --}}
@php
    $lockupSvc = app(\App\Services\LoanLockupService::class);
    $lockupRoles = $lockupSvc->approverRoles();
    $pendingLockup = \App\Models\LoanLockup::with('requester')->where('loan_id', $loan->id)->where('status', 'pending')->latest('id')->first();
    $doneLockup = \App\Models\LoanLockup::where('loan_id', $loan->id)->where('status', 'executed')->latest('id')->first();
    $canRequestLockup = !$loan->isLockedUp() && in_array($loan->status, ['active', 'defaulted'], true) && !$pendingLockup && auth()->user()->can('initiate loan lockup');
@endphp

@if($pendingLockup)
    @php
        $approvals = collect($pendingLockup->approvals ?? []);
        $myRoles = $lockupSvc->rolesUserCanApprove($pendingLockup, auth()->user());
        $canReject = collect($lockupRoles)->keys()->contains(fn ($r) => auth()->user()->hasRole($r)) || (int) $pendingLockup->requested_by === (int) auth()->id();
    @endphp
    <div class="alert alert-warning border-warning">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div>
                <div class="fw-semibold"><i class="bi bi-lock me-1"></i>Lock-up awaiting approval</div>
                <div class="small">Requested by <b>{{ $pendingLockup->requester->name ?? '—' }}</b> on {{ $pendingLockup->created_at->format('d M Y H:i') }}
                    · lock-up date <b>{{ $pendingLockup->lockup_date->format('d M Y') }}</b> · “{{ $pendingLockup->reason }}”</div>
                <div class="small mt-1 d-flex flex-wrap gap-3">
                    @foreach($lockupRoles as $role => $label)
                        @php $a = $approvals->firstWhere('role', $role); @endphp
                        <span>{!! $a ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-hourglass-split text-secondary"></i>' !!}
                            {{ $label }}: {{ $a ? $a['name'] . ' · ' . \Carbon\Carbon::parse($a['at'])->format('d M H:i') : 'waiting' }}</span>
                    @endforeach
                </div>
            </div>
            <div class="d-flex gap-2 align-items-start">
                @if($myRoles)
                    <form method="POST" action="{{ route('loans.lockups.approve', $pendingLockup) }}" onsubmit="return confirm('Approve locking up {{ $loan->loan_number }} as {{ $lockupRoles[$myRoles[0]] }}?')">
                        @csrf
                        <button class="btn btn-success btn-sm"><i class="bi bi-check2-circle me-1"></i>Approve as {{ $lockupRoles[$myRoles[0]] }}</button>
                    </form>
                @endif
                @if($canReject)
                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectLockupModal">
                        {{ (int) $pendingLockup->requested_by === (int) auth()->id() && !collect($lockupRoles)->keys()->contains(fn ($r) => auth()->user()->hasRole($r)) ? 'Withdraw request' : 'Reject' }}
                    </button>
                @endif
            </div>
        </div>
    </div>
    @if($canReject)
    <div class="modal fade" id="rejectLockupModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('loans.lockups.reject', $pendingLockup) }}" class="modal-content">
                @csrf
                <div class="modal-header"><h6 class="modal-title fw-bold">Reject / withdraw lock-up — {{ $loan->loan_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label small fw-semibold">Reason</label>
                    <input type="text" name="reason" class="form-control" required maxlength="500" placeholder="e.g. Member agreed a repayment plan">
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Reject request</button></div>
            </form>
        </div>
    </div>
    @endif
@endif

@if($doneLockup && $loan->isLockedUp())
    <div class="alert alert-light border d-flex flex-wrap justify-content-between align-items-center gap-2 small">
        <span><i class="bi bi-lock-fill me-1 text-danger"></i>Locked up on {{ $doneLockup->lockup_date->format('d M Y') }}
            (requested by {{ optional(\App\Models\User::find($doneLockup->requested_by))->name ?? '—' }}; approved by
            {{ collect($doneLockup->approvals ?? [])->map(fn ($a) => $a['name'] . ' (' . ($lockupRoles[$a['role']] ?? $a['role']) . ')')->implode(' and ') }}).
            Principal {{ number_format($doneLockup->principal, 0) }} · interest owed {{ number_format($doneLockup->interest, 0) }}.</span>
        @can('correct loans')
        <form method="POST" action="{{ route('loans.lockups.undo', $doneLockup) }}" onsubmit="return confirm('Undo the lock-up and return {{ $loan->loan_number }} to the normal loans?')">
            @csrf
            <button class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-arrow-counterclockwise me-1"></i>Undo lock-up</button>
        </form>
        @endcan
    </div>
@endif

@if($canRequestLockup)
<div class="modal fade" id="lockupLoanModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('loans.lockups.store', $loan) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h6 class="modal-title fw-bold"><i class="bi bi-lock me-1 text-danger"></i>Lock up {{ $loan->loan_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-light border small">
                    This sends a request for approval. The loan is only locked up after
                    <b>one {{ implode('</b> and <b>one ', $lockupRoles) }}</b> approve it (two different people, not you).
                    Then interest is charged up to the lock-up date and kept as interest owed, the principal moves to Locked-Up Loans Receivable,
                    the schedule is removed and Run Loans stops charging it.
                </div>
                <div class="row g-2">
                    <div class="col-5">
                        <label class="form-label small fw-semibold">Lock-up date</label>
                        <input type="date" name="lockup_date" class="form-control" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                    </div>
                    <div class="col-7">
                        <label class="form-label small fw-semibold">Owing now</label>
                        <div class="form-control-plaintext small">P {{ number_format($loan->outstanding_principal, 0) }} · I {{ number_format($loan->outstanding_interest, 0) }}</div>
                    </div>
                </div>
                <label class="form-label small fw-semibold mt-2">Reason</label>
                <textarea name="reason" class="form-control" rows="2" required maxlength="500" placeholder="Why should this loan be locked up?"></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger"><i class="bi bi-send me-1"></i>Send for approval</button></div>
        </form>
    </div>
</div>
@endif
