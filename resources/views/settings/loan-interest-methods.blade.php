@extends('layouts.app')
@section('title', 'Loan Interest Method Check')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Settings</a></li>
    <li class="breadcrumb-item active">Loan Interest Method Check</li>
@endsection
@section('content')
<div class="mb-3">
    <h4 class="mb-0 fw-semibold">Loan Interest Method Check</h4>
    <p class="text-muted small mb-0">
        Running loans (active / defaulted, excluding Locked-Up Loans) set to <strong>Flat</strong> interest. Tick the ones that are really
        <strong>reducing balance</strong> and click Switch. Only the method changes: no balances move and no journals are posted —
        Run Loans already charges interest on the reducing balance. It changes the loan terms shown and how Correct Balance rebuilds the schedule.
    </p>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-x-octagon me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<form method="POST" action="{{ route('settings.loan-interest-methods.apply') }}"
      onsubmit="return window.confirm('Switch the ticked loans/products from Flat to Reducing balance?')">
    @csrf

    <div class="card mb-3">
        <div class="card-header fw-semibold">Loan products</div>
        <div class="card-body small">
            <div class="text-muted mb-2">Switching a product only affects loans created from it in future.</div>
            @foreach($products as $p)
                <div class="form-check">
                    @if($p->interest_method === 'flat')
                        <input class="form-check-input" type="checkbox" name="product_ids[]" value="{{ $p->id }}" id="prod{{ $p->id }}">
                        <label class="form-check-label" for="prod{{ $p->id }}"><b>{{ $p->name }}</b> — {{ $p->interest_rate }}% <span class="badge bg-warning text-dark">flat</span></label>
                    @else
                        <input class="form-check-input" type="checkbox" disabled>
                        <label class="form-check-label text-muted"><b>{{ $p->name }}</b> — {{ $p->interest_rate }}% <span class="badge bg-success">{{ $p->interest_method }}</span></label>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">{{ $loans->count() }} loan(s) set to Flat</span>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-arrow-left-right me-1"></i>Switch ticked to Reducing</button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle small">
                <thead class="table-light"><tr>
                    <th class="ps-3"><input class="form-check-input" type="checkbox" id="lmAll" title="Tick all"></th>
                    <th>Loan</th><th>Client</th><th>Product</th>
                    <th class="text-end">Principal</th><th class="text-end">Rate</th><th class="text-end">Term</th><th>Disbursed</th>
                    <th class="text-end">Flat installment</th><th class="text-end">Reducing installment</th>
                    <th class="text-end pe-3">Outstanding</th>
                </tr></thead>
                <tbody>
                @forelse($loans as $loan)
                    <tr>
                        <td class="ps-3"><input class="form-check-input lm-loan" type="checkbox" name="loan_ids[]" value="{{ $loan->id }}"></td>
                        <td><a href="{{ route('loans.show', $loan) }}" target="_blank" class="font-monospace">{{ $loan->loan_number }}</a></td>
                        <td>{{ $loan->client->name ?? '—' }}</td>
                        <td>{{ $loan->product->name ?? '—' }}</td>
                        <td class="text-end">{{ number_format($loan->principal, $dp) }}</td>
                        <td class="text-end">{{ $loan->interest_rate }}%</td>
                        <td class="text-end">{{ $loan->term_months }}m</td>
                        <td class="text-nowrap">{{ $loan->disbursement_date?->format('d M Y') }}</td>
                        <td class="text-end">{{ $loan->flat_installment !== null ? number_format($loan->flat_installment, $dp) : '—' }}</td>
                        <td class="text-end fw-semibold">{{ $loan->reducing_installment !== null ? number_format($loan->reducing_installment, $dp) : '—' }}</td>
                        <td class="text-end pe-3">{{ number_format($loan->outstanding_principal, $dp) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center text-muted py-4">No running loans are set to Flat.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</form>

@push('scripts')
<script>
document.getElementById('lmAll')?.addEventListener('change', e => {
    document.querySelectorAll('.lm-loan').forEach(c => c.checked = e.target.checked);
});
</script>
@endpush
@endsection
