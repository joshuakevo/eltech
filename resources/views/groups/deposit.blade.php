@extends('layouts.app')
@section('title', 'Group Deposit')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('groups.index') }}">Groups</a></li>
    <li class="breadcrumb-item"><a href="{{ route('groups.show', $group) }}">{{ $group->name }}</a></li>
    <li class="breadcrumb-item active">Deposit</li>
@endsection
@section('content')
<div class="mb-4"><h4 class="fw-bold mb-0">Post Deposit — {{ $group->name }}</h4></div>

<div class="card" style="max-width:640px">
    <div class="card-body">
        <form method="POST" action="{{ route('groups.deposit.store', $group) }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Posting Mode <span class="text-danger">*</span></label>
                <select name="mode" id="modeSelect" class="form-select" onchange="updateMode()">
                    <option value="individual" {{ old('mode','individual')==='individual'?'selected':'' }}>Individual member</option>
                    <option value="equal_split" {{ old('mode')==='equal_split'?'selected':'' }}>Group-wide — equal split</option>
                    <option value="custom" {{ old('mode')==='custom'?'selected':'' }}>Group-wide — custom amounts</option>
                </select>
            </div>

            <div id="memberRow" class="mb-3">
                <label class="form-label fw-semibold">Member</label>
                <select name="member_id" class="form-select">
                    <option value="">— Select member —</option>
                    @foreach($members as $m)
                    <option value="{{ $m->id }}" {{ old('member_id') == $m->id ? 'selected' : '' }}>
                        {{ $m->name }} (bal: {{ number_format($m->balance, 0) }}){{ $m->is_leader ? ' ★' : '' }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div id="amountRow" class="mb-3">
                <label class="form-label fw-semibold" id="amountLabel">Amount (UGX) <span class="text-danger">*</span></label>
                <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror" min="0.01" step="0.01" value="{{ old('amount') }}">
                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div id="customRow" class="d-none mb-3">
                <label class="form-label fw-semibold">Amounts per Member</label>
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light"><tr><th>Member</th><th class="text-end">Balance</th><th>Amount (UGX)</th></tr></thead>
                    <tbody>
                    @foreach($members as $m)
                    <tr>
                        <td>{{ $m->name }}@if($m->is_leader) <span class="badge bg-primary">Leader</span>@endif</td>
                        <td class="text-end font-monospace text-muted">{{ number_format($m->balance, 0) }}</td>
                        <td><input type="number" name="custom_amounts[{{ $m->id }}]" class="form-control form-control-sm" min="0" step="0.01" value="{{ old('custom_amounts.'.$m->id, 0) }}"></td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Transaction Date <span class="text-danger">*</span></label>
                    <input type="date" name="transaction_date" class="form-control" value="{{ old('transaction_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Receipt / Reference <span class="text-danger">*</span></label>
                    <input type="text" name="reference" class="form-control @error('reference') is-invalid @enderror" value="{{ old('reference') }}" placeholder="e.g. RCT-001" required>
                    @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Payment Source</label>
                    <select name="payment_source_account_id" class="form-select" required>
                        <option value="">— Select GL account —</option>
                        @foreach($paymentSourceAccounts as $acc)
                        <option value="{{ $acc->id }}" {{ old('payment_source_account_id') == $acc->id ? 'selected' : '' }}>
                            {{ $acc->account_code }} — {{ $acc->account_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Notes</label>
                    <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Optional note…">
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">Post Deposit</button>
                <a href="{{ route('groups.show', $group) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function updateMode() {
    var mode = document.getElementById('modeSelect').value;
    document.getElementById('memberRow').classList.toggle('d-none', mode !== 'individual');
    document.getElementById('amountRow').classList.toggle('d-none', mode === 'custom');
    document.getElementById('customRow').classList.toggle('d-none', mode !== 'custom');
    document.getElementById('amountLabel').textContent = (mode === 'equal_split' ? 'Total Amount (UGX)' : 'Amount (UGX)') + ' *';
}
updateMode();
</script>
@endsection
