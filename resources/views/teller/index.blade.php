@extends('layouts.app')
@section('title','Quick Teller')
@section('content')
<div class="mb-4">
    <h4 class="mb-0 fw-semibold">Quick Teller</h4>
    <p class="text-muted small mb-0">Fast savings deposits &amp; withdrawals</p>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if($errors->has('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">
    {{-- Search Panel --}}
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-search me-2 text-primary"></i>Find Account
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Search by Client Name / Phone / Account No.</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Start typing...">
                    </div>
                </div>
                <div id="searchResults" class="list-group" style="max-height:340px;overflow-y:auto"></div>
            </div>
        </div>
    </div>

    {{-- Transaction Panel --}}
    <div class="col-lg-7">
        <div class="card shadow-sm" id="transactionPanel" style="display:none">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-wallet2 me-2 text-primary"></i>Account Details</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="clearAccount()">
                    <i class="bi bi-x"></i> Clear
                </button>
            </div>
            <div class="card-body">
                {{-- Account Info --}}
                <div class="row g-2 mb-4">
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded">
                            <div class="text-muted small">Account Number</div>
                            <div class="fw-semibold fs-6" id="dispAccountNo">—</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded">
                            <div class="text-muted small">Client Name</div>
                            <div class="fw-semibold fs-6" id="dispClientName">—</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded">
                            <div class="text-muted small">Product</div>
                            <div class="fw-semibold" id="dispProduct">—</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-primary rounded text-white">
                            <div class="small opacity-75">Current Balance</div>
                            <div class="fw-bold fs-5" id="dispBalance">0.00</div>
                        </div>
                    </div>
                </div>

                {{-- Tabs --}}
                <ul class="nav nav-tabs mb-3" id="tellerTabs">
                    <li class="nav-item">
                        <button class="nav-link active" data-tab="deposit" onclick="switchTab('deposit')">
                            <i class="bi bi-arrow-down-circle text-success me-1"></i>Deposit
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-tab="withdraw" onclick="switchTab('withdraw')">
                            <i class="bi bi-arrow-up-circle text-danger me-1"></i>Withdraw
                        </button>
                    </li>
                </ul>

                {{-- Deposit Form --}}
                <div id="tabDeposit">
                    <form method="POST" action="{{ route('teller.deposit') }}">
                        @csrf
                        <input type="hidden" name="savings_account_id" id="depositAccountId">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text text-muted">{{ \App\Models\SystemSetting::get('currency', 'KES') }}</span>
                                    <input type="number" name="amount" class="form-control form-control-lg"
                                           step="0.01" min="0.01" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                                <input type="date" name="date" class="form-control" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Payment Source <span class="text-danger">*</span></label>
                                <select name="payment_source_account_id" class="form-select" required>
                                    <option value="">— Select GL account —</option>
                                    @foreach($paymentSourceAccounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->account_code }} — {{ $acc->account_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Receipt / Reference <span class="text-danger">*</span></label>
                                <input type="text" name="reference" class="form-control" placeholder="e.g. RCT-001" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Narration</label>
                                <input type="text" name="narration" class="form-control" placeholder="Cash deposit">
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-success btn-lg px-4">
                                <i class="bi bi-arrow-down-circle me-2"></i>Post Deposit
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Withdraw Form --}}
                <div id="tabWithdraw" style="display:none">
                    <form method="POST" action="{{ route('teller.withdraw') }}" id="tellerWithdrawForm">
                        @csrf
                        <input type="hidden" name="savings_account_id" id="withdrawAccountId">
                        <input type="hidden" name="allow_overdraft" id="tellerAllowOverdraftInput" value="0">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text text-muted">{{ \App\Models\SystemSetting::get('currency', 'KES') }}</span>
                                    <input type="number" name="amount" id="tellerWithdrawAmount" class="form-control form-control-lg"
                                           step="0.01" min="0.01" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                                <input type="date" name="date" class="form-control" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Withdrawal Charge</label>
                                <input type="number" name="withdrawal_fee" id="tellerWithdrawFee"
                                    class="form-control" step="0.01" min="0" value="0" placeholder="0">
                                <div class="form-text" id="tellerWithdrawFeeHint">Set to 0 to waive fee</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Payment Source (e.g. Cash, Mobile Money, Bank) <span class="text-danger">*</span></label>
                                <select name="payment_source_account_id" id="tellerPaymentSource" class="form-select" required>
                                    <option value="">— Select GL account —</option>
                                    @foreach($paymentSourceAccounts as $acc)
                                    <option value="{{ $acc->id }}"
                                        data-charge="{{ $acc->default_withdrawal_charge ?? '' }}"
                                        data-institution-charge="{{ $acc->default_institution_charge ?? '' }}">{{ $acc->account_code }} — {{ $acc->account_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Bank / Mobile Money Charge</label>
                                <input type="number" name="institution_charge" id="tellerInstitutionCharge"
                                    class="form-control" step="0.01" min="0" value="0" placeholder="0">
                                <div class="form-text">What the provider charges the SACCO — not deducted from the member.</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Receipt / Reference <span class="text-danger">*</span></label>
                                <input type="text" name="reference" class="form-control" placeholder="e.g. RCT-001" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Narration</label>
                                <input type="text" name="narration" class="form-control" placeholder="Cash withdrawal">
                            </div>
                        </div>
                        <div id="tellerOverdraftBox" class="alert alert-danger d-none mt-3 mb-0" style="border:2px solid #dc2626">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-bold mb-1">Insufficient Funds</div>
                                    <div class="small mb-2" id="tellerOverdraftMessage"></div>
                                    @if($canOverdraw)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="tellerOverdraftConfirm">
                                        <label class="form-check-label fw-semibold" for="tellerOverdraftConfirm">
                                            Yes, overdraw this account into a negative balance
                                        </label>
                                    </div>
                                    @else
                                    <div class="small fw-semibold">
                                        You don't have permission to overdraw this account. Contact an administrator to proceed.
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-danger btn-lg px-4" id="tellerWithdrawSubmitBtn">
                                <i class="bi bi-arrow-up-circle me-2"></i>Post Withdrawal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Placeholder when no account selected --}}
        <div class="card shadow-sm text-center py-5" id="noAccountPlaceholder">
            <div class="card-body text-muted">
                <i class="bi bi-search display-4 opacity-25 d-block mb-3"></i>
                <p class="mb-0">Search for a client or account on the left to begin a transaction</p>
            </div>
        </div>
    </div>
</div>

<script>
let searchTimer = null;
let selectedAccountId = null;
let currentProductFee = 0;
let selectedBalance = 0;
let selectedMinBalance = 0;
const canOverdraw = {{ $canOverdraw ? 'true' : 'false' }};

document.getElementById('searchInput').addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 2) {
        document.getElementById('searchResults').innerHTML = '';
        return;
    }
    searchTimer = setTimeout(() => fetchAccounts(q), 300);
});

function fetchAccounts(q) {
    fetch(`{{ route('teller.search-client') }}?q=` + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('searchResults');
            if (!data.length) {
                el.innerHTML = '<div class="list-group-item text-muted text-center py-3">No accounts found</div>';
                return;
            }
            el.innerHTML = data.map(a => `
                <button type="button" class="list-group-item list-group-item-action py-2 px-3"
                        onclick="selectAccount(${a.id}, '${a.account_number}', '${escHtml(a.client_name)}', '${escHtml(a.product_name)}', '${a.balance_fmt}', ${a.withdrawal_fee}, ${a.balance}, ${a.minimum_balance})">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold">${escHtml(a.client_name)}</div>
                            <small class="text-muted">${a.account_number} &middot; ${escHtml(a.product_name)}</small>
                        </div>
                        <span class="badge bg-primary-subtle text-primary ms-2">${a.balance_fmt}</span>
                    </div>
                </button>
            `).join('');
        });
}

function escHtml(str) {
    return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function selectAccount(id, accNo, clientName, product, balance, withdrawFee, rawBalance, minBalance) {
    selectedAccountId = id;
    document.getElementById('depositAccountId').value = id;
    document.getElementById('withdrawAccountId').value = id;
    currentProductFee = withdrawFee !== undefined ? withdrawFee : 0;
    selectedBalance = rawBalance !== undefined ? rawBalance : 0;
    selectedMinBalance = minBalance !== undefined ? minBalance : 0;
    document.getElementById('tellerPaymentSource').selectedIndex = 0;
    applyTellerChannelCharge();
    document.getElementById('dispAccountNo').textContent = accNo;
    document.getElementById('dispClientName').textContent = clientName;
    document.getElementById('dispProduct').textContent = product;
    document.getElementById('dispBalance').textContent = balance;
    document.getElementById('transactionPanel').style.display = '';
    document.getElementById('noAccountPlaceholder').style.display = 'none';
    document.getElementById('searchResults').innerHTML = '';
    document.getElementById('searchInput').value = clientName + ' – ' + accNo;
    document.getElementById('tellerWithdrawAmount').value = '';
    checkTellerOverdraft();
}

function checkTellerOverdraft() {
    var amt = parseFloat(document.getElementById('tellerWithdrawAmount').value) || 0;
    var fee = parseFloat(document.getElementById('tellerWithdrawFee').value) || 0;
    var total = amt + fee;
    var available = selectedBalance - selectedMinBalance;
    var box = document.getElementById('tellerOverdraftBox');
    var submitBtn = document.getElementById('tellerWithdrawSubmitBtn');
    var checkbox = document.getElementById('tellerOverdraftConfirm');
    var hiddenInput = document.getElementById('tellerAllowOverdraftInput');

    if (amt > 0 && total > available) {
        var shortfall = total - available;
        var resultingBalance = selectedBalance - total;
        document.getElementById('tellerOverdraftMessage').textContent =
            'Available: ' + available.toFixed(2) + '. This withdrawal exceeds it by ' + shortfall.toFixed(2) +
            ', taking the balance to ' + resultingBalance.toFixed(2) + (resultingBalance < 0 ? ' (negative — overdrawn)' : '') + '.';
        box.classList.remove('d-none');
        if (canOverdraw) {
            submitBtn.disabled = !(checkbox && checkbox.checked);
            hiddenInput.value = (checkbox && checkbox.checked) ? '1' : '0';
        } else {
            submitBtn.disabled = true;
            hiddenInput.value = '0';
        }
    } else {
        box.classList.add('d-none');
        submitBtn.disabled = false;
        hiddenInput.value = '0';
        if (checkbox) checkbox.checked = false;
    }
}
document.getElementById('tellerWithdrawAmount').addEventListener('input', checkTellerOverdraft);
document.getElementById('tellerWithdrawFee').addEventListener('input', checkTellerOverdraft);
var tellerOverdraftCheckbox = document.getElementById('tellerOverdraftConfirm');
if (tellerOverdraftCheckbox) {
    tellerOverdraftCheckbox.addEventListener('change', checkTellerOverdraft);
}

function clearAccount() {
    selectedAccountId = null;
    document.getElementById('transactionPanel').style.display = 'none';
    document.getElementById('noAccountPlaceholder').style.display = '';
    document.getElementById('searchInput').value = '';
    document.getElementById('searchResults').innerHTML = '';
}

function applyTellerChannelCharge() {
    var select = document.getElementById('tellerPaymentSource');
    var opt = select.options[select.selectedIndex];
    var channelCharge = opt ? opt.getAttribute('data-charge') : '';
    var feeField = document.getElementById('tellerWithdrawFee');
    var hint = document.getElementById('tellerWithdrawFeeHint');
    if (channelCharge !== null && channelCharge !== '') {
        feeField.value = channelCharge;
        hint.textContent = 'Channel charge for ' + opt.text.trim() + ' — editable, set to 0 to waive';
    } else {
        feeField.value = currentProductFee;
        hint.textContent = 'Set to 0 to waive fee';
    }

    var institutionCharge = opt ? opt.getAttribute('data-institution-charge') : '';
    document.getElementById('tellerInstitutionCharge').value = (institutionCharge !== null && institutionCharge !== '') ? institutionCharge : 0;

    checkTellerOverdraft();
}
document.getElementById('tellerPaymentSource').addEventListener('change', applyTellerChannelCharge);

function switchTab(tab) {
    document.querySelectorAll('#tellerTabs .nav-link').forEach(b => b.classList.remove('active'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
    document.getElementById('tabDeposit').style.display = tab === 'deposit' ? '' : 'none';
    document.getElementById('tabWithdraw').style.display = tab === 'withdraw' ? '' : 'none';
}
</script>
@endsection
