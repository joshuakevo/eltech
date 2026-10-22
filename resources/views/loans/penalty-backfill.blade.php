@extends('layouts.app')
@section('title', 'Penalty catch-up')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loan-reports.penalties') }}">Loan Penalties</a></li>
    <li class="breadcrumb-item active">Catch-up since 31 Jul</li>
@endsection
@section('content')
<div class="page-header">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-clock-history me-1 text-danger"></i>Penalty catch-up: 31 Jul 2026 → today</h4>
        <div class="text-muted small">Installments due since the transfer that were not fully paid by their due date (paid late, Run Loans short, or still unpaid) and have no penalty yet. Each is charged its flat tier penalty, dated the day after the due date. Penalty is paid last.</div>
    </div>
    <a href="{{ route('loans.penalty-backfill', ['format' => 'excel']) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
</div>

@if($applied['count'] > 0)
<div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span>Already back-filled: <strong>{{ $applied['count'] }}</strong> penalties · {{ number_format($applied['total'], $dp) }} (paid so far {{ number_format($applied['paid'], $dp) }})</span>
    <form method="POST" action="{{ route('loans.penalty-backfill.undo') }}" class="d-flex gap-2" onsubmit="return confirm('Remove the back-filled penalties that have not been paid?')">
        @csrf @method('DELETE')
        <input name="confirm" class="form-control form-control-sm" placeholder="Type UNDO" required pattern="UNDO" style="width:120px">
        <button class="btn btn-sm btn-outline-danger">Undo catch-up</button>
    </form>
</div>
@endif

@if($overdue->isNotEmpty())
<div class="card mb-3 border-danger">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><strong>{{ $overdue->count() }}</strong> overdue-period penalties on <strong>{{ $overdue->pluck('loan.id')->unique()->count() }}</strong> loans past maturity · <strong>{{ number_format($overdue->sum('penalty'), $dp) }}</strong></span>
        @if($plan->isEmpty())
        <form method="POST" action="{{ route('loans.penalty-backfill.apply') }}" onsubmit="return confirm('Charge {{ $overdue->count() }} overdue-period penalties ({{ number_format($overdue->sum('penalty'), $dp) }})?')">@csrf<button class="btn btn-sm btn-danger"><i class="bi bi-check2-circle me-1"></i>Charge these penalties</button></form>
        @endif
    </div>
    <div class="card-body py-2 small text-muted">Loans that ran past maturity still owing: one penalty for each monthly due day after maturity where less than one installment was paid since the previous one.</div>
    <div class="table-responsive" style="max-height:50vh">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th class="ps-3">Loan</th><th>Member</th><th>Due day</th><th class="text-end">Paid in period</th><th class="text-end">Installment</th><th class="text-end pe-3">Penalty</th></tr></thead>
            <tbody class="small">
            @foreach($overdue as $r)
                <tr>
                    <td class="ps-3 font-monospace"><a href="{{ route('loans.show', $r->loan) }}">{{ $r->loan->loan_number }}</a> <span class="text-muted">matured {{ $r->loan->schedules->first()->due_date->format('d M Y') }}</span></td>
                    <td>{{ $r->loan->client->name ?? '' }} <span class="text-muted">{{ $r->loan->client->client_number ?? '' }}</span></td>
                    <td class="text-nowrap">{{ \Carbon\Carbon::parse($r->period)->format('d M Y') }}</td>
                    <td class="text-end">{{ number_format($r->paid, $dp) }}</td>
                    <td class="text-end">{{ number_format($r->installment, $dp) }}</td>
                    <td class="text-end pe-3 fw-semibold">{{ number_format($r->penalty, $dp) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><strong>{{ $plan->count() }}</strong> penalties on <strong>{{ $plan->pluck('loan.id')->unique()->count() }}</strong> loans · <strong>{{ number_format($plan->sum('penalty'), $dp) }}</strong></span>
        @if($plan->isNotEmpty())
        <form method="POST" action="{{ route('loans.penalty-backfill.apply') }}" onsubmit="return confirm('Charge {{ $plan->count() }} penalties ({{ number_format($plan->sum('penalty'), $dp) }})?')">
            @csrf
            <button class="btn btn-sm btn-danger"><i class="bi bi-check2-circle me-1"></i>Charge these penalties</button>
        </form>
        @endif
    </div>
    <div class="table-responsive" style="max-height:65vh">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light" style="position:sticky;top:0"><tr><th class="ps-3">Loan</th><th>Member</th><th class="text-end">Inst.</th><th>Due</th><th class="text-end">Installment</th><th>Paid off</th><th class="text-end pe-3">Penalty</th></tr></thead>
            <tbody class="small">
            @forelse($plan as $r)
                <tr>
                    <td class="ps-3 font-monospace"><a href="{{ route('loans.show', $r->loan) }}">{{ $r->loan->loan_number }}</a> <span class="text-muted">{{ $r->loan->status }}</span></td>
                    <td>{{ $r->loan->client->name ?? '' }} <span class="text-muted">{{ $r->loan->client->client_number ?? '' }}</span></td>
                    <td class="text-end">#{{ $r->schedule->installment_no }}</td>
                    <td class="text-nowrap">{{ \Carbon\Carbon::parse($r->due)->format('d M Y') }}</td>
                    <td class="text-end">{{ number_format($r->schedule->total_due, $dp) }}</td>
                    <td class="{{ $r->completed ? '' : 'text-danger' }}">{{ $r->reason }}</td>
                    <td class="text-end pe-3 fw-semibold">{{ number_format($r->penalty, $dp) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Nothing to charge.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
