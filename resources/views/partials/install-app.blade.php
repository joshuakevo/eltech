{{-- "Install app" pop-up: QR code to open the system on a phone + install on this device. Open with data-bs-target="#installAppModal". --}}
@php $appUrl = url('/'); $appName = \App\Models\SystemSetting::get('org_name', 'ElTech Finance'); @endphp
<div class="modal fade" id="installAppModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;overflow:hidden">
            <div class="modal-header text-white" style="background:#0f2444">
                <div class="d-flex align-items-center gap-2">
                    <img src="{{ route('app.icon', ['size' => 192]) }}" alt="" style="width:36px;height:36px;border-radius:8px">
                    <div>
                        <div class="fw-semibold">Install {{ $appName }}</div>
                        <div style="font-size:.75rem;opacity:.7">Open it like an app from your phone or computer</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 align-items-center">
                    <div class="col-sm-5 text-center">
                        <div id="installQr" class="d-inline-block p-2 bg-white border rounded"></div>
                        <div class="text-muted mt-1" style="font-size:.72rem">Scan with your phone camera</div>
                    </div>
                    <div class="col-sm-7" style="font-size:.82rem">
                        <button type="button" id="installNowBtn" class="btn btn-primary w-100 mb-2 d-none"><i class="bi bi-download me-1"></i>Install on this device</button>
                        <div id="installDone" class="alert alert-success py-2 small d-none mb-2"><i class="bi bi-check-circle me-1"></i>Installed — open it from your home screen or apps.</div>
                        <div class="fw-semibold mb-1">After scanning:</div>
                        <div class="mb-1"><i class="bi bi-android2 text-success me-1"></i><b>Android (Chrome)</b>: menu <i class="bi bi-three-dots-vertical"></i> → <i>Install app</i> / <i>Add to Home screen</i></div>
                        <div class="mb-1"><i class="bi bi-apple me-1"></i><b>iPhone (Safari)</b>: Share <i class="bi bi-box-arrow-up"></i> → <i>Add to Home Screen</i></div>
                        <div><i class="bi bi-pc-display text-primary me-1"></i><b>Computer (Chrome / Edge)</b>: the install icon <i class="bi bi-window-plus"></i> at the right of the address bar</div>
                    </div>
                </div>
                <div class="input-group input-group-sm mt-3">
                    <input type="text" class="form-control" value="{{ $appUrl }}" readonly id="installUrl">
                    <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('installUrl').value); this.innerHTML='<i class=\'bi bi-check2\'></i> Copied'"><i class="bi bi-clipboard me-1"></i>Copy link</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    const modal = document.getElementById('installAppModal');
    const btn = document.getElementById('installNowBtn');
    let drawn = false;
    const showButton = () => { if (window.__installPrompt) btn.classList.remove('d-none'); };
    document.addEventListener('install-available', showButton);
    modal.addEventListener('show.bs.modal', () => {
        showButton();
        if (!drawn && window.QRCode) {
            new QRCode(document.getElementById('installQr'), { text: @json($appUrl), width: 150, height: 150, colorDark: '#0f2444', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
            drawn = true;
        }
    });
    btn.addEventListener('click', async () => {
        const p = window.__installPrompt; if (!p) return;
        p.prompt();
        const choice = await p.userChoice;
        window.__installPrompt = null; btn.classList.add('d-none');
        if (choice.outcome === 'accepted') document.getElementById('installDone').classList.remove('d-none');
    });
    window.addEventListener('appinstalled', () => { btn.classList.add('d-none'); document.getElementById('installDone').classList.remove('d-none'); });
})();
</script>
