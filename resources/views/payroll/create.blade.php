@php
    $editing = isset($payroll);
    $existingItems = $editing
        ? $payroll->items->map->only(['employee_id', 'basic_salary', 'allowances', 'paye', 'nssf_employee', 'nssf_employer', 'lunch', 'transport', 'staff_savings'])->values()
        : [];
@endphp
@extends('layouts.app')
@section('title', $editing ? 'Edit Payroll Run — ' . $payroll->run_number : 'New Payroll Run')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('payroll.index') }}">Payroll</a></li>
    @if($editing)
        <li class="breadcrumb-item"><a href="{{ route('payroll.show', $payroll) }}">{{ $payroll->run_number }}</a></li>
        <li class="breadcrumb-item active">Edit</li>
    @else
        <li class="breadcrumb-item active">New Run</li>
    @endif
@endsection
@section('content')
<div class="card">
    <div class="card-header fw-semibold">{{ $editing ? 'Edit Payroll Run — ' . $payroll->run_number : 'New Payroll Run' }}</div>
    <div class="card-body">
    @if($errors->any())
    <div class="alert alert-danger small py-2">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
    @endif
    <form method="POST" action="{{ $editing ? route('payroll.update', $payroll) : route('payroll.store') }}" id="payrollForm">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Month <span class="text-danger">*</span></label>
                <select name="period_month" class="form-select" required>
                    @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" {{ old('period_month', $editing ? $payroll->period_month : now()->month) == $m ? 'selected' : '' }}>
                        {{ date('F', mktime(0,0,0,$m,1)) }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Year <span class="text-danger">*</span></label>
                <input type="number" name="period_year" class="form-control" value="{{ old('period_year', $editing ? $payroll->period_year : now()->year) }}" min="2000" max="2100" required>
            </div>
            <div class="col-md-7">
                <label class="form-label fw-semibold">Description</label>
                <input type="text" name="description" class="form-control" value="{{ old('description', $editing ? $payroll->description : '') }}" placeholder="Optional note">
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-semibold mb-0">Employees</h6>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addAllEmployees()">
                <i class="bi bi-people me-1"></i>Add All Active
            </button>
        </div>

        <div class="form-text mb-2">
            PAYE, NSSF 5% (employee) and NSSF 10% (employer) are filled in automatically from Gross Pay.
            They are editable — change them (e.g. to 0) for anyone who is not charged; clear a cell to go back to the automatic amount.
            Lunch and Staff Savings are deducted from Net Pay; Transport is added to Net Pay (not taxed).
            Staff Savings are deposited into the Staff Savings account ({{ \App\Models\SystemSetting::get(\App\Models\PayrollItem::STAFF_SAVINGS_SETTING, 'not set') }}) when the run is processed.
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm" id="itemsTable" style="min-width:1700px">
                <thead class="table-light">
                    <tr>
                        <th style="width:230px">Employee</th>
                        <th style="width:120px">Type</th>
                        <th style="width:150px">Gross Pay</th>
                        <th style="width:125px">PAYE</th>
                        <th style="width:120px">NSSF 5%</th>
                        <th style="width:120px">NSSF 10%</th>
                        <th style="width:110px">Lunch</th>
                        <th style="width:120px">Transport</th>
                        <th style="width:130px">Staff Savings</th>
                        <th style="width:140px" class="text-end">Net Pay</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    {{-- rows added by JS --}}
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="fw-semibold text-end">Totals:</td>
                        <td class="text-end fw-semibold" id="totalGross">0</td>
                        <td class="text-end fw-semibold" id="totalPaye">0</td>
                        <td class="text-end fw-semibold" id="totalNssf5">0</td>
                        <td class="text-end fw-semibold" id="totalNssf10">0</td>
                        <td class="text-end fw-semibold" id="totalLunch">0</td>
                        <td class="text-end fw-semibold" id="totalTransport">0</td>
                        <td class="text-end fw-semibold" id="totalStaffSavings">0</td>
                        <td class="text-end fw-bold" id="grandTotal">0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="addRow()">
            <i class="bi bi-plus me-1"></i>Add Employee Row
        </button>

        <div class="d-flex gap-2 mt-2">
            <button class="btn btn-primary">{{ $editing ? 'Update Payroll Run' : 'Save Payroll Run' }}</button>
            <a href="{{ $editing ? route('payroll.show', $payroll) : route('payroll.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
const employees = @json($employees);
const existingItems = @json($existingItems);
const PAY_TYPES = @json(\App\Models\Employee::PAY_TYPES);
const DEFAULT_LUNCH = {{ \App\Models\PayrollItem::DEFAULT_LUNCH }};
const DEFAULT_STAFF_SAVINGS = {{ \App\Models\PayrollItem::DEFAULT_STAFF_SAVINGS }};
let rowIndex = 0;

function fmt(n) { return n.toLocaleString('en-US', {minimumFractionDigits:0, maximumFractionDigits:0}); }
function num(id) { return parseFloat(document.getElementById(id).value) || 0; }

function selectedEmployeeIds(excludeRowId) {
    const ids = new Set();
    document.querySelectorAll('[name$="[employee_id]"]').forEach(sel => {
        if (sel.closest('tr').id !== 'row_' + excludeRowId && sel.value) ids.add(sel.value);
    });
    return ids;
}

// Mirrors PayrollItem::calculatePaye() server-side -- Uganda monthly PAYE bands.
function calcPaye(gross) {
    if (gross <= 335000) return 0;
    if (gross <= 410000) return (gross - 335000) * 0.20;
    if (gross <= 485000) return 15000 + (gross - 410000) * 0.25;
    let paye = 33750 + (gross - 485000) * 0.30;
    if (gross > 10000000) paye += (gross - 10000000) * 0.10;
    return paye;
}

function statutory(gross) {
    return {
        paye:   Math.round(calcPaye(gross) * 100) / 100,
        nssf5:  Math.round(gross * 0.05 * 100) / 100,
        nssf10: Math.round(gross * 0.10 * 100) / 100,
    };
}

// Statutory cells (PAYE / NSSF) auto-fill from gross until the user types in them.
// Clearing a cell hands it back to the automatic calculation.
function statInput(name, prefix, i, value, manual) {
    return `<input type="number" name="items[${i}][${name}]" id="${prefix}_${i}" class="form-control form-control-sm text-end stat-input${manual ? ' bg-warning-subtle' : ''}"
        value="${value}" min="0" step="any" data-manual="${manual ? 1 : 0}" oninput="onStatEdit(this,${i})" onblur="recalcRow(${i})" title="Auto-calculated — edit to override, clear to reset">`;
}

function addRow(empId = '', gross = 0, lunch = DEFAULT_LUNCH, transport = 0, staffSavings = DEFAULT_STAFF_SAVINGS, overrides = null) {
    const i = rowIndex++;
    const used = selectedEmployeeIds(-1);
    const opts = employees.map(e => {
        const isUsed = used.has(String(e.id)) && String(e.id) !== String(empId);
        return `<option value="${e.id}" data-salary="${e.basic_salary}" data-paytype="${e.pay_type || 'salary'}" ${String(e.id) === String(empId) ? 'selected' : ''} ${isUsed ? 'disabled' : ''}>${e.client ? e.client.name : '?'}${isUsed ? ' (already added)' : ''}</option>`;
    }).join('');

    // When editing a saved run, stored PAYE/NSSF that differ from the calculation are overrides.
    const auto = statutory(parseFloat(gross) || 0);
    const ov = {
        paye:   overrides && Math.abs(overrides.paye - auto.paye) > 0.005,
        nssf5:  overrides && Math.abs(overrides.nssf5 - auto.nssf5) > 0.005,
        nssf10: overrides && Math.abs(overrides.nssf10 - auto.nssf10) > 0.005,
    };

    const row = `<tr id="row_${i}">
        <td>
            <select name="items[${i}][employee_id]" class="form-select form-select-sm" onchange="onEmpChange(this,${i})" required>
                <option value="">— Select —</option>${opts}
            </select>
        </td>
        <td class="align-middle small" id="type_${i}">—</td>
        <td><input type="number" name="items[${i}][basic_salary]" id="gross_${i}" class="form-control form-control-sm" value="${gross}" min="0" step="any" oninput="recalcRow(${i})" required></td>
        <td>${statInput('paye', 'paye', i, ov.paye ? overrides.paye : '', ov.paye)}</td>
        <td>${statInput('nssf_employee', 'nssf5', i, ov.nssf5 ? overrides.nssf5 : '', ov.nssf5)}</td>
        <td>${statInput('nssf_employer', 'nssf10', i, ov.nssf10 ? overrides.nssf10 : '', ov.nssf10)}</td>
        <td><input type="number" name="items[${i}][lunch]" id="lunch_${i}" class="form-control form-control-sm" value="${lunch}" min="0" step="any" oninput="recalcRow(${i})"></td>
        <td><input type="number" name="items[${i}][transport]" id="transport_${i}" class="form-control form-control-sm" value="${transport}" min="0" step="any" oninput="recalcRow(${i})"></td>
        <td><input type="number" name="items[${i}][staff_savings]" id="staffsav_${i}" class="form-control form-control-sm" value="${staffSavings}" min="0" step="any" placeholder="${DEFAULT_STAFF_SAVINGS}" oninput="recalcRow(${i})" title="Blank = ${fmt(DEFAULT_STAFF_SAVINGS)}; enter 0 for no staff savings"></td>
        <td class="text-end align-middle fw-semibold" id="net_${i}">0</td>
        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="removeRow(${i})"><i class="bi bi-x"></i></button></td>
    </tr>`;
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend', row);
    showPayType(i);
    recalcRow(i);
}

function showPayType(i) {
    const sel  = document.querySelector('#row_' + i + ' select');
    const type = sel.value ? (sel.options[sel.selectedIndex].dataset.paytype || 'salary') : '';
    document.getElementById('type_' + i).innerHTML = type
        ? `<span class="badge ${type === 'commission' ? 'bg-info text-dark' : 'bg-light text-dark border'}">${PAY_TYPES[type] || type}</span>`
        : '—';
}

function refreshDisabled() {
    document.querySelectorAll('[name$="[employee_id]"]').forEach(sel => {
        const rowId = sel.closest('tr').id.replace('row_', '');
        const used = selectedEmployeeIds(rowId);
        Array.from(sel.options).forEach(opt => {
            if (!opt.value) return;
            const isUsed = used.has(opt.value);
            opt.disabled = isUsed;
            if (!opt.textContent.includes('(already added)') && isUsed) opt.textContent += ' (already added)';
            if (opt.textContent.includes('(already added)') && !isUsed) opt.textContent = opt.textContent.replace(' (already added)', '');
        });
    });
}

function setManual(el, manual) {
    el.dataset.manual = manual ? '1' : '0';
    el.classList.toggle('bg-warning-subtle', manual);
}

function onEmpChange(sel, i) {
    const opt = sel.options[sel.selectedIndex];
    const salary = opt.dataset.salary || 0;
    document.getElementById('gross_' + i).value = salary;
    showPayType(i);
    ['paye', 'nssf5', 'nssf10'].forEach(p => setManual(document.getElementById(p + '_' + i), false));
    recalcRow(i);
    refreshDisabled();
}

function onStatEdit(el, i) {
    setManual(el, el.value !== '');
    recalcRow(i);
}

function recalcRow(i) {
    const gross = num('gross_' + i);
    const auto  = statutory(gross);

    ['paye', 'nssf5', 'nssf10'].forEach(p => {
        const el = document.getElementById(p + '_' + i);
        if (el.dataset.manual !== '1' && document.activeElement !== el) el.value = auto[p];
    });

    const stat  = p => { const v = document.getElementById(p + '_' + i).value; return v === '' ? auto[p] : (parseFloat(v) || 0); };
    const paye  = stat('paye');
    const nssf5 = stat('nssf5');
    const net   = gross - paye - nssf5 - num('lunch_' + i) - staffSavingsOf(i) + num('transport_' + i);

    document.getElementById('net_' + i).textContent   = fmt(net);
    recalcTotal();
}

// A blank Staff Savings cell counts as the default, matching the server.
function staffSavingsOf(i) {
    const v = document.getElementById('staffsav_' + i).value;
    return v === '' ? DEFAULT_STAFF_SAVINGS : (parseFloat(v) || 0);
}

function removeRow(i) {
    const row = document.getElementById('row_' + i);
    if (row) row.remove();
    recalcTotal();
}

function sumCells(prefix) {
    let total = 0;
    document.querySelectorAll('#itemsBody [id^="' + prefix + '_"]').forEach(el => {
        total += parseFloat(el.tagName === 'INPUT' ? el.value : el.textContent.replace(/,/g, '')) || 0;
    });
    return total;
}

function recalcTotal() {
    document.getElementById('totalGross').textContent     = fmt(sumCells('gross'));
    document.getElementById('totalPaye').textContent      = fmt(sumCells('paye'));
    document.getElementById('totalNssf5').textContent     = fmt(sumCells('nssf5'));
    document.getElementById('totalNssf10').textContent    = fmt(sumCells('nssf10'));
    document.getElementById('totalLunch').textContent     = fmt(sumCells('lunch'));
    document.getElementById('totalTransport').textContent = fmt(sumCells('transport'));
    let staffSavTotal = 0;
    document.querySelectorAll('#itemsBody [id^="staffsav_"]').forEach(el => { staffSavTotal += staffSavingsOf(el.id.replace('staffsav_', '')); });
    document.getElementById('totalStaffSavings').textContent = fmt(staffSavTotal);
    document.getElementById('grandTotal').textContent     = fmt(sumCells('net'));
}

function addAllEmployees() {
    document.getElementById('itemsBody').innerHTML = '';
    rowIndex = 0;
    employees.filter(e => e.status === 'active').forEach(e => addRow(e.id, e.basic_salary));
}

existingItems.forEach(it => addRow(
    it.employee_id, (parseFloat(it.basic_salary) || 0) + (parseFloat(it.allowances) || 0), it.lunch, it.transport, it.staff_savings,
    { paye: it.paye, nssf5: it.nssf_employee, nssf10: it.nssf_employer }
));
refreshDisabled();
</script>
@endpush
