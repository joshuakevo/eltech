@extends('layouts.app')
@section('title', 'Loan Schedule Generator')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
    <li class="breadcrumb-item active">Schedule Generator</li>
@endsection
@push('styles')
<style>
    .print-only { display: none; }
    @media print {
        @page { size: A4; margin: 12mm; }
        html, body { height: auto !important; min-height: 0 !important; padding: 0 !important; margin: 0 !important; background: #fff !important; }
        .sidebar, .topbar, .bnav, .no-print, #navProgress, #aiFab, #aiPanel, .modal, .sidebar-overlay { display: none !important; }
        .main-wrap { margin: 0 !important; padding: 0 !important; min-height: 0 !important; }
        .page-content { padding: 0 !important; }
        .row > .col-lg-8 { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; }
        .card { border: 0 !important; box-shadow: none !important; margin-bottom: 8px !important; break-inside: auto; }
        .table-responsive { overflow: visible !important; }
        .print-only { display: block !important; }
        .print-head { background: #0f2444 !important; color: #fff !important; padding: 10px 14px; margin-bottom: 10px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
    .calc-figure { font-size: 1.5rem; font-weight: 700; line-height: 1.1; }
</style>
@endpush
@section('content')
<div class="page-header no-print">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-calculator me-1 text-primary"></i>Loan Schedule Generator</h4>
        <div class="text-muted small">Try out amounts, rates and terms before giving a loan — nothing is saved. Uses the same schedule calculation as a real disbursement.</div>
    </div>
</div>

<div class="row g-3">
    {{-- Inputs --}}
    <div class="col-lg-4 no-print">
        <form method="GET" action="{{ route('loans.calculator') }}" class="card" id="calcForm">
            <div class="card-body">
                <label class="form-label small fw-semibold mb-1">Loan product</label>
                <select name="loan_product_id" id="calcProduct" class="form-select mb-3">
                    <option value="">— Custom terms —</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" @selected(($product->id ?? null) == $p->id)
                            data-rate="{{ round($p->interest_rate / 12, 4) + 0 }}" data-method="{{ $p->interest_method }}" data-term="{{ $p->term_months }}"
                            data-frequency="{{ $p->repayment_frequency ?? 'monthly' }}" data-min="{{ $p->min_amount }}" data-max="{{ $p->max_amount }}">{{ $p->name }}</option>
                    @endforeach
                </select>

                <div class="btn-group w-100 mb-3" role="group">
                    <input type="radio" class="btn-check" name="mode" id="modeAmount" value="amount" @checked($in['mode'] !== 'afford')>
                    <label class="btn btn-outline-primary btn-sm" for="modeAmount">By amount</label>
                    <input type="radio" class="btn-check" name="mode" id="modeAfford" value="afford" @checked($in['mode'] === 'afford')>
                    <label class="btn btn-outline-primary btn-sm" for="modeAfford">What can they afford?</label>
                </div>

                <div class="mb-3" id="boxAmount">
                    <label class="form-label small fw-semibold mb-1">Loan amount</label>
                    <input type="number" name="principal" class="form-control" min="0" step="1000" value="{{ $in['mode'] !== 'afford' && $in['principal'] ? $in['principal'] + 0 : '' }}" placeholder="e.g. 5000000">
                    <div class="form-text" id="rangeHint">@if($product && ($product->min_amount || $product->max_amount))Product range: {{ number_format($product->min_amount ?? 0) }} – {{ $product->max_amount ? number_format($product->max_amount) : 'no limit' }}@endif</div>
                </div>
                <div class="mb-3" id="boxAfford">
                    <label class="form-label small fw-semibold mb-1">Installment the member can pay</label>
                    <input type="number" name="installment" class="form-control" min="0" step="1000" value="{{ $in['installment'] ? $in['installment'] + 0 : '' }}" placeholder="e.g. 800000">
                    <div class="form-text">Gives the largest loan whose installment fits.</div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold mb-1">Rate % per month</label>
                        <input type="number" name="rate" id="calcRate" class="form-control" min="0" step="0.01" value="{{ $in['monthly'] + 0 }}" placeholder="e.g. 2.8">
                        <div class="form-text" id="rateMonthly">= {{ round($in['rate'], 2) + 0 }}% per year</div>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold mb-1">Method</label>
                        <select name="method" id="calcMethod" class="form-select">
                            <option value="reducing" @selected($in['method'] === 'reducing')>Reducing balance</option>
                            <option value="flat" @selected($in['method'] === 'flat')>Flat</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold mb-1">Term (months)</label>
                        <input type="number" name="term" id="calcTerm" class="form-control" min="1" value="{{ $in['term'] }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold mb-1">Repayment</label>
                        <select name="frequency" id="calcFrequency" class="form-select">
                            @foreach(\App\Models\Loan::FREQUENCIES as $key => [$label])
                                <option value="{{ $key }}" @selected($in['frequency'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold mb-1">Disbursement date</label>
                        <input type="date" name="date" class="form-control" value="{{ $in['date'] }}">
                    </div>
                </div>

                <details class="mb-3" @if($in['app_fee'] > 0 || $in['mgmt_rate'] != 1.5 || $in['ins_rate'] != 1.5) open @endif>
                    <summary class="small fw-semibold text-primary">Fees at disbursement</summary>
                    <div class="row g-2 mt-1">
                        <div class="col-4"><label class="form-label small mb-1">Management %</label><input type="number" name="mgmt_rate" class="form-control form-control-sm" step="0.01" min="0" value="{{ $in['mgmt_rate'] + 0 }}"></div>
                        <div class="col-4"><label class="form-label small mb-1">Insurance %</label><input type="number" name="ins_rate" class="form-control form-control-sm" step="0.01" min="0" value="{{ $in['ins_rate'] + 0 }}"></div>
                        <div class="col-4"><label class="form-label small mb-1">Application</label><input type="number" name="app_fee" class="form-control form-control-sm" step="1000" min="0" value="{{ $in['app_fee'] + 0 }}"></div>
                    </div>
                </details>

                <button class="btn btn-primary w-100"><i class="bi bi-calculator me-1"></i>Generate schedule</button>
            </div>
        </form>
    </div>

    {{-- Results --}}
    <div class="col-lg-8">
        @if($error)
            <div class="alert alert-warning">{{ $error }}</div>
        @elseif(!$result)
            <div class="card"><div class="card-body text-center text-muted py-5">
                <i class="bi bi-calculator fs-1 d-block mb-2 opacity-50"></i>
                Pick a loan product (or custom terms), enter an amount — or the installment the member can pay — and generate the schedule.
            </div></div>
        @else
            @php $orgLogoP = \App\Models\SystemSetting::get('org_logo'); @endphp
            <div class="print-only print-head">
                @if($orgLogoP)<img src="{{ asset($orgLogoP) }}" alt="" style="height:32px;max-width:170px;object-fit:contain">@else<strong>{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}</strong>@endif
                <span style="float:right;font-size:11px;opacity:.85;line-height:32px">Loan Repayment Schedule — estimate · {{ now()->format('d M Y') }}</span>
            </div>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <div class="fw-bold">{{ $product->name ?? 'Custom terms' }} — estimate</div>
                            <div class="text-muted small">{{ number_format($in['principal'], $dp) }} at {{ $in['monthly'] + 0 }}% per month ({{ round($in['rate'], 2) + 0 }}% p.a.) {{ $in['method'] === 'flat' ? 'flat' : 'reducing balance' }}, {{ $in['term'] }} months, {{ strtolower(\App\Models\Loan::FREQUENCIES[$in['frequency']][0] ?? 'monthly') }} · disbursed {{ \Carbon\Carbon::parse($in['date'])->format('d M Y') }}, matures {{ $result['maturity']->format('d M Y') }}</div>
                            @if($in['mode'] === 'afford')<div class="small text-success mt-1"><i class="bi bi-check-circle me-1"></i>Largest loan whose installment fits {{ number_format($in['installment'], $dp) }}</div>@endif
                            @if($result['outOfRange'])<div class="small text-danger mt-1"><i class="bi bi-exclamation-triangle me-1"></i>Outside this product's amount range ({{ number_format($product->min_amount ?? 0) }} – {{ $product->max_amount ? number_format($product->max_amount) : 'no limit' }})</div>@endif
                        </div>
                        <div class="d-flex gap-2 no-print">
                            <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
                            <a href="{{ request()->fullUrlWithQuery(['format' => 'excel']) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6 col-md-3"><div class="text-muted small">{{ $result['periods'] > 1 && ($result['rows'][0]['step_months'] ?? 1) > 1 ? 'Installment' : 'Monthly installment' }}</div><div class="calc-figure text-primary">{{ number_format($result['installment'], 0) }}</div>@if(abs($result['last'] - $result['installment']) > 1)<div class="small text-muted">last {{ number_format($result['last'], 0) }}</div>@endif</div>
                        <div class="col-6 col-md-3"><div class="text-muted small">Installments</div><div class="calc-figure">{{ $result['periods'] }}</div></div>
                        <div class="col-6 col-md-3"><div class="text-muted small">Total interest</div><div class="calc-figure">{{ number_format($result['interest'], 0) }}</div></div>
                        <div class="col-6 col-md-3"><div class="text-muted small">Total repayable</div><div class="calc-figure">{{ number_format($result['repayable'], 0) }}</div></div>
                    </div>
                    <hr>
                    <div class="row g-2 small">
                        <div class="col-6 col-md-3"><span class="text-muted">Loan amount</span><div class="fw-semibold">{{ number_format($in['principal'], 0) }}</div></div>
                        <div class="col-6 col-md-3"><span class="text-muted">Management ({{ $in['mgmt_rate'] + 0 }}%)</span><div class="fw-semibold">{{ number_format($result['fees']['management'], 0) }}</div></div>
                        <div class="col-6 col-md-3"><span class="text-muted">Insurance ({{ $in['ins_rate'] + 0 }}%) + application</span><div class="fw-semibold">{{ number_format($result['fees']['insurance'] + $result['fees']['application'], 0) }}</div></div>
                        <div class="col-6 col-md-3"><span class="text-muted">Member receives (if fees deducted)</span><div class="fw-bold text-success">{{ number_format($result['net'], 0) }}</div></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Repayment schedule</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light"><tr><th class="ps-3">#</th><th>Due date</th><th class="text-end">Principal</th><th class="text-end">Interest</th><th class="text-end">Installment</th><th class="text-end pe-3">Balance after</th></tr></thead>
                        <tbody class="small">
                        @foreach($result['rows'] as $r)
                            <tr>
                                <td class="ps-3">{{ $r['installment_no'] }}</td>
                                <td>{{ \Carbon\Carbon::parse($r['due_date'])->format('d M Y') }}</td>
                                <td class="text-end">{{ number_format($r['principal_due'], 0) }}</td>
                                <td class="text-end">{{ number_format($r['interest_due'], 0) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($r['total_due'], 0) }}</td>
                                <td class="text-end pe-3">{{ number_format($r['balance_after'], 0) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-light fw-bold small"><tr><td colspan="2" class="ps-3">Total</td><td class="text-end">{{ number_format($in['principal'], 0) }}</td><td class="text-end">{{ number_format($result['interest'], 0) }}</td><td class="text-end">{{ number_format($result['repayable'], 0) }}</td><td></td></tr></tfoot>
                    </table>
                </div>
                <div class="card-footer bg-white small text-muted">Estimate only. Interest on running loans is charged by days between installments, so actual figures can differ slightly from this schedule.</div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
(function () {
    const product = document.getElementById('calcProduct');
    const rate = document.getElementById('calcRate');
    function setMode() {
        const afford = document.getElementById('modeAfford').checked;
        document.getElementById('boxAmount').style.display = afford ? 'none' : '';
        document.getElementById('boxAfford').style.display = afford ? '' : 'none';
    }
    document.querySelectorAll('input[name=mode]').forEach(r => r.addEventListener('change', setMode));
    setMode();
    // Choosing a product fills its terms (still editable)
    product.addEventListener('change', function () {
        const o = this.selectedOptions[0];
        if (!o || !o.value) return;
        rate.value = o.dataset.rate;
        document.getElementById('calcMethod').value = o.dataset.method;
        document.getElementById('calcTerm').value = o.dataset.term;
        document.getElementById('calcFrequency').value = o.dataset.frequency || 'monthly';
        const min = +o.dataset.min || 0, max = +o.dataset.max || 0;
        document.getElementById('rangeHint').textContent = (min || max) ? 'Product range: ' + min.toLocaleString() + ' – ' + (max ? max.toLocaleString() : 'no limit') : '';
        rate.dispatchEvent(new Event('input'));
    });
    rate.addEventListener('input', function () {
        const v = parseFloat(this.value) || 0;
        document.getElementById('rateMonthly').textContent = '= ' + (+(v * 12).toFixed(2)) + '% per year';
    });
})();
</script>
@endpush
@endsection
