@extends('layouts.app')
@php $dp = 2; @endphp
@section('title', 'Edit Journal Entry')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('transactions.index') }}">Journal Entries</a></li>
    <li class="breadcrumb-item"><a href="{{ route('transactions.show', $transaction) }}">{{ $transaction->reference }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection
@section('content')

<form method="POST" action="{{ route('transactions.update', $transaction) }}" id="journalForm">
@csrf @method('PUT')
<div class="card">
    <div class="card-header py-2 fw-semibold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Journal Entry — <span class="font-monospace">{{ $transaction->reference }}</span></div>
    <div class="card-body p-3">

        @if(session('error'))
            <div class="alert alert-danger small mb-3">{{ session('error') }}</div>
        @endif

        @if($transaction->module !== 'manual')
        <div class="alert alert-warning small mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i>
            This entry was posted by the <strong>{{ $transaction->module }}</strong> module. Saving will reverse its linked member sub-ledger effects (savings balance, share/membership records, etc.) and re-apply them from the values below — the underlying record (e.g. savings/loan/share record) will be re-synced automatically.
        </div>
        @endif

        @php
            $lineFieldErrors = collect($errors->getMessages())
                ->filter(fn ($msgs, $key) => $key === 'lines' || str_starts_with($key, 'lines.'));
        @endphp
        @if($lineFieldErrors->isNotEmpty())
            <div class="alert alert-danger small mb-3">
                <ul class="mb-0 ps-3 small">
                    @foreach($lineFieldErrors as $msgs)
                        @foreach((array) $msgs as $msg)<li>{{ $msg }}</li>@endforeach
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Header fields --}}
        <div class="row g-2 mb-3">
            <div class="col-md-3">
                <label class="form-label mb-1">Date <span class="text-danger">*</span></label>
                <input type="date" name="date" class="form-control form-control-sm @error('date') is-invalid @enderror"
                       value="{{ old('date', $transaction->date->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                @error('date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label mb-1">Description <span class="text-danger">*</span></label>
                <input type="text" name="description" class="form-control form-control-sm @error('description') is-invalid @enderror"
                       value="{{ old('description', $transaction->description) }}" required>
                @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Receipt / Reference <span class="text-danger">*</span></label>
                <input type="text" name="reference" class="form-control form-control-sm @error('reference') is-invalid @enderror"
                       value="{{ old('reference', $transaction->reference) }}" required>
                @error('reference')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Journal Lines --}}
        <div class="d-flex justify-content-between align-items-center mb-1">
            <h6 class="fw-semibold mb-0">Journal Lines</h6>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="addLine()">
                <i class="bi bi-plus"></i> Add Line
            </button>
        </div>
        <div class="table-responsive mb-2">
            <table class="table table-bordered table-sm mb-0" id="linesTable">
                <thead class="table-light">
                    <tr>
                        <th style="width:26%">Account</th>
                        <th style="width:18%">Client <span class="text-muted fw-normal" style="font-size:.7rem">(optional)</span></th>
                        <th>Description</th>
                        <th style="width:160px">Debit</th>
                        <th style="width:160px">Credit</th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody id="linesBody">
                @foreach($lineRows as $i => $line)
                    <tr>
                        <td>
                            <select name="lines[{{ $i }}][account_id]" class="form-select form-select-sm ts-select" required>
                                <option value="">Select account...</option>
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}" @selected((string)($line['account_id'] ?? '') === (string)$acc->id)>
                                        {{ $acc->account_code }} — {{ $acc->account_name }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select name="lines[{{ $i }}][client_id]" class="form-select form-select-sm ts-select">
                                <option value="">— None —</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}" @selected((string)($line['client_id'] ?? '') === (string)$c->id)>
                                        {{ $c->name }} ({{ $c->client_number }})
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="text" name="lines[{{ $i }}][description]" class="form-control form-control-sm" value="{{ $line['description'] ?? '' }}"></td>
                        <td><input type="number" name="lines[{{ $i }}][debit]" step="0.01" min="0" value="{{ $line['debit'] ?? '0' }}" class="form-control form-control-sm debit-input" oninput="updateTotals()"></td>
                        <td><input type="number" name="lines[{{ $i }}][credit]" step="0.01" min="0" value="{{ $line['credit'] ?? '0' }}" class="form-control form-control-sm credit-input" oninput="updateTotals()"></td>
                        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="removeLine(this)"><i class="bi bi-trash"></i></button></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="3" class="fw-semibold text-end small">Totals:</td>
                        <td><span id="totalDebit" class="fw-semibold">0.00</span></td>
                        <td><span id="totalCredit" class="fw-semibold">0.00</span></td>
                        <td></td>
                    </tr>
                    <tr id="balanceRow">
                        <td colspan="6" class="text-center small py-1 fw-semibold" id="balanceStatus"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>
            Editing will reverse the original sub-ledger impacts and re-apply them with the new values.
        </p>

    </div>
    <div class="card-footer d-flex justify-content-end gap-2 py-2 px-3">
        <a href="{{ route('transactions.show', $transaction) }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
        <button class="btn btn-sm btn-primary" id="submitBtn"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
    </div>
</div>
</form>

@push('scripts')
<script>
let lineCount = {{ count($lineRows) }};
const accounts = @json($accounts->map(fn($a) => ['id' => $a->id, 'label' => $a->account_code . ' — ' . $a->account_name]));
const clientOpts = @json($clients->map(fn($c) => ['id' => $c->id, 'label' => $c->name . ' (' . $c->client_number . ')']));
var dp = {{ $dp }};
function fmt(v) { return parseFloat(v).toFixed(dp); }

function addLine() {
    const tbody = document.getElementById('linesBody');
    const tr = document.createElement('tr');
    const accOpts = accounts.map(a => `<option value="${a.id}">${a.label}</option>`).join('');
    const cliOpts = clientOpts.map(c => `<option value="${c.id}">${c.label}</option>`).join('');
    tr.innerHTML = `
        <td><select name="lines[${lineCount}][account_id]" class="form-select form-select-sm ts-select" required><option value="">Select account...</option>${accOpts}</select></td>
        <td><select name="lines[${lineCount}][client_id]" class="form-select form-select-sm ts-select"><option value="">— None —</option>${cliOpts}</select></td>
        <td><input type="text" name="lines[${lineCount}][description]" class="form-control form-control-sm"></td>
        <td><input type="number" name="lines[${lineCount}][debit]"  step="0.01" min="0" value="0" class="form-control form-control-sm debit-input"  oninput="updateTotals()"></td>
        <td><input type="number" name="lines[${lineCount}][credit]" step="0.01" min="0" value="0" class="form-control form-control-sm credit-input" oninput="updateTotals()"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="removeLine(this)"><i class="bi bi-trash"></i></button></td>
    `;
    tbody.appendChild(tr);
    tr.querySelectorAll('select').forEach(s => initTomSelect(s));
    lineCount++;
}

function removeLine(btn) {
    if (document.querySelectorAll('#linesBody tr').length > 2) {
        const tr = btn.closest('tr');
        tr.querySelectorAll('select').forEach(s => { if (s.tomselect) s.tomselect.destroy(); });
        tr.remove();
        updateTotals();
    }
}

function updateTotals() {
    const debits  = [...document.querySelectorAll('.debit-input')].reduce((s, i) => s + parseFloat(i.value || 0), 0);
    const credits = [...document.querySelectorAll('.credit-input')].reduce((s, i) => s + parseFloat(i.value || 0), 0);
    document.getElementById('totalDebit').textContent  = fmt(debits);
    document.getElementById('totalCredit').textContent = fmt(credits);
    const diff   = Math.abs(debits - credits);
    const status = document.getElementById('balanceStatus');
    if (diff < 0.001 && debits > 0) {
        status.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Balanced</span>';
        document.getElementById('submitBtn').disabled = false;
    } else {
        status.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle"></i> Unbalanced — difference: ${fmt(diff)}</span>`;
        document.getElementById('submitBtn').disabled = true;
    }
}
updateTotals();
</script>
@endpush
@endsection
