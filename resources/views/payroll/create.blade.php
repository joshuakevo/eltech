@extends('layouts.app')
@section('title', 'New Payroll Run')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('payroll.index') }}">Payroll</a></li>
    <li class="breadcrumb-item active">New Run</li>
@endsection
@section('content')
<div class="card">
    <div class="card-header fw-semibold">New Payroll Run</div>
    <div class="card-body">
    <form method="POST" action="{{ route('payroll.store') }}" id="payrollForm">
        @csrf
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Month <span class="text-danger">*</span></label>
                <select name="period_month" class="form-select" required>
                    @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" {{ old('period_month', now()->month) == $m ? 'selected' : '' }}>
                        {{ date('F', mktime(0,0,0,$m,1)) }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Year <span class="text-danger">*</span></label>
                <input type="number" name="period_year" class="form-control" value="{{ old('period_year', now()->year) }}" min="2000" max="2100" required>
            </div>
            <div class="col-md-7">
                <label class="form-label fw-semibold">Description</label>
                <input type="text" name="description" class="form-control" value="{{ old('description') }}" placeholder="Optional note">
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-semibold mb-0">Employees</h6>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addAllEmployees()">
                <i class="bi bi-people me-1"></i>Add All Active
            </button>
        </div>

        <div class="form-text mb-2">
            PAYE, NSSF 5% (employee) and NSSF 10% (employer) are calculated automatically from Gross Pay (Basic + Allowances) as you type.
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm" id="itemsTable">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th style="width:140px">Basic Salary</th>
                        <th style="width:120px">Allowances</th>
                        <th style="width:130px" class="text-end">Gross Pay</th>
                        <th style="width:120px" class="text-end">PAYE</th>
                        <th style="width:110px" class="text-end">NSSF 5%</th>
                        <th style="width:110px" class="text-end">NSSF 10%</th>
                        <th style="width:120px">Deductions</th>
                        <th style="width:140px" class="text-end">Net Pay</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    {{-- rows added by JS --}}
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="fw-semibold text-end">Totals:</td>
                        <td class="text-end fw-semibold" id="totalGross">0</td>
                        <td class="text-end fw-semibold" id="totalPaye">0</td>
                        <td class="text-end fw-semibold" id="totalNssf5">0</td>
                        <td class="text-end fw-semibold" id="totalNssf10">0</td>
                        <td class="text-end fw-semibold" id="totalDeduct">0</td>
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
            <button class="btn btn-primary">Save Payroll Run</button>
            <a href="{{ route('payroll.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
const employees = @json($employees);
let rowIndex = 0;

function fmt(n) { return n.toLocaleString('en-US', {minimumFractionDigits:0, maximumFractionDigits:0}); }

function selectedEmployeeIds(excludeRowId) {
    const ids = new Set();
    document.querySelectorAll('[name$="[employee_id]"]').forEach(sel => {
        if (sel.closest('tr').id !== 'row_' + excludeRowId && sel.value) ids.add(sel.value);
    });
    return ids;
}

// Mirrors PayrollItem::calculatePaye() server-side -- Uganda monthly PAYE bands.
function calcPaye(gross) {
    if (gross <= 235000) return 0;
    if (gross <= 335000) return (gross - 235000) * 0.10;
    if (gross <= 410000) return 10000 + (gross - 335000) * 0.20;
    let paye = 25000 + (gross - 410000) * 0.30;
    if (gross > 10000000) paye += (gross - 10000000) * 0.10;
    return paye;
}

function addRow(empId = '', basic = 0, allow = 0, deduct = 0) {
    const i = rowIndex++;
    const used = selectedEmployeeIds(-1);
    const opts = employees.map(e => {
        const isUsed = used.has(String(e.id)) && String(e.id) !== String(empId);
        return `<option value="${e.id}" data-salary="${e.basic_salary}" ${String(e.id) === String(empId) ? 'selected' : ''} ${isUsed ? 'disabled' : ''}>${e.client ? e.client.name : '?'} — ${e.employee_number}${isUsed ? ' (already added)' : ''}</option>`;
    }).join('');
    const row = `<tr id="row_${i}">
        <td>
            <select name="items[${i}][employee_id]" class="form-select form-select-sm" onchange="onEmpChange(this,${i})" required>
                <option value="">— Select —</option>${opts}
            </select>
        </td>
        <td><input type="number" name="items[${i}][basic_salary]" id="basic_${i}" class="form-control form-control-sm" value="${basic}" min="0" step="1000" oninput="recalcRow(${i})" required></td>
        <td><input type="number" name="items[${i}][allowances]" id="allow_${i}" class="form-control form-control-sm" value="${allow}" min="0" step="1000" oninput="recalcRow(${i})"></td>
        <td class="text-end align-middle" id="gross_${i}">0</td>
        <td class="text-end align-middle text-danger" id="paye_${i}">0</td>
        <td class="text-end align-middle text-danger" id="nssf5_${i}">0</td>
        <td class="text-end align-middle text-muted" id="nssf10_${i}">0</td>
        <td><input type="number" name="items[${i}][deductions]" id="deduct_${i}" class="form-control form-control-sm" value="${deduct}" min="0" step="1000" oninput="recalcRow(${i})"></td>
        <td class="text-end align-middle fw-semibold" id="net_${i}">0</td>
        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="removeRow(${i})"><i class="bi bi-x"></i></button></td>
    </tr>`;
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend', row);
    recalcRow(i);
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

function onEmpChange(sel, i) {
    const opt = sel.options[sel.selectedIndex];
    const salary = opt.dataset.salary || 0;
    document.getElementById('basic_' + i).value = salary;
    recalcRow(i);
    refreshDisabled();
}

function recalcRow(i) {
    const b = parseFloat(document.getElementById('basic_' + i).value) || 0;
    const a = parseFloat(document.getElementById('allow_' + i).value) || 0;
    const d = parseFloat(document.getElementById('deduct_' + i).value) || 0;
    const gross  = b + a;
    const paye   = calcPaye(gross);
    const nssf5  = gross * 0.05;
    const nssf10 = gross * 0.10;
    const net    = gross - paye - nssf5 - d;

    document.getElementById('gross_' + i).textContent  = fmt(gross);
    document.getElementById('paye_' + i).textContent   = fmt(paye);
    document.getElementById('nssf5_' + i).textContent  = fmt(nssf5);
    document.getElementById('nssf10_' + i).textContent = fmt(nssf10);
    document.getElementById('net_' + i).textContent    = fmt(net);
    recalcTotal();
}

function removeRow(i) {
    const row = document.getElementById('row_' + i);
    if (row) row.remove();
    recalcTotal();
}

function sumCells(prefix) {
    let total = 0;
    document.querySelectorAll('[id^="' + prefix + '_"]').forEach(el => {
        total += parseFloat(el.textContent.replace(/,/g, '')) || 0;
    });
    return total;
}

function recalcTotal() {
    document.getElementById('totalGross').textContent  = fmt(sumCells('gross'));
    document.getElementById('totalPaye').textContent   = fmt(sumCells('paye'));
    document.getElementById('totalNssf5').textContent  = fmt(sumCells('nssf5'));
    document.getElementById('totalNssf10').textContent = fmt(sumCells('nssf10'));

    let deductTotal = 0;
    document.querySelectorAll('[id^="deduct_"]').forEach(el => { deductTotal += parseFloat(el.value) || 0; });
    document.getElementById('totalDeduct').textContent = fmt(deductTotal);

    document.getElementById('grandTotal').textContent = fmt(sumCells('net'));
}

function addAllEmployees() {
    document.getElementById('itemsBody').innerHTML = '';
    rowIndex = 0;
    employees.forEach(e => addRow(e.id, e.basic_salary));
}
</script>
@endpush
