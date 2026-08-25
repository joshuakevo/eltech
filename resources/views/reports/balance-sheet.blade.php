@extends('layouts.app')
@section('title', 'Balance Sheet')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Balance Sheet</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Balance Sheet</h4>
        @if($segment)
        <span class="badge bg-primary-subtle text-primary mt-1"><i class="bi bi-tags-fill me-1"></i>{{ $segment->name }}</span>
        @endif
    </div>
    <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Export</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Export PDF</a></li>
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'excel']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Export Excel (CSV)</a></li>
        </ul>
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="GET">
            <div class="col-md-3"><label class="form-label small fw-semibold">As of Date</label><input type="date" name="as_of" class="form-control" value="{{ $asOf }}"></div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Segment</label>
                <select name="segment_id" class="form-select">
                    <option value="">All Segments</option>
                    @foreach($segments as $s)
                    <option value="{{ $s->id }}" @selected($segmentId == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto align-self-end"><button class="btn btn-primary">Run Report</button></div>
        </form>
    </div>
</div>

@if($segment)
<div class="alert alert-info small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Showing only balances attributable to <strong>{{ $segment->name }}</strong> clients (loans, savings/FD liabilities,
    share capital, and this segment's net income). Cash and other institutional accounts aren't tied to a specific
    segment and show as zero here, so <strong>this view will not balance</strong> the way the full institution-wide
    balance sheet does — that's expected, not an error.
</div>
@endif
<div class="row g-3">
    <!-- Assets -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header fw-semibold">Assets</div>
            <table class="table mb-0">
                <tbody>
                @foreach($data['asset']['rows'] as $row)
                    <tr><td class="ps-3">{{ $row['account']->account_code }} — {{ $row['account']->account_name }}</td><td class="text-end pe-3">{{ number_format($row['balance'], $dp) }}</td></tr>
                @endforeach
                </tbody>
                <tfoot class="table-light fw-semibold"><tr><td class="ps-3">Total Assets</td><td class="text-end pe-3 text-primary">{{ number_format($data['asset']['total'], $dp) }}</td></tr></tfoot>
            </table>
        </div>
    </div>
    <!-- Liabilities + Equity -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Liabilities</div>
            <table class="table mb-0">
                <tbody>
                @foreach($data['liability']['rows'] as $row)
                    <tr><td class="ps-3">{{ $row['account']->account_code }} — {{ $row['account']->account_name }}</td><td class="text-end pe-3">{{ number_format($row['balance'], $dp) }}</td></tr>
                @endforeach
                </tbody>
                <tfoot class="table-light fw-semibold"><tr><td class="ps-3">Total Liabilities</td><td class="text-end pe-3 text-danger">{{ number_format($data['liability']['total'], $dp) }}</td></tr></tfoot>
            </table>
        </div>
        <div class="card">
            <div class="card-header fw-semibold">Equity</div>
            <table class="table mb-0">
                <tbody>
                @foreach($data['equity']['rows'] as $row)
                    <tr><td class="ps-3">{{ $row['account']->account_code }} — {{ $row['account']->account_name }}</td><td class="text-end pe-3">{{ number_format($row['balance'], $dp) }}</td></tr>
                @endforeach
                </tbody>
                <tfoot class="table-light fw-semibold"><tr><td class="ps-3">Total Equity</td><td class="text-end pe-3 text-success">{{ number_format($data['equity']['total'], $dp) }}</td></tr></tfoot>
            </table>
        </div>
        <div class="card mt-2">
            <div class="card-body text-center">
                <div class="fw-bold">Liabilities + Equity = {{ number_format($data['liability']['total'] + $data['equity']['total'], $dp) }}</div>
                @if($segment)
                    <div class="text-muted small"><i class="bi bi-info-circle me-1"></i>Balance check skipped for segment view (see note above).</div>
                @elseif(abs($data['asset']['total'] - ($data['liability']['total'] + $data['equity']['total'])) < 0.01)
                    <div class="text-success small"><i class="bi bi-check-circle me-1"></i>Balance sheet is balanced.</div>
                @else
                    <div class="text-danger small"><i class="bi bi-x-circle me-1"></i>Balance sheet is NOT balanced.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
