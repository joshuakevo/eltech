{{-- Savings statement pop-up: date range -> Excel / PDF ($saving, $statementUrl) --}}
@php $firstSavTx = $saving->transactions->min('transaction_date'); @endphp
<div class="modal fade" id="savingsStatementModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="GET" action="{{ $statementUrl }}" class="modal-content" onsubmit="setTimeout(() => bootstrap.Modal.getInstance(document.getElementById('savingsStatementModal'))?.hide(), 400)">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-file-earmark-text me-1 text-primary"></i>Statement — {{ $saving->account_number }} · {{ $saving->client->name ?? '' }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">From</label>
                        <input type="date" name="from_date" class="form-control" value="{{ $firstSavTx ? \Carbon\Carbon::parse($firstSavTx)->toDateString() : optional($saving->opened_date)->toDateString() }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">To</label>
                        <input type="date" name="to_date" class="form-control" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}">
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-1 mt-2">
                    @foreach(['This month' => [now()->startOfMonth(), today()], 'Last month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()], 'Last 3 months' => [now()->subMonthsNoOverflow(3)->startOfMonth(), today()], 'This year' => [now()->startOfYear(), today()]] as $label => [$f, $t])
                        <button type="button" class="btn btn-light btn-sm py-0" onclick="this.form.from_date.value='{{ $f->toDateString() }}';this.form.to_date.value='{{ $t->toDateString() }}'">{{ $label }}</button>
                    @endforeach
                </div>
                <div class="form-text mt-2">Opening balance at the start date, every transaction in the period, and the closing balance at the end date.</div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="format" value="excel" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
                <button type="submit" name="format" value="pdf" class="btn btn-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>
            </div>
        </form>
    </div>
</div>
