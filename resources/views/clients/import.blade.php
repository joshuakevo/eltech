@extends('layouts.app')
@section('title', 'Import Members')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">Clients</a></li>
    <li class="breadcrumb-item active">Import members</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h4 class="fw-bold mb-0">Import Members</h4>
        <div class="text-muted small">Add many members at once from a CSV file. Existing member codes are skipped, never overwritten.</div>
    </div>
    <a href="{{ route('clients.import.template') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Download template</a>
</div>

@if(!$preview)
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('clients.import.preview') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-6">
                <label class="form-label small fw-semibold">CSV file</label>
                <input type="file" name="file" accept=".csv,text/csv" class="form-control @error('file') is-invalid @enderror" required>
                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-eye me-1"></i>Preview</button></div>
        </form>
        <div class="small text-muted mt-3">
            Columns (first row = headings): <code>client_number</code>, <code>name</code> (required), <code>first_name</code>, <code>last_name</code>,
            <code>segment</code> (e.g. KDF), <code>relationship_manager</code> (user's full name), <code>status</code> (active/inactive), <code>membership_fee</code>, <code>phone</code>, <code>email</code>.
            In Excel: <i>File → Save As → CSV (Comma delimited)</i>. Nothing is saved until you confirm the preview.
        </div>
    </div>
</div>
@else
@php
    $rows = collect($preview['rows']);
    $ok = $rows->filter(fn ($r) => !$r['issues'] && !$r['skip']);
    $bad = $rows->filter(fn ($r) => $r['issues']);
    $skip = $rows->filter(fn ($r) => $r['skip']);
@endphp
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
        <div>
            <div class="fw-semibold">{{ $preview['file'] }}</div>
            <div class="small">
                <span class="text-success fw-semibold">{{ $ok->count() }} to import</span> ·
                <span class="text-secondary">{{ $skip->count() }} already exist (skipped)</span> ·
                <span class="{{ $bad->count() ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $bad->count() }} with problems (not imported)</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('clients.import.cancel') }}">@csrf<button class="btn btn-outline-secondary btn-sm">Cancel</button></form>
            <form method="POST" action="{{ route('clients.import.confirm') }}" onsubmit="if (this.dataset.sent) return false; if (!confirm('Import {{ $ok->count() }} member(s)?')) return false; this.dataset.sent = 1; this.querySelector('button').disabled = true; return true;">
                @csrf
                <button class="btn btn-success btn-sm" {{ $ok->count() ? '' : 'disabled' }}><i class="bi bi-check2-circle me-1"></i>Import {{ $ok->count() }} member(s)</button>
            </form>
        </div>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle small">
            <thead class="table-light"><tr><th class="ps-3">Line</th><th>Code</th><th>Name</th><th>Segment</th><th>Relationship manager</th><th>Status</th><th class="text-end">Fee</th><th class="pe-3">Result</th></tr></thead>
            <tbody>
            @foreach($rows as $r)
                <tr class="{{ $r['issues'] ? 'table-danger' : ($r['skip'] ? 'table-secondary' : '') }}">
                    <td class="ps-3 text-muted">{{ $r['line'] }}</td>
                    <td class="font-monospace">{{ $r['client_number'] }}</td>
                    <td>{{ $r['name'] }}</td>
                    <td>{{ $r['segment'] ?: '—' }}</td>
                    <td>{{ $r['rm'] ?: '—' }}</td>
                    <td>{{ ucfirst($r['status']) }}</td>
                    <td class="text-end">{{ number_format($r['membership_fee'], 0) }}</td>
                    <td class="pe-3">
                        @if($r['issues'])<span class="text-danger">{{ implode('; ', $r['issues']) }}</span>
                        @elseif($r['skip'])<span class="text-secondary">{{ $r['skip'] }}</span>
                        @else<span class="text-success"><i class="bi bi-check2"></i> New member</span>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
