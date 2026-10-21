@extends('layouts.app')
@section('title', 'AI Assistant')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Settings</a></li>
    <li class="breadcrumb-item active">AI Assistant</li>
@endsection
@section('content')
<div class="page-header">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-stars me-1 text-primary"></i>AI Assistant</h4>
        <div class="text-muted small">The floating "Ask" button: staff ask questions about savings, loans and members in plain language.</div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('settings.ai.save') }}" class="card">
            @csrf
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="ai_enabled" value="1" id="aiOn" @checked($cfg['enabled'])>
                    <label class="form-check-label fw-semibold" for="aiOn">Show the AI assistant to permitted users</label>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Provider</label>
                    <select name="ai_provider" id="aiProvider" class="form-select">
                        @foreach($presets as $key => $p)
                            <option value="{{ $key }}" data-base="{{ $p['base'] }}" data-model="{{ $p['model'] }}" @selected($provider === $key)>{{ $p['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">API address</label>
                        <input name="ai_base_url" id="aiBase" class="form-control font-monospace" value="{{ $cfg['base'] }}" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Model</label>
                        <input name="ai_model" id="aiModel" class="form-control font-monospace" value="{{ $cfg['model'] }}" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">API key</label>
                    <input name="ai_api_key" type="password" class="form-control" autocomplete="off" placeholder="{{ $hasKey ? '•••••••• saved — leave blank to keep it' : 'Paste the key from the provider' }}">
                    <div class="form-text">Stored encrypted on the server and never shown again.</div>
                </div>
                <div class="mb-3" style="max-width:220px">
                    <label class="form-label small fw-semibold">Questions per user per day</label>
                    <input name="ai_daily_limit" type="number" min="1" max="1000" class="form-control" value="{{ $cfg['limit'] }}" required>
                </div>
                <button class="btn btn-primary">Save</button>
            </div>
        </form>
        @if($hasKey)
        <form method="POST" action="{{ route('settings.ai.test') }}" class="mt-2">@csrf<button class="btn btn-outline-secondary btn-sm"><i class="bi bi-plug me-1"></i>Test connection</button></form>
        @endif
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">Getting a free key (Groq)</div>
            <div class="card-body small">
                <ol class="mb-0 ps-3">
                    <li>Go to <strong>console.groq.com</strong> and sign up (no card needed).</li>
                    <li>Open <strong>API Keys</strong> → <strong>Create API Key</strong>, copy it.</li>
                    <li>Paste it above, tick <em>Show the AI assistant</em>, Save, then <strong>Test connection</strong>.</li>
                </ol>
                <div class="text-muted mt-2">If a model name stops working, the provider has retired it — pick a current model from their models page and paste its name.</div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Privacy</div>
            <div class="card-body small text-muted">
                The assistant can only <strong>read</strong> figures — it cannot post, change or delete anything. It sends the provider <strong>member numbers and amounts only</strong>; names, phones and emails stay on this server and are put back in when the answer is shown. Users only get answers for areas their role can already see. Every question is recorded in the Audit Log.
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.getElementById('aiProvider').addEventListener('change', function () {
    const o = this.selectedOptions[0];
    if (o.dataset.base) document.getElementById('aiBase').value = o.dataset.base;
    if (o.dataset.model) document.getElementById('aiModel').value = o.dataset.model;
});
</script>
@endpush
@endsection
