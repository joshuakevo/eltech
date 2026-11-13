@extends('layouts.app')
@section('title', 'Written-Off Loans')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
    <li class="breadcrumb-item active">Written Off</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Loans</h4>
</div>

@include('loans._tabs', ['activeTab' => 'written-off'])

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card"><div class="card-body">
        <div class="text-muted small text-uppercase">Principal written off</div>
        <div class="fs-4 fw-bold text-danger">{{ number_format($writeOffs->sum('principal')) }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body">
        <div class="text-muted small text-uppercase">Interest written off</div>
        <div class="fs-4 fw-bold">{{ number_format($writeOffs->sum('interest')) }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body">
        <div class="text-muted small text-uppercase">Bad debts recovered</div>
        <div class="fs-4 fw-bold text-success">{{ number_format($writeOffs->sum('recovered_sum')) }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body">
        <div class="text-muted small text-uppercase">Loans written off</div>
        <div class="fs-4 fw-bold">{{ $writeOffs->count() }}</div>
    </div></div></div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">Written-off loans</span>
        <form class="d-flex gap-2" method="GET">
            <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Loan #, member name or number">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm small align-middle mb-0">
            <thead class="table-light"><tr>
                <th class="ps-3">Date</th><th>Loan #</th><th>Member</th><th class="text-end">Principal</th><th class="text-end">Interest</th>
                <th class="text-end">Penalty b/f</th><th class="text-end">Recovered</th><th>Reason</th><th>By</th><th class="pe-3"></th>
            </tr></thead>
            <tbody>
            @forelse($writeOffs as $w)
                <tr>
                    <td class="ps-3 text-nowrap">{{ $w->writeoff_date->format('d M Y') }}</td>
                    <td><a href="{{ route('loans.show', $w->loan_id) }}">{{ $w->loan->loan_number ?? '' }}</a></td>
                    <td>{{ $w->loan->client->name ?? '' }} <span class="text-muted">{{ $w->loan->client->client_number ?? '' }}</span></td>
                    <td class="text-end">{{ number_format($w->principal) }}</td>
                    <td class="text-end">{{ number_format($w->interest) }}</td>
                    <td class="text-end">{{ $w->penalty > 0 ? number_format($w->penalty) : '—' }}</td>
                    <td class="text-end text-success">{{ $w->recovered_sum > 0 ? number_format($w->recovered_sum) : '—' }}</td>
                    <td>{{ $w->reason }}</td>
                    <td>{{ $w->createdBy->name ?? '' }}</td>
                    <td class="pe-3 text-nowrap">@if($w->transaction_id)<a href="{{ route('transactions.show', $w->transaction_id) }}" class="btn btn-sm btn-outline-secondary py-0">Journal</a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No loans written off. Use <b>Write off</b> on a Locked-Up loan's page.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="max-width: 760px">
    <div class="card-header fw-semibold"><i class="bi bi-journal-text me-1"></i>Write-off journal</div>
    <div class="card-body small">
        <p class="text-muted">Principal: <b>DR</b> {{ $accounts['expense']?->account_code }} {{ $accounts['expense']?->account_name }} / <b>CR</b> the Locked-Up loan receivable (product's account).
            Interest: <b>DR</b> {{ $accounts['interest_debit']?->account_code }} {{ $accounts['interest_debit']?->account_name }} / <b>CR</b> {{ $accounts['interest_credit']?->account_code }} {{ $accounts['interest_credit']?->account_name }}.
            Money collected later (<b>Record bad debt recovery</b> on the loan page): <b>DR</b> cash / member savings / <b>CR</b> {{ $recoveredAccount?->account_code }} {{ $recoveredAccount?->account_name }}.</p>
        @can('manage settings')
        <form method="POST" action="{{ route('loans.write-offs.settings') }}" class="row g-2">
            @csrf
            @foreach(['loan_writeoff_expense_account' => ['Principal debited to', 'expense'], 'loan_writeoff_interest_debit_account' => ['Interest debited to', 'interest_debit'], 'loan_writeoff_interest_credit_account' => ['Interest credited to', 'interest_credit'], 'bad_debts_recovered_account' => ['Recoveries credited to', 'recovered']] as $field => [$label, $key])
                <div class="col-md-6">
                    <label class="form-label fw-semibold">{{ $label }}</label>
                    <select name="{{ $field }}" class="form-select form-select-sm">
                        @foreach($allAccounts as $a)<option value="{{ $a->account_code }}" @selected($a->account_code === $settings[$key])>{{ $a->account_code }} — {{ $a->account_name }}</option>@endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-12"><button class="btn btn-sm btn-primary"><i class="bi bi-check2 me-1"></i>Save accounts</button></div>
        </form>
        @endcan
    </div>
</div>
@endsection
