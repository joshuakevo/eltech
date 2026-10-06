@extends('layouts.app')
@section('title', 'Payroll Run — ' . $payroll->run_number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('payroll.index') }}">Payroll</a></li>
    <li class="breadcrumb-item active">{{ $payroll->run_number }}</li>
@endsection
@section('content')
@php $hasDeductions = $payroll->items->sum('deductions') > 0; @endphp
@if($errors->has('payment_date'))
<div class="alert alert-danger small mb-3">
    <i class="bi bi-exclamation-triangle me-1"></i>{{ $errors->first('payment_date') }}
</div>
@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h6 class="fw-semibold mb-0">{{ $payroll->run_number }} — {{ $payroll->period_label }}</h6>
        @if($payroll->description)
            <small class="text-muted">{{ $payroll->description }}</small>
        @endif
    </div>
    @if($payroll->status === 'draft')
    <div class="d-flex gap-2">
        @can('create payroll')
        <a href="{{ route('payroll.edit', $payroll) }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        @endcan
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#processModal">
            <i class="bi bi-check-circle me-1"></i>Process Payroll
        </button>
        <form method="POST" action="{{ route('payroll.destroy', $payroll) }}"
              onsubmit="return confirm('Delete this draft run? This cannot be undone.')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete Draft</button>
        </form>
    </div>
    @else
    <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>Processed</span>
    @endif
</div>

<div class="row g-3 mb-3">
    <div class="col">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="small text-muted">Employees</div>
                <div class="fw-bold fs-6">{{ $payroll->items->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center border-success">
            <div class="card-body py-2">
                <div class="small text-muted">Total Net Salary</div>
                <div class="fw-bold fs-6 text-success">{{ number_format($payroll->total_gross, 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="small text-muted">Status</div>
                <div class="fw-bold fs-6">
                    @if($payroll->status === 'processed')
                        <span class="text-success">Processed</span>
                    @else
                        <span class="text-warning">Draft</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @if($payroll->processed_at)
    <div class="col">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="small text-muted">Processed On</div>
                <div class="fw-bold fs-6">{{ $payroll->processed_at->format('d M Y') }}</div>
            </div>
        </div>
    </div>
    @endif
</div>

<div class="card">
    <div class="card-header small fw-semibold py-2">Payroll Items</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Employee</th>
                    <th>Savings Account</th>
                    <th>Type</th>
                    <th class="text-end">Gross Pay</th>
                    <th class="text-end">PAYE</th>
                    <th class="text-end">NSSF 5%</th>
                    <th class="text-end">NSSF 10%</th>
                    <th class="text-end">Lunch</th>
                    <th class="text-end">Transport</th>
                    <th class="text-end">Staff Savings</th>
                    @if($hasDeductions)<th class="text-end">Deductions</th>@endif
                    <th class="text-end fw-semibold">Net Salary</th>
                </tr>
            </thead>
            <tbody class="small">
            @foreach($payroll->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    <div class="fw-semibold">{{ $item->employee->name }}</div>
                    <div class="text-muted" style="font-size:.72rem">{{ $item->employee->position }} · {{ $item->employee->employee_number }}</div>
                </td>
                <td class="font-monospace">
                    {{ $item->savingsAccount?->account_number ?? '<span class="text-danger">Not linked</span>' }}
                </td>
                <td><span class="badge {{ $item->pay_type === 'commission' ? 'bg-info text-dark' : 'bg-light text-dark border' }}">{{ \App\Models\Employee::payTypeLabel($item->pay_type) }}</span></td>
                <td class="text-end">{{ number_format($item->basic_salary + $item->allowances, 0) }}</td>
                <td class="text-end text-danger">{{ number_format($item->paye, 0) }}</td>
                <td class="text-end text-danger">{{ number_format($item->nssf_employee, 0) }}</td>
                <td class="text-end text-muted">{{ number_format($item->nssf_employer, 0) }}</td>
                <td class="text-end text-danger">{{ number_format($item->lunch, 0) }}</td>
                <td class="text-end text-success">{{ number_format($item->transport, 0) }}</td>
                <td class="text-end text-danger">{{ number_format($item->staff_savings, 0) }}</td>
                @if($hasDeductions)<td class="text-end text-danger">{{ number_format($item->deductions, 0) }}</td>@endif
                <td class="text-end fw-semibold">{{ number_format($item->net_salary, 0) }}</td>
            </tr>
            @endforeach
            </tbody>
            <tfoot class="table-light fw-bold small">
                <tr>
                    <td colspan="4">Totals</td>
                    <td class="text-end">{{ number_format($payroll->items->sum('basic_salary') + $payroll->items->sum('allowances'), 0) }}</td>
                    <td class="text-end text-danger">{{ number_format($payroll->items->sum('paye'), 0) }}</td>
                    <td class="text-end text-danger">{{ number_format($payroll->items->sum('nssf_employee'), 0) }}</td>
                    <td class="text-end text-muted">{{ number_format($payroll->items->sum('nssf_employer'), 0) }}</td>
                    <td class="text-end text-danger">{{ number_format($payroll->items->sum('lunch'), 0) }}</td>
                    <td class="text-end text-success">{{ number_format($payroll->items->sum('transport'), 0) }}</td>
                    <td class="text-end text-danger">{{ number_format($payroll->items->sum('staff_savings'), 0) }}</td>
                    @if($hasDeductions)<td class="text-end text-danger">{{ number_format($payroll->items->sum('deductions'), 0) }}</td>@endif
                    <td class="text-end">{{ number_format($payroll->items->sum('net_salary'), 0) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Journal breakdown: preview before processing, posted entry after --}}
@if($journalPreview)
<div class="card mt-3">
    <div class="card-header small fw-semibold py-2 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-journal-text me-1"></i>Journal Preview — what processing will post</span>
        <span class="badge bg-warning text-dark">Not yet posted</span>
    </div>
    @if($journalPreview['issues'])
    <div class="alert alert-danger small py-2 m-2 mb-0">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Fix these before processing:</div>
        <ul class="mb-0 ps-3">@foreach($journalPreview['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul>
    </div>
    @endif
    @include('payroll._journal', ['lines' => $journalPreview['lines']])
</div>
@elseif($postedJournal)
<div class="card mt-3">
    <div class="card-header small fw-semibold py-2 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-journal-check me-1"></i>Posted Journal Entry —
            @can('view transactions')
                <a href="{{ route('transactions.show', $postedJournal) }}" class="font-monospace">{{ $postedJournal->reference }}</a>
            @else
                <span class="font-monospace">{{ $postedJournal->reference }}</span>
            @endcan
        </span>
        <span class="text-muted">{{ $postedJournal->date?->format('d M Y') }}</span>
    </div>
    @include('payroll._journal', ['lines' => $postedJournal->lines->map(fn ($l) => [
        'account' => $l->account, 'debit' => (float) $l->debit, 'credit' => (float) $l->credit, 'description' => $l->description,
    ])->all()])
</div>
@endif

@if($payroll->status === 'draft')
<div class="modal fade" id="processModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-check-circle me-1"></i>Process Payroll</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('payroll.process', $payroll) }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        This will post the journal entry below and credit <strong>{{ number_format($journalPreview['total_net'] ?? 0, 0) }}</strong> net pay to employee savings accounts.
                    </div>
                    @if($journalPreview['issues'] ?? false)
                        <div class="alert alert-danger small py-2 mb-3">
                            <ul class="mb-0 ps-3">@foreach($journalPreview['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul>
                        </div>
                    @endif
                    <div class="border rounded mb-3">
                        @include('payroll._journal', ['lines' => $journalPreview['lines'] ?? []])
                    </div>
                    @error('payment_date')
                        <div class="alert alert-danger small py-2 mb-3">{{ $message }}</div>
                    @enderror
                    <div class="mb-3" style="max-width:240px">
                        <label class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control @error('payment_date') is-invalid @enderror"
                               value="{{ old('payment_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-sm btn-success" {{ ($journalPreview['issues'] ?? false) ? 'disabled' : '' }}><i class="bi bi-check-circle me-1"></i>Confirm &amp; Process</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
