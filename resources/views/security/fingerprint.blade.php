@extends('layouts.app')
@section('title', 'Fingerprint Sign-in')
@section('breadcrumb')
    <li class="breadcrumb-item active">Fingerprint sign-in</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h4 class="fw-bold mb-0">Fingerprint Sign-in</h4>
        <div class="text-muted small">Sign in without a password using your phone's fingerprint, face unlock, Windows Hello or Touch ID. Your fingerprint never leaves your device.</div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body text-center py-4">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:72px;height:72px;background:#e8efff"><i class="bi bi-fingerprint text-primary" style="font-size:2.2rem"></i></div>
                <div class="fw-semibold mb-1">Set up this device</div>
                <div class="text-muted small mb-3" id="fpSupportText">Checking whether this device supports fingerprint sign-in…</div>
                <button type="button" class="btn btn-primary d-none" id="fpEnrollBtn"><i class="bi bi-fingerprint me-1"></i>Enable fingerprint sign-in</button>
                <div id="fpEnrollMsg" class="small mt-2"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Your devices</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Device</th><th>Added</th><th>Last used</th><th class="pe-3"></th></tr></thead>
                    <tbody>
                    @forelse($credentials as $c)
                        <tr>
                            <td class="ps-3"><i class="bi bi-phone me-1 text-muted"></i>{{ $c->name }}</td>
                            <td class="small text-muted">{{ $c->created_at->format('d M Y') }}</td>
                            <td class="small text-muted">{{ $c->last_used_at ? $c->last_used_at->diffForHumans() : 'Never' }}</td>
                            <td class="pe-3 text-end">
                                <form method="POST" action="{{ route('fingerprint.destroy', $c) }}" onsubmit="return confirm('Remove fingerprint sign-in for {{ $c->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4 small">No devices yet. Enable fingerprint sign-in on your phone or computer.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted">Lost a phone? Remove it here — it can no longer sign in, even with the fingerprint.</div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(async function () {
    const text = document.getElementById('fpSupportText'), btn = document.getElementById('fpEnrollBtn'), msg = document.getElementById('fpEnrollMsg');
    if (!(await Fingerprint.supported())) { text.textContent = 'This device or browser does not support fingerprint sign-in. Try your phone (Chrome or Safari) or a computer with Windows Hello / Touch ID.'; return; }
    text.textContent = Fingerprint.enrolledHere() ? 'This device is already set up. You can add it again if you removed it.' : 'Turn it on, then use "Sign in with fingerprint" on the login page.';
    btn.classList.remove('d-none');
    btn.onclick = async () => {
        btn.disabled = true; msg.className = 'small mt-2 text-muted'; msg.textContent = 'Confirm with your fingerprint…';
        try { await Fingerprint.enroll(); msg.className = 'small mt-2 text-success'; msg.textContent = 'Done! Reloading…'; setTimeout(() => location.reload(), 900); }
        catch (e) { msg.className = 'small mt-2 text-danger'; msg.textContent = e.message; btn.disabled = false; }
    };
})();
</script>
@endpush
