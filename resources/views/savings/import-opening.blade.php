@extends('layouts.app')
@section('title', 'Import Opening Balances')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('savings.index') }}">Savings</a></li>
    <li class="breadcrumb-item active">Import opening balances</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h4 class="fw-bold mb-0">Import Savings Opening Balances</h4>
        <div class="text-muted small">Bring members' balances over from another system. Each member's account in the chosen product is opened (or their existing one used) and the balance is posted with a journal.</div>
    </div>
    <a href="{{ route('savings.import-opening.template') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Download template</a>
</div>

@if(!$preview)
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('savings.import-opening.preview') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Savings product</label>
                <select name="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                    @foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Balances as at</label>
                <input type="date" name="date" value="{{ old('date') }}" class="form-control @error('date') is-invalid @enderror" required>
                @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Offset account</label>
                <select name="offset_account_id" class="form-select" required>
                    @foreach($offsets as $a)<option value="{{ $a->id }}" @selected((old('offset_account_id') ?? $defaultOffset) == $a->id)>{{ $a->account_code }} {{ $a->account_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">CSV file</label>
                <input type="file" name="file" accept=".csv,text/csv" class="form-control @error('file') is-invalid @enderror" required>
                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-eye me-1"></i>Preview</button></div>
        </form>
        <div class="small text-muted mt-3">
            Columns (first row = headings): <code>client_number</code>, <code>balance</code> (required), <code>name</code> (optional, shown next to the system name so you can check).
            A negative balance opens the account overdrawn. Positive: DR offset / CR savings liability; negative: the reverse. Nothing is saved until you confirm the preview.
        </div>
    </div>
</div>
@else
@php
    $rows = collect($preview['rows']);
    $ok = $rows->filter(fn ($r) => !$r['issues'] && !$r['skip']);
    $bad = $rows->filter(fn ($r) => $r['issues']);
    $skip = $rows->filter(fn ($r) => !$r['issues'] && $r['skip']);
@endphp
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
        <div>
            <div class="fw-semibold">{{ $preview['file'] }} → {{ $preview['product'] }} as at {{ \Carbon\Carbon::parse($preview['date'])->format('d M Y') }} · offset {{ $preview['offset'] }}</div>
            <div class="small">
                <span class="text-success fw-semibold">{{ $ok->count() }} to post</span>
                (credit {{ number_format($ok->where('balance', '>', 0)->sum('balance'), 0) }}, overdrawn {{ number_format($ok->where('balance', '<', 0)->sum('balance'), 0) }}, net {{ number_format($ok->sum('balance'), 0) }}) ·
                <span class="text-secondary">{{ $skip->count() }} skipped</span> ·
                <span class="{{ $bad->count() ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $bad->count() }} with problems (not posted)</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('savings.import-opening.cancel') }}">@csrf<button class="btn btn-outline-secondary btn-sm">Cancel</button></form>
            <form method="POST" action="{{ route('savings.import-opening.confirm') }}" onsubmit="if (this.dataset.sent) return false; if (!confirm('Post {{ $ok->count() }} opening balance(s)?')) return false; this.dataset.sent = 1; this.querySelector('button').disabled = true; return true;">
                @csrf
                <button class="btn btn-success btn-sm" {{ $ok->count() ? '' : 'disabled' }}><i class="bi bi-check2-circle me-1"></i>Post {{ $ok->count() }} opening balance(s)</button>
            </form>
        </div>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle small">
            <thead class="table-light"><tr><th class="ps-3">Line</th><th>Code</th><th>Name in file</th><th>Member in system</th><th class="text-end">Opening balance</th><th class="pe-3">Result</th></tr></thead>
            <tbody>
            @foreach($rows as $r)
                <tr class="{{ $r['issues'] ? 'table-danger' : ($r['skip'] ? 'table-secondary' : '') }}">
                    <td class="ps-3 text-muted">{{ $r['line'] }}</td>
                    <td class="font-monospace">{{ $r['client_number'] }}</td>
                    <td>{{ $r['file_name'] ?: '—' }}</td>
                    <td>{{ $r['client_name'] ?: '—' }}</td>
                    <td class="text-end fw-semibold {{ $r['balance'] < 0 ? 'text-danger' : '' }}">{{ number_format($r['balance'], 0) }}</td>
                    <td class="pe-3">
                        @if($r['issues'])<span class="text-danger">{{ implode('; ', $r['issues']) }}</span>
                        @elseif($r['skip'])<span class="text-secondary">{{ $r['skip'] }}</span>
                        @else<span class="text-success"><i class="bi bi-check2"></i> {{ $r['note'] ?? 'New account' }}{{ $r['balance'] < 0 ? ' · overdrawn' : '' }}</span>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
