@extends('layouts.app')
@section('title', 'Financial Periods')
@section('breadcrumb')
    <li class="breadcrumb-item active">Financial Periods</li>
@endsection
@section('content')

<div class="mb-3">
    <h4 class="fw-bold mb-0">Financial Periods</h4>
    <p class="text-muted small mb-0">Control which periods are open or closed for postings.</p>
</div>

{{-- Year-level actions --}}
@php $openCount = $periods->where('status','open')->count(); $closedCount = $periods->where('status','closed')->count(); @endphp
<div class="card mb-4">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            {{-- Year selector --}}
            <form method="GET" class="mb-0">
                <select name="year" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto">
                    @foreach($years as $y)
                        <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                    @endforeach
                    <option value="{{ $year - 1 }}" @selected(false)>{{ $year - 1 }}</option>
                    <option value="{{ $year + 1 }}" @selected(false)>{{ $year + 1 }}</option>
                </select>
            </form>
            <div>
                <div class="fw-bold">Financial Year {{ $year }}</div>
                <div class="text-muted small">
                    {{ $openCount }} open &bull; {{ $closedCount }} closed
                    @if($allClosed)
                        &bull; <span class="text-danger fw-semibold">All periods closed</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            @if(!$allClosed)
            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#closeYearModal">
                <i class="bi bi-lock-fill me-1"></i>Close Year {{ $year }}
            </button>
            @else
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#reopenYearModal">
                <i class="bi bi-unlock-fill me-1"></i>Reopen Year {{ $year }}
            </button>
            @endif
        </div>
    </div>
</div>

{{-- Month grid --}}
<div class="row g-3">
@foreach($periods as $period)
@php
    $isCurrent = ($period->year == now()->year && $period->month == now()->month);
    $isFuture  = Carbon\Carbon::create($period->year, $period->month, 1)->isFuture();
    $label     = Carbon\Carbon::create($period->year, $period->month, 1)->format('F');
@endphp
<div class="col-sm-6 col-md-4 col-lg-3">
    <div class="card h-100 {{ $period->status === 'closed' ? 'border-danger' : ($isCurrent ? 'border-primary' : '') }}"
         style="{{ $period->status === 'closed' ? 'border-width:2px' : ($isCurrent ? 'border-width:2px' : '') }}">
        <div class="card-body p-3">
            <div class="d-flex align-items-start justify-content-between mb-2">
                <div>
                    <div class="fw-bold">{{ $label }}</div>
                    <div class="text-muted small">{{ $period->year }}</div>
                </div>
                <div class="d-flex flex-column align-items-end gap-1">
                    @if($period->status === 'closed')
                        <span class="badge bg-danger"><i class="bi bi-lock-fill me-1"></i>Closed</span>
                    @else
                        <span class="badge bg-success"><i class="bi bi-unlock-fill me-1"></i>Open</span>
                    @endif
                    @if($isCurrent)
                        <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size:.65rem">Current</span>
                    @endif
                </div>
            </div>

            @if($period->status === 'closed' && $period->closedAt)
            <div class="text-muted" style="font-size:.72rem">
                Closed {{ $period->closed_at->format('d M Y') }}
                @if($period->closedBy) by {{ $period->closedBy->name }} @endif
            </div>
            @elseif($period->status === 'open' && $period->reopened_at)
            <div class="text-muted" style="font-size:.72rem">
                Reopened {{ $period->reopened_at->format('d M Y') }}
            </div>
            @endif

            @if($period->notes)
            <div class="text-muted fst-italic mt-1" style="font-size:.72rem">{{ Str::limit($period->notes, 60) }}</div>
            @endif

            <div class="mt-3">
                @if($period->status === 'open')
                <button class="btn btn-outline-danger btn-sm w-100"
                    data-bs-toggle="modal"
                    data-bs-target="#closePeriodModal"
                    data-period-id="{{ $period->id }}"
                    data-period-label="{{ $label }} {{ $period->year }}">
                    <i class="bi bi-lock me-1"></i>Close Period
                </button>
                @else
                <button class="btn btn-outline-success btn-sm w-100"
                    data-bs-toggle="modal"
                    data-bs-target="#reopenPeriodModal"
                    data-period-id="{{ $period->id }}"
                    data-period-label="{{ $label }} {{ $period->year }}">
                    <i class="bi bi-unlock me-1"></i>Reopen Period
                </button>
                @endif
            </div>
        </div>
    </div>
</div>
@endforeach
</div>

{{-- Close Period Modal --}}
<div class="modal fade" id="closePeriodModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="closePeriodForm" class="modal-content">
            @csrf
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-lock-fill text-danger me-2"></i>Close Period</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>You are about to close <strong id="closePeriodLabel"></strong>.</p>
                <div class="alert alert-warning py-2 small">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Once closed, no transactions can be posted to this period until it is reopened.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Month-end reconciliation complete"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger px-4"><i class="bi bi-lock me-1"></i>Close Period</button>
            </div>
        </form>
    </div>
</div>

{{-- Reopen Period Modal --}}
<div class="modal fade" id="reopenPeriodModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="reopenPeriodForm" class="modal-content">
            @csrf
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-unlock-fill text-success me-2"></i>Reopen Period</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>You are about to reopen <strong id="reopenPeriodLabel"></strong>.</p>
                <div class="alert alert-info py-2 small">
                    <i class="bi bi-info-circle me-1"></i>
                    Postings will be allowed in this period again. Ensure this is intentional.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Correction needed for prior period"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success px-4"><i class="bi bi-unlock me-1"></i>Reopen Period</button>
            </div>
        </form>
    </div>
</div>

{{-- Close Year Modal --}}
<div class="modal fade" id="closeYearModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('periods.year.close', $year) }}" class="modal-content">
            @csrf
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar-x-fill text-danger me-2"></i>Close Financial Year {{ $year }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger py-2 small">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    This will close all <strong>{{ $openCount }}</strong> open period(s) in {{ $year }}. No transactions can be posted to any month in this year.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Year-end close {{ $year }}"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger px-4"><i class="bi bi-lock-fill me-1"></i>Close Year {{ $year }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Reopen Year Modal --}}
<div class="modal fade" id="reopenYearModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('periods.year.reopen', $year) }}" class="modal-content">
            @csrf
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar-check-fill text-success me-2"></i>Reopen Financial Year {{ $year }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning py-2 small">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    This will reopen all <strong>{{ $closedCount }}</strong> closed period(s) in {{ $year }}.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success px-4"><i class="bi bi-unlock-fill me-1"></i>Reopen Year {{ $year }}</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('[data-bs-target="#closePeriodModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('closePeriodLabel').textContent = btn.dataset.periodLabel;
        document.getElementById('closePeriodForm').action = '/periods/' + btn.dataset.periodId + '/close';
    });
});
document.querySelectorAll('[data-bs-target="#reopenPeriodModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('reopenPeriodLabel').textContent = btn.dataset.periodLabel;
        document.getElementById('reopenPeriodForm').action = '/periods/' + btn.dataset.periodId + '/reopen';
    });
});
</script>
@endpush
@endsection
