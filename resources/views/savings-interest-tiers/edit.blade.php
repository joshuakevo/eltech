@extends('layouts.app')
@section('title','Savings Interest Tiers')
@section('content')
<div class="mb-4">
    <h4 class="mb-0 fw-semibold">Savings Interest Tiers</h4>
    <p class="text-muted small mb-0">Ordinary Savings interest, worked out on each month-end balance: <strong>base interest + (balance − tier's Balance From) × annual rate ÷ 12</strong>, using the tier the balance falls in. E.g. 7,000,000 → 16,667 + 2,000,000 × 6% ÷ 12 = 26,667.</p>
</div>

<div class="row justify-content-center">
<div class="col-lg-9">
<form method="POST" action="{{ route('savings-interest-tiers.update') }}">
@csrf @method('PUT')
<div class="card shadow-sm">
    <div class="card-body p-3">

        @if(session('error'))
            <div class="alert alert-danger small mb-3">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger small mb-3">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-semibold mb-0">Tiers</h6>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="addTier()">
                <i class="bi bi-plus"></i> Add Tier
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0" id="tiersTable">
                <thead class="table-light">
                    <tr>
                        <th style="width:24%">Balance From (UGX)</th>
                        <th style="width:24%">Balance To (UGX) <span class="text-muted fw-normal" style="font-size:.7rem">(blank = and above)</span></th>
                        <th style="width:24%">Base interest / month (UGX)</th>
                        <th style="width:20%">Annual Rate (%)</th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody id="tiersBody">
                @foreach($tiers as $i => $tier)
                    <tr>
                        <td><input type="number" step="0.01" min="0" name="tiers[{{ $i }}][min_balance]" class="form-control form-control-sm" value="{{ old("tiers.$i.min_balance", $tier->min_balance) }}" required></td>
                        <td><input type="number" step="0.01" min="0" name="tiers[{{ $i }}][max_balance]" class="form-control form-control-sm" value="{{ old("tiers.$i.max_balance", $tier->max_balance) }}" placeholder="and above"></td>
                        <td><input type="number" step="0.01" min="0" name="tiers[{{ $i }}][base_interest]" class="form-control form-control-sm" value="{{ old("tiers.$i.base_interest", $tier->base_interest ?? 0) }}"></td>
                        <td><input type="number" step="0.01" min="0" max="100" name="tiers[{{ $i }}][rate]" class="form-control form-control-sm" value="{{ old("tiers.$i.rate", $tier->rate) }}" required></td>
                        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="removeTier(this)"><i class="bi bi-trash"></i></button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>
            Used by Running Interest for Ordinary Savings (month-end balances). Tiers with a 0% rate pay nothing.
        </p>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2 py-2 px-3">
        <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save Tiers</button>
    </div>
</div>
</form>
</div>
</div>

<script>
let tierCount = {{ count($tiers) }};
function addTier() {
    const tbody = document.getElementById('tiersBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="number" step="0.01" min="0" name="tiers[${tierCount}][min_balance]" class="form-control form-control-sm" required></td>
        <td><input type="number" step="0.01" min="0" name="tiers[${tierCount}][max_balance]" class="form-control form-control-sm" placeholder="and above"></td>
        <td><input type="number" step="0.01" min="0" name="tiers[${tierCount}][base_interest]" class="form-control form-control-sm" value="0"></td>
        <td><input type="number" step="0.01" min="0" max="100" name="tiers[${tierCount}][rate]" class="form-control form-control-sm" required></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="removeTier(this)"><i class="bi bi-trash"></i></button></td>
    `;
    tbody.appendChild(tr);
    tierCount++;
}
function removeTier(btn) {
    if (document.querySelectorAll('#tiersBody tr').length > 1) {
        btn.closest('tr').remove();
    }
}
</script>
@endsection
