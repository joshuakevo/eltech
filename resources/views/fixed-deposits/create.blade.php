@extends('layouts.app')
@section('title', 'New Fixed Deposit')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('fixed-deposits.index') }}">Fixed Deposits</a></li>
    <li class="breadcrumb-item active">New Fixed Deposit</li>
@endsection
@section('content')

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<form method="POST" action="{{ route('fixed-deposits.store') }}">
@csrf
<div class="card">
    <div class="card-header py-2 fw-semibold"><i class="bi bi-safe me-2 text-primary"></i>New Fixed Deposit</div>
    <div class="card-body p-3">

        <div class="row g-2 mb-2">
            <div class="col-md-6">
                <label class="form-label mb-1">Client <span class="text-danger">*</span></label>
                <select name="client_id" id="clientSelect" class="form-select form-select-sm ts-select" required>
                    <option value="">Select client...</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" @selected(old('client_id', $selectedClient?->id)==$c->id)>{{ $c->name }} ({{ $c->client_number }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label mb-1">FD Product <span class="text-danger">*</span></label>
                <select name="product_id" class="form-select form-select-sm" id="fdProduct" required>
                    <option value="">Select product...</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" data-rate="{{ $p->interest_rate }}" data-months="{{ $p->term_months }}" @selected(old('product_id')==$p->id)>
                            {{ $p->name }} ({{ $p->interest_rate }}% p.a., {{ $p->term_months }} months)
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="row g-2 mb-2">
            <div class="col-md-4">
                <label class="form-label mb-1">Principal Amount <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text text-muted">{{ \App\Models\SystemSetting::get('currency', 'UGX') }}</span>
                    <input type="number" name="principal" id="principal" class="form-control" step="0.01" min="1"
                           value="{{ old('principal') }}" required oninput="calcMaturity(); checkBalance()">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">Interest Rate (%)</label>
                <input type="number" name="interest_rate" id="fdRate" class="form-control form-control-sm"
                       step="0.01" min="0" value="{{ old('interest_rate') }}" placeholder="From product" oninput="calcMaturity()">
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">Term (months)</label>
                <input type="number" name="term_months" id="fdMonths" class="form-control form-control-sm"
                       min="1" value="{{ old('term_months') }}" placeholder="From product" oninput="calcMaturity()">
            </div>
        </div>

        <div class="row g-2 mb-2">
            <div class="col-md-4">
                <label class="form-label mb-1">Start Date <span class="text-danger">*</span></label>
                <input type="date" name="start_date" class="form-control form-control-sm"
                       value="{{ old('start_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required oninput="calcMaturity()">
            </div>
            <div class="col-md-8">
                {{-- Maturity preview inline --}}
                <label class="form-label mb-1">Projected at Maturity</label>
                <div id="calcResult" class="border rounded px-3 py-2 bg-light small d-none">
                    <span class="me-3 text-muted">Interest: <strong id="calcInterest">—</strong></span>
                    <span class="me-3">Maturity Amount: <strong class="text-success" id="calcMaturityAmt">—</strong></span>
                    <span class="text-muted">Date: <strong id="calcMaturityDate">—</strong></span>
                </div>
                <div id="calcPlaceholder" class="border rounded px-3 py-2 bg-light small text-muted fst-italic">
                    Fill in principal, rate and term to see maturity projection.
                </div>
            </div>
        </div>

        {{-- Funding --}}
        <div class="row g-2 mb-2">
            <div class="col-auto d-flex align-items-center gap-3 pt-1">
                <span class="form-label mb-0 fw-semibold">Funding:</span>
                <div class="form-check mb-0">
                    <input class="form-check-input" type="radio" name="_funding" id="fundCash" value="cash"
                        {{ old('savings_account_id') ? '' : 'checked' }} onchange="onFundingChange()">
                    <label class="form-check-label" for="fundCash"><i class="bi bi-cash me-1"></i>Cash</label>
                </div>
                <div class="form-check mb-0">
                    <input class="form-check-input" type="radio" name="_funding" id="fundSavings" value="savings"
                        {{ old('savings_account_id') ? 'checked' : '' }} onchange="onFundingChange()">
                    <label class="form-check-label" for="fundSavings"><i class="bi bi-piggy-bank me-1"></i>Deduct from Savings</label>
                </div>
            </div>
        </div>

        <div id="savingsAccountRow" class="row g-2 mb-2" style="display:none">
            <div class="col-md-6">
                <label class="form-label mb-1">Savings Account <span class="text-danger">*</span></label>
                <select name="savings_account_id" id="savingsAccountSelect" class="form-select form-select-sm ts-select">
                    <option value="">— Select client first —</option>
                </select>
                <div class="form-text" id="savingsBalanceHint"></div>
            </div>
        </div>

        <div id="cashSourceRow" class="row g-2 mb-2" style="display:none">
            <div class="col-md-6">
                <label class="form-label mb-1">Payment Source <span class="text-danger">*</span></label>
                <select name="payment_source_account_id" id="paymentSourceSelect" class="form-select form-select-sm">
                    <option value="">— Select GL account —</option>
                    @foreach($paymentSourceAccounts as $acc)
                    <option value="{{ $acc->id }}" {{ old('payment_source_account_id') == $acc->id ? 'selected' : '' }}>
                        {{ $acc->account_code }} — {{ $acc->account_name }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label mb-1">Receipt / Reference <span class="text-danger">*</span></label>
                <input type="text" name="reference" class="form-control form-control-sm" maxlength="100"
                       placeholder="Voucher no., receipt no., etc." value="{{ old('reference') }}" required>
            </div>
        </div>

    </div>
    <div class="card-footer d-flex justify-content-end gap-2 py-2 px-3">
        <a href="{{ route('fixed-deposits.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
        <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Create Fixed Deposit</button>
    </div>
</div>
</form>

@push('scripts')
<script>
const savingsByClient = @json($savingsByClient);
var dp = {{ $dp }};
function fmt(v) { return parseFloat(v).toFixed(dp); }

function onFundingChange() {
    const isSavings = document.getElementById('fundSavings').checked;
    document.getElementById('savingsAccountRow').style.display = isSavings ? '' : 'none';
    document.getElementById('cashSourceRow').style.display     = isSavings ? 'none' : '';
    if (!isSavings) document.querySelector('[name=savings_account_id]').value = '';
    document.getElementById('paymentSourceSelect').required = !isSavings;
    checkBalance();
}

function refreshSavingsDropdown() {
    const clientId = document.getElementById('clientSelect').value;
    const sel      = document.getElementById('savingsAccountSelect');
    const accounts = savingsByClient[clientId] || [];
    if (sel.tomselect) sel.tomselect.destroy();
    sel.innerHTML = '<option value="">— Select savings account —</option>';
    accounts.forEach(function(a) {
        const opt = document.createElement('option');
        opt.value = a.id; opt.textContent = a.label; opt.dataset.balance = a.balance;
        sel.appendChild(opt);
    });
    initTomSelect(sel);
    const hint = document.getElementById('savingsBalanceHint');
    if (accounts.length === 0) {
        hint.textContent = 'No active savings accounts for this client.';
        hint.className = 'form-text text-danger';
    } else {
        hint.textContent = '';
    }
    sel.addEventListener('change', checkBalance);
}

function checkBalance() {
    if (!document.getElementById('fundSavings').checked) return;
    const sel = document.getElementById('savingsAccountSelect');
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.dataset.balance) { document.getElementById('savingsBalanceHint').textContent = ''; return; }
    const bal       = parseFloat(opt.dataset.balance) || 0;
    const principal = parseFloat(document.getElementById('principal').value) || 0;
    const hint      = document.getElementById('savingsBalanceHint');
    hint.textContent = 'Available balance: ' + fmt(bal);
    hint.className   = 'form-text ' + (bal >= principal ? 'text-success' : 'text-danger');
}

document.getElementById('clientSelect').addEventListener('change', function() {
    refreshSavingsDropdown(); checkBalance();
});

document.getElementById('fdProduct').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    document.getElementById('fdRate').placeholder   = opt.dataset.rate   ?? '';
    document.getElementById('fdMonths').placeholder = opt.dataset.months ?? '';
    calcMaturity();
});

function calcMaturity() {
    const product = document.getElementById('fdProduct');
    const opt     = product.options[product.selectedIndex];
    const p = parseFloat(document.getElementById('principal').value) || 0;
    const r = parseFloat(document.getElementById('fdRate').value)    || parseFloat(opt.dataset.rate)   || 0;
    const m = parseInt(document.getElementById('fdMonths').value)    || parseInt(opt.dataset.months)   || 0;
    const calcResult = document.getElementById('calcResult');
    const calcPlaceholder = document.getElementById('calcPlaceholder');
    if (!p || !r || !m) { calcResult.classList.add('d-none'); calcPlaceholder.classList.remove('d-none'); return; }

    const interest = p * (r / 100) * (m / 12);
    const maturity = p + interest;
    const startVal = document.querySelector('[name=start_date]').value;
    let maturityDate = '—';
    if (startVal) {
        const d = new Date(startVal); d.setMonth(d.getMonth() + m);
        maturityDate = d.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
    }
    document.getElementById('calcInterest').textContent     = fmt(interest);
    document.getElementById('calcMaturityAmt').textContent  = fmt(maturity);
    document.getElementById('calcMaturityDate').textContent = maturityDate;
    calcResult.classList.remove('d-none');
    calcPlaceholder.classList.add('d-none');
}

document.addEventListener('DOMContentLoaded', function() {
    onFundingChange();
    if (document.getElementById('clientSelect').value) refreshSavingsDropdown();
});
</script>
@endpush
@endsection
