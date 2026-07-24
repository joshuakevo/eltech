@extends('layouts.app')
@section('title', 'Member Summary Statement')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Member Summary</li>
@endsection
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Member Summary Statement</h4>
    <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Export</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Export PDF</a></li>
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'excel']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Export Excel (CSV)</a></li>
        </ul>
    </div>
</div>

{{-- Filter bar --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">As of Date</label>
                <input type="date" name="as_of" class="form-control" value="{{ $asOf }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Member Status</label>
                <select name="status" class="form-select">
                    <option value="">All Members</option>
                    <option value="active"      {{ request('status') === 'active'      ? 'selected' : '' }}>Active</option>
                    <option value="inactive"    {{ request('status') === 'inactive'    ? 'selected' : '' }}>Inactive</option>
                    <option value="blacklisted" {{ request('status') === 'blacklisted' ? 'selected' : '' }}>Blacklisted</option>
                </select>
            </div>
            <div class="col-auto align-self-end">
                <button class="btn btn-primary">Run Report</button>
                <a href="{{ route('reports.member-summary') }}" class="btn btn-outline-secondary ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

{{-- Summary totals --}}
<div class="row g-3 mb-4">
    <div class="col">
        <div class="card text-center border-0 bg-light">
            <div class="card-body py-2">
                <div class="text-muted small">Members</div>
                <div class="fw-bold fs-5">{{ $members->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center border-0 bg-primary bg-opacity-10">
            <div class="card-body py-2">
                <div class="text-muted small">Total Savings</div>
                <div class="fw-bold fs-6 text-primary">{{ number_format($totals['savings'], 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center border-0 bg-primary bg-opacity-10">
            <div class="card-body py-2">
                <div class="text-muted small">Savings Interest <span class="d-block" style="font-size:.65rem">(accrued, uncredited)</span></div>
                <div class="fw-bold fs-6 text-primary">{{ number_format($totals['savings_interest'], 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center border-0 bg-danger bg-opacity-10">
            <div class="card-body py-2">
                <div class="text-muted small">Loan Principal</div>
                <div class="fw-bold fs-6 text-danger">{{ number_format($totals['loan_principal'], 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center border-0 bg-warning bg-opacity-10">
            <div class="card-body py-2">
                <div class="text-muted small">Loan Interest</div>
                <div class="fw-bold fs-6 text-warning">{{ number_format($totals['loan_interest'], 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center border-0 bg-info bg-opacity-10">
            <div class="card-body py-2">
                <div class="text-muted small">Fixed Deposits</div>
                <div class="fw-bold fs-6 text-info">{{ number_format($totals['fd_amount'], 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center border-0 bg-success bg-opacity-10">
            <div class="card-body py-2">
                <div class="text-muted small">Share Capital</div>
                <div class="fw-bold fs-6 text-success">{{ number_format($totals['share_total'], 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center border-0 bg-secondary bg-opacity-10">
            <div class="card-body py-2">
                <div class="text-muted small">Group Balance</div>
                <div class="fw-bold fs-6 text-secondary">{{ number_format($totals['group_balance'], 0) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Main table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0 small">
            <thead class="table-dark">
                <tr>
                    <th class="ps-3">#</th>
                    <th>Member</th>
                    <th>Client #</th>
                    <th class="text-end">Savings Balance</th>
                    <th class="text-end">Savings Interest</th>
                    <th class="text-end">Loan Principal</th>
                    <th class="text-end">Loan Interest</th>
                    <th class="text-end">Fixed Deposits</th>
                    <th class="text-end">Group Bal.</th>
                    <th class="text-end">Shares</th>
                    <th class="text-end pe-3">Share Value</th>
                </tr>
            </thead>
            <tbody>
            @forelse($members as $i => $row)
            <tr>
                <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                <td>
                    <a href="{{ route('clients.show', $row->client) }}" class="fw-semibold text-decoration-none text-dark">
                        {{ $row->client->name }}
                    </a>
                    <div class="text-muted" style="font-size:.72rem">
                        <span class="badge badge-status-{{ $row->client->status }} py-0">{{ ucfirst($row->client->status) }}</span>
                        @if($row->client->joining_date)
                            &nbsp; Joined {{ $row->client->joining_date->format('d M Y') }}
                        @endif
                    </div>
                </td>
                <td class="font-monospace text-muted">{{ $row->client->client_number }}</td>
                <td class="text-end {{ $row->savings_balance > 0 ? 'text-primary fw-semibold' : 'text-muted' }}">
                    {{ number_format($row->savings_balance, 0) }}
                </td>
                <td class="text-end {{ $row->savings_interest > 0 ? 'text-primary' : 'text-muted' }}">
                    {{ $row->savings_interest > 0 ? number_format($row->savings_interest, 0) : '—' }}
                </td>
                <td class="text-end {{ $row->loan_principal > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                    {{ $row->loan_principal > 0 ? number_format($row->loan_principal, 0) : '—' }}
                </td>
                <td class="text-end {{ $row->loan_interest > 0 ? 'text-warning' : 'text-muted' }}">
                    {{ $row->loan_interest > 0 ? number_format($row->loan_interest, 0) : '—' }}
                </td>
                <td class="text-end {{ $row->fd_amount > 0 ? 'text-info fw-semibold' : 'text-muted' }}">
                    {{ $row->fd_amount > 0 ? number_format($row->fd_amount, 0) : '—' }}
                </td>
                <td class="text-end {{ $row->group_balance > 0 ? 'text-secondary fw-semibold' : 'text-muted' }}">
                    {{ $row->group_balance > 0 ? number_format($row->group_balance, 0) : '—' }}
                </td>
                <td class="text-end text-muted">
                    {{ $row->share_units > 0 ? $row->share_units . ' shr' : '—' }}
                </td>
                <td class="text-end pe-3 {{ $row->share_total > 0 ? 'text-success fw-semibold' : 'text-muted' }}">
                    {{ $row->share_total > 0 ? number_format($row->share_total, 0) : '—' }}
                </td>
            </tr>
            @empty
            <tr><td colspan="11" class="text-center text-muted py-4">No members found.</td></tr>
            @endforelse
            </tbody>
            <tfoot class="table-secondary fw-bold small">
                <tr>
                    <td colspan="3" class="ps-3">Totals ({{ $members->count() }} members)</td>
                    <td class="text-end text-primary">{{ number_format($totals['savings'], 0) }}</td>
                    <td class="text-end text-primary">{{ number_format($totals['savings_interest'], 0) }}</td>
                    <td class="text-end text-danger">{{ number_format($totals['loan_principal'], 0) }}</td>
                    <td class="text-end text-warning">{{ number_format($totals['loan_interest'], 0) }}</td>
                    <td class="text-end text-info">{{ number_format($totals['fd_amount'], 0) }}</td>
                    <td class="text-end text-secondary">{{ number_format($totals['group_balance'], 0) }}</td>
                    <td class="text-end">{{ number_format($totals['share_units'], 0) }} shr</td>
                    <td class="text-end pe-3 text-success">{{ number_format($totals['share_total'], 0) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="card-footer text-muted small">
        1 share = UGX {{ number_format($shareValue, 0) }} &nbsp;&bull;&nbsp;
        Savings shows active accounts only &nbsp;&bull;&nbsp;
        Loans shows active outstanding balances only &nbsp;&bull;&nbsp;
        Fixed Deposits shows active principal only &nbsp;&bull;&nbsp;
        Savings Interest is accrued-but-uncredited interest on graduated-tier products only &mdash; it is not yet in the member's balance until interest is posted
    </div>
</div>
@endsection
