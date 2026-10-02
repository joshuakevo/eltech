@extends('layouts.app')
@php $specific = $run->provision_type === 'specific'; @endphp
@section('title', ucfirst($run->provision_type) . ' Provision — ' . $run->as_at_date->format('d M Y'))
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
    <li class="breadcrumb-item"><a href="{{ route('loan-provisions.index', ['type' => $run->provision_type]) }}">{{ ucfirst($run->provision_type) }} Provisions</a></li>
    <li class="breadcrumb-item active">{{ $run->as_at_date->format('d M Y') }}</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">{{ ucfirst($run->provision_type) }} Provision — as at {{ $run->as_at_date->format('d M Y') }}</h4>
        <div class="small text-muted">
            Run by {{ optional($run->createdBy)->name ?? '—' }} on {{ $run->created_at->format('d M Y H:i') }}
            · <span class="badge {{ $run->status === 'posted' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($run->status) }}</span>
            @if($run->transaction)
                · Journal <a href="{{ route('transactions.show', $run->transaction) }}" class="font-monospace">{{ $run->transaction->reference }}</a>
            @endif
        </div>
        @if($run->notes)<div class="small mt-1">{{ $run->notes }}</div>@endif
    </div>
    <a href="{{ route('loan-provisions.show', [$run, 'format' => 'excel']) }}" class="btn btn-outline-primary">
        <i class="bi bi-file-earmark-excel me-1"></i> Export
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card h-100"><div class="card-body">
        <div class="text-muted small text-uppercase">{{ $specific ? 'Principal in Arrears > 90 Days' : 'Outstanding Principal' }}</div>
        <div class="fs-4 fw-bold">{{ number_format($run->total_outstanding, $dp) }}</div>
        <div class="small text-muted">{{ number_format($run->loan_count) }} loans (excl. Locked-Up)</div>
    </div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body">
        <div class="text-muted small text-uppercase">Required{{ $specific ? ' (50% > 90 days, 100% ≥ 365 days)' : ' (' . rtrim(rtrim(number_format($run->rate, 4), '0'), '.') . '%)' }}</div>
        <div class="fs-4 fw-bold text-primary">{{ number_format($run->required_provision, $dp) }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body">
        <div class="text-muted small text-uppercase">Previous Provision</div>
        <div class="fs-4 fw-bold">{{ number_format($run->previous_provision, $dp) }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body">
        <div class="text-muted small text-uppercase">Adjustment Posted</div>
        <div class="fs-4 fw-bold">{{ $run->adjustment < 0 ? '(' . number_format(abs($run->adjustment), $dp) . ')' : number_format($run->adjustment, $dp) }}</div>
    </div></div></div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">Breakdown</div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead><tr>
                <th class="ps-3">#</th><th>Loan #</th><th>Client</th><th>Product</th><th>Disbursed</th>
                @if($specific)<th>Oldest Arrears</th><th class="text-end">Days</th>@endif
                <th class="text-end">Outstanding Principal</th><th class="text-end">Rate</th><th class="text-end pe-3">Provision</th>
            </tr></thead>
            <tbody>
            @forelse($lines as $i => $line)
                <tr>
                    <td class="ps-3 text-muted small">{{ $i + 1 }}</td>
                    <td class="font-monospace">
                        @if($line->loan)
                            <a href="{{ route('loans.show', $line->loan) }}" class="text-decoration-none">{{ $line->loan->loan_number }}</a>
                        @else — @endif
                    </td>
                    <td>{{ optional($line->client)->name ?? '—' }}</td>
                    <td class="small text-muted">{{ optional(optional($line->loan)->product)->name }}</td>
                    <td class="small text-muted">{{ optional(optional($line->loan)->disbursement_date)->format('d M Y') }}</td>
                    @if($specific)
                        <td class="small text-muted">{{ $line->oldest_arrears_date ? \Carbon\Carbon::parse($line->oldest_arrears_date)->format('d M Y') : '—' }}</td>
                        <td class="text-end fw-semibold {{ $line->days_in_arrears >= 365 ? 'text-danger' : 'text-warning' }}">{{ number_format((int) $line->days_in_arrears) }}</td>
                    @endif
                    <td class="text-end">{{ number_format($line->outstanding_principal, $dp) }}</td>
                    <td class="text-end small text-muted">{{ rtrim(rtrim(number_format($line->rate, 4), '0'), '.') }}%</td>
                    <td class="text-end pe-3 fw-semibold">{{ number_format($line->provision_amount, $dp) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $specific ? 10 : 8 }}" class="text-center text-muted py-4">No loans in this run.</td></tr>
            @endforelse
            </tbody>
            <tfoot class="fw-bold"><tr>
                <td colspan="{{ $specific ? 7 : 5 }}" class="ps-3 text-end">TOTAL</td>
                <td class="text-end">{{ number_format($run->total_outstanding, $dp) }}</td>
                <td></td>
                <td class="text-end pe-3">{{ number_format($run->required_provision, $dp) }}</td>
            </tr></tfoot>
        </table>
    </div>
</div>
@endsection
