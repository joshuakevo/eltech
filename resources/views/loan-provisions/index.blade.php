@extends('layouts.app')
@section('title', $type === 'general' ? 'General Provisions' : 'Specific Provisions')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
    <li class="breadcrumb-item active">{{ $type === 'general' ? 'General Provisions' : 'Specific Provisions' }}</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Loan Provisions</h4>
    @if($type === 'general' && $preview)
    <a href="{{ request()->fullUrlWithQuery(['format' => 'excel', 'as_at_date' => $asAt, 'rate' => $rate]) }}" class="btn btn-outline-primary">
        <i class="bi bi-file-earmark-excel me-1"></i> Export Breakdown
    </a>
    @endif
</div>

@include('loans._tabs', ['activeTab' => $type === 'general' ? 'general-provisions' : 'specific-provisions'])

@if($type === 'specific')
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-shield-exclamation fs-1 d-block mb-2"></i>
            Specific loan provisions are not set up yet.
        </div>
    </div>
@else
    {{-- Run parameters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="GET">
                <input type="hidden" name="type" value="general">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Provision as at</label>
                    <input type="date" name="as_at_date" class="form-control" value="{{ $asAt }}" max="{{ today()->toDateString() }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Rate (%)</label>
                    <input type="number" name="rate" class="form-control" value="{{ rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.') }}" step="0.01" min="0.01" max="100">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary"><i class="bi bi-calculator me-1"></i> Calculate</button>
                </div>
                <div class="col-auto">
                    <a href="{{ route('loan-provisions.index', ['type' => 'general']) }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
            <div class="small text-muted mt-2">
                <i class="bi bi-info-circle me-1"></i>
                General provision = rate × outstanding principal as at the date selected, for all disbursed loans
                <strong>excluding Locked-Up Loans</strong>. Posting books only the movement needed to bring
                GL 1110 (Loan Provisions — General) to the required balance, against GL 5120 (General Loan Provision Expense).
            </div>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Outstanding Principal</div>
                <div class="fs-4 fw-bold">{{ number_format($preview['total_outstanding'], $dp) }}</div>
                <div class="small text-muted">{{ number_format($preview['lines']->count()) }} loans as at {{ \Carbon\Carbon::parse($asAt)->format('d M Y') }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Required Provision ({{ rtrim(rtrim(number_format($rate, 4), '0'), '.') }}%)</div>
                <div class="fs-4 fw-bold text-primary">{{ number_format($preview['required'], $dp) }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Existing Provision (GL 1110)</div>
                <div class="fs-4 fw-bold">{{ number_format($preview['existing'], $dp) }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Adjustment to Post</div>
                <div class="fs-4 fw-bold {{ $preview['adjustment'] > 0 ? 'text-danger' : ($preview['adjustment'] < 0 ? 'text-success' : '') }}">
                    {{ $preview['adjustment'] < 0 ? '(' . number_format(abs($preview['adjustment']), $dp) . ')' : number_format($preview['adjustment'], $dp) }}
                </div>
                <div class="small text-muted">
                    @if($preview['adjustment'] > 0) Charge: DR 5120 / CR 1110
                    @elseif($preview['adjustment'] < 0) Write-back: DR 1110 / CR 5120
                    @else No movement needed
                    @endif
                </div>
            </div></div>
        </div>
    </div>

    {{-- Post --}}
    @can('run loan provisions')
    <div class="card mb-4">
        <div class="card-body">
            @if($alreadyPosted)
                <div class="alert alert-info mb-0">
                    A general provision has already been posted as at {{ \Carbon\Carbon::parse($asAt)->format('d M Y') }}.
                    <a href="{{ route('loan-provisions.show', $alreadyPosted) }}">View run</a>.
                    Reverse its journal entry to re-run it.
                </div>
            @else
                <form method="POST" action="{{ route('loan-provisions.store') }}" class="row g-2 align-items-end"
                      onsubmit="return confirm('Post general provision as at {{ $asAt }}?');">
                    @csrf
                    <input type="hidden" name="as_at_date" value="{{ $asAt }}">
                    <input type="hidden" name="rate" value="{{ $rate }}">
                    <div class="col-md-7">
                        <label class="form-label small text-muted mb-1">Notes (optional)</label>
                        <input type="text" name="notes" class="form-control" maxlength="500" placeholder="e.g. September 2026 month-end provision">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-success"><i class="bi bi-check2-circle me-1"></i> Run Provision</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
    @endcan

    {{-- Breakdown --}}
    <div class="card mb-4">
        <div class="card-header bg-white fw-semibold">Breakdown — loans contributing to outstanding principal</div>
        <div class="table-responsive" style="max-height:520px">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="sticky-top bg-white"><tr>
                    <th class="ps-3">#</th><th>Loan #</th><th>Client</th><th>Product</th><th>Disbursed</th><th>Status</th>
                    <th class="text-end">Outstanding Principal</th>
                    <th class="text-end">Rate</th>
                    <th class="text-end pe-3">Provision</th>
                </tr></thead>
                <tbody>
                @forelse($preview['lines'] as $i => $line)
                    @php $loan = $line['loan']; @endphp
                    <tr>
                        <td class="ps-3 text-muted small">{{ $i + 1 }}</td>
                        <td class="font-monospace"><a href="{{ route('loans.show', $loan) }}" class="text-decoration-none">{{ $loan->loan_number }}</a></td>
                        <td>{{ optional($loan->client)->name ?? '—' }}</td>
                        <td class="small text-muted">{{ optional($loan->product)->name }}</td>
                        <td class="small text-muted">{{ optional($loan->disbursement_date)->format('d M Y') }}</td>
                        <td><span class="badge badge-status-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span></td>
                        <td class="text-end">{{ number_format($line['outstanding'], $dp) }}</td>
                        <td class="text-end small text-muted">{{ rtrim(rtrim(number_format($line['rate'], 4), '0'), '.') }}%</td>
                        <td class="text-end pe-3 fw-semibold">{{ number_format($line['provision_amount'], $dp) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No outstanding loans as at this date.</td></tr>
                @endforelse
                </tbody>
                @if($preview['lines']->isNotEmpty())
                <tfoot class="fw-bold"><tr>
                    <td colspan="6" class="ps-3 text-end">TOTAL</td>
                    <td class="text-end">{{ number_format($preview['total_outstanding'], $dp) }}</td>
                    <td></td>
                    <td class="text-end pe-3">{{ number_format($preview['required'], $dp) }}</td>
                </tr></tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- History --}}
    <div class="card">
        <div class="card-header bg-white fw-semibold">Previous General Provision Runs</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    <th class="ps-3">As At</th><th class="text-end">Loans</th><th class="text-end">Outstanding</th>
                    <th class="text-end">Rate</th><th class="text-end">Required</th><th class="text-end">Previous</th>
                    <th class="text-end">Adjustment</th><th>Journal</th><th>Status</th><th>By</th><th class="pe-3"></th>
                </tr></thead>
                <tbody>
                @forelse($runs as $run)
                    <tr>
                        <td class="ps-3">{{ $run->as_at_date->format('d M Y') }}</td>
                        <td class="text-end">{{ number_format($run->loan_count) }}</td>
                        <td class="text-end">{{ number_format($run->total_outstanding, $dp) }}</td>
                        <td class="text-end">{{ rtrim(rtrim(number_format($run->rate, 4), '0'), '.') }}%</td>
                        <td class="text-end fw-semibold">{{ number_format($run->required_provision, $dp) }}</td>
                        <td class="text-end text-muted">{{ number_format($run->previous_provision, $dp) }}</td>
                        <td class="text-end">{{ $run->adjustment < 0 ? '(' . number_format(abs($run->adjustment), $dp) . ')' : number_format($run->adjustment, $dp) }}</td>
                        <td class="small">
                            @if($run->transaction)
                                <a href="{{ route('transactions.show', $run->transaction) }}" class="text-decoration-none font-monospace">{{ $run->transaction->reference }}</a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $run->status === 'posted' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($run->status) }}</span>
                        </td>
                        <td class="small text-muted">{{ optional($run->createdBy)->name }}</td>
                        <td class="pe-3"><a href="{{ route('loan-provisions.show', $run) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center text-muted py-4">No provision runs yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($runs->hasPages())
        <div class="card-footer">{{ $runs->links() }}</div>
        @endif
    </div>
@endif
@endsection
