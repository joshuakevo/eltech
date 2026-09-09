@extends('layouts.app')
@section('title', 'CRM — ' . $client->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('crm.clients.index') }}">CRM</a></li>
    <li class="breadcrumb-item active">{{ $client->name }}</li>
@endsection
@section('content')

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h4 class="fw-bold mb-0">
            {{ $client->name }}
            <span class="badge badge-status-{{ $client->status }} ms-1">{{ ucfirst($client->status) }}</span>
        </h4>
        <span class="text-muted">{{ $client->client_number }} &bull; {{ ucfirst($client->client_type) }}</span>
    </div>
    <a href="{{ route('clients.show', $client) }}" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-up-right-square me-1"></i>Open Client Record
    </a>
</div>

<ul class="nav nav-tabs mb-3" id="clientProfileTabs">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabOverview" type="button">
            <i class="bi bi-person-vcard me-1"></i>Overview
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabProducts" type="button">
            <i class="bi bi-grid-3x3-gap me-1"></i>Products
        </button>
    </li>
</ul>

<div class="tab-content">

    {{-- OVERVIEW --}}
    <div class="tab-pane fade show active" id="tabOverview">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="text-muted small">Total Value (Assets)</div>
                    <div class="fw-bold fs-5 text-success">{{ number_format($summary['total_assets'], $dp) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="text-muted small">Outstanding (Liabilities)</div>
                    <div class="fw-bold fs-5 {{ $summary['total_liability'] > 0 ? 'text-warning' : '' }}">{{ number_format($summary['total_liability'], $dp) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="text-muted small">Products Held</div>
                    <div class="fw-bold fs-5">{{ $productsOwnedCount }} of {{ $totalProductTypes }}
                        <span class="fs-6 text-muted">({{ $totalProductTypes > 0 ? round($productsOwnedCount / $totalProductTypes * 100) : 0 }}%)</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="text-muted small">Last Activity</div>
                    <div class="fw-bold fs-5">{{ $lastActivityAt ? \Illuminate\Support\Carbon::parse($lastActivityAt)->format('d M Y') : 'Never' }}</div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header small fw-semibold py-2">Client Information</div>
                    <div class="card-body py-2">
                        <table class="table table-sm table-borderless mb-0 small">
                            <tr><td class="text-muted w-40">Full Name</td><td class="fw-semibold">{{ $client->name }}</td></tr>
                            <tr><td class="text-muted">Client #</td><td class="fw-semibold font-monospace">{{ $client->client_number }}</td></tr>
                            @if($client->phone)
                            <tr><td class="text-muted">Phone</td><td>{{ $client->phone }}</td></tr>
                            @endif
                            @if($client->email)
                            <tr><td class="text-muted">Email</td><td>{{ $client->email }}</td></tr>
                            @endif
                            @if($client->district)
                            <tr><td class="text-muted">District</td><td>{{ $client->district }}</td></tr>
                            @endif
                            <tr><td class="text-muted">Branch</td><td>{{ $client->branch->name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Segment</td><td>{{ $client->segment->name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Date Joined</td><td>{{ $client->joining_date?->format('d M Y') ?? '—' }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header small fw-semibold py-2">Relationship</div>
                    <div class="card-body py-2">
                        <table class="table table-sm table-borderless mb-0 small">
                            <tr><td class="text-muted w-40">Account Manager</td><td class="fw-semibold">{{ $client->relationshipManager->name ?? '— Unassigned —' }}</td></tr>
                            <tr><td class="text-muted">Customer Status</td><td><span class="badge badge-status-{{ $client->status }}">{{ ucfirst($client->status) }}</span></td></tr>
                            <tr><td class="text-muted">Customer Type</td><td>{{ ucfirst($client->client_type) }}</td></tr>
                            <tr><td class="text-muted">Preferred Contact</td><td>{{ $client->preferred_communication ? ucfirst(str_replace('_',' ',$client->preferred_communication)) : '—' }}</td></tr>
                        </table>
                        <div class="mt-2 small">
                            <div class="text-muted mb-1">Financial breakdown (assets)</div>
                            <table class="table table-sm table-borderless mb-0 small">
                                <tr><td class="text-muted">Savings Balance</td><td class="text-end">{{ number_format($summary['savings_balance'], $dp) }}</td></tr>
                                @if($summary['savings_interest'] > 0)
                                <tr><td class="text-muted">Accrued Savings Interest</td><td class="text-end">{{ number_format($summary['savings_interest'], $dp) }}</td></tr>
                                @endif
                                @if($summary['fd_amount'] > 0)
                                <tr><td class="text-muted">Fixed Deposits</td><td class="text-end">{{ number_format($summary['fd_amount'], $dp) }}</td></tr>
                                @endif
                                @if($summary['share_total'] > 0)
                                <tr><td class="text-muted">Shares Paid ({{ $summary['share_units'] }} units)</td><td class="text-end">{{ number_format($summary['share_total'], $dp) }}</td></tr>
                                @endif
                                @if($summary['group_balance'] > 0)
                                <tr><td class="text-muted">Group Savings</td><td class="text-end">{{ number_format($summary['group_balance'], $dp) }}</td></tr>
                                @endif
                                @if($summary['loan_principal'] > 0 || $summary['loan_interest'] > 0)
                                <tr><td class="text-danger">Outstanding Loan Principal</td><td class="text-end text-danger">{{ number_format($summary['loan_principal'], $dp) }}</td></tr>
                                <tr><td class="text-danger">Outstanding Loan Interest</td><td class="text-end text-danger">{{ number_format($summary['loan_interest'], $dp) }}</td></tr>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- PRODUCTS --}}
    <div class="tab-pane fade" id="tabProducts">
        <div class="card mb-3">
            <div class="card-body py-2 small">
                <i class="bi bi-info-circle me-1 text-primary"></i>
                Product penetration: <strong>{{ $productsOwnedCount }} of {{ $totalProductTypes }}</strong> core products
                ({{ $totalProductTypes > 0 ? round($productsOwnedCount / $totalProductTypes * 100) : 0 }}%).
                @if(!$ownsLoan)
                    <span class="text-muted">Client has no active loan — potential cross-sell opportunity.</span>
                @endif
                @if(!$ownsShares)
                    <span class="text-muted">Client has no shares on record.</span>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr>
                        <th class="ps-3">Product</th><th>Reference</th><th>Status</th>
                        <th class="text-end">Value / Balance</th><th>Performance</th><th class="pe-3">Actions</th>
                    </tr></thead>
                    <tbody>
                    @forelse($productRows as $row)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold">{{ $row['type'] }}</div>
                                <div class="small text-muted">{{ $row['name'] }}</div>
                            </td>
                            <td class="font-monospace small">{{ $row['ref'] }}</td>
                            <td><span class="badge {{ $row['badge_class'] }}">{{ ucfirst($row['status']) }}</span></td>
                            <td class="text-end fw-semibold">{{ number_format($row['value'], $dp) }}</td>
                            <td class="small">{{ $row['performance'] }}</td>
                            <td class="pe-3">
                                @if($row['link'])
                                <a href="{{ $row['link'] }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No products on record for this client.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
