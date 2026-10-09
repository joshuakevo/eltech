<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php $appName = \App\Models\SystemSetting::get('org_name', 'ElTech Finance'); @endphp
    <title>Install {{ $appName }}</title>
    @include('partials.pwa-head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        html, body { height: 100%; }
        body { margin: 0; background: linear-gradient(160deg, #0f2444 0%, #1a3a6e 100%); font-family: 'Segoe UI', system-ui, sans-serif; display: flex; align-items: center; justify-content: center; padding: 1.25rem; }
        .install-card { background: #fff; border-radius: 22px; max-width: 380px; width: 100%; padding: 2rem 1.5rem 1.5rem; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,.35); }
        .app-icon { width: 96px; height: 96px; border-radius: 22px; box-shadow: 0 8px 24px rgba(15,36,68,.35); }
        .app-name { font-size: 1.3rem; font-weight: 700; color: #0f2444; margin-top: 1rem; }
        .app-sub { color: #6b7280; font-size: .85rem; }
        .btn-install { background: #2563eb; color: #fff; border: 0; border-radius: 14px; font-size: 1.1rem; font-weight: 600; padding: .9rem 1rem; width: 100%; margin-top: 1.5rem; }
        .btn-install:disabled { background: #93b4f5; }
        .step { display: flex; gap: .75rem; align-items: center; text-align: left; background: #f4f7fb; border-radius: 12px; padding: .7rem .9rem; margin-top: .6rem; font-size: .9rem; }
        .step .n { width: 28px; height: 28px; border-radius: 50%; background: #0f2444; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; }
        .later { display: inline-block; margin-top: 1.1rem; color: #6b7280; font-size: .82rem; text-decoration: none; }
        .powered { color: rgba(255,255,255,.55); font-size: .72rem; text-align: center; margin-top: 1rem; }
    </style>
</head>
<body>
<div style="width:100%;max-width:380px">
<div class="install-card">
    <img src="{{ route('app.icon', ['size' => 192]) }}" alt="" class="app-icon">
    <div class="app-name">{{ $appName }}</div>
    <div class="app-sub">Financial Management System</div>

    {{-- Android / Chrome / Edge: one tap --}}
    <div id="modePrompt">
        <button id="installBtn" class="btn-install" disabled>
            <span class="spinner-border spinner-border-sm me-2" id="installWait"></span><i class="bi bi-download me-2 d-none" id="installIco"></i>Install app
        </button>
        <div id="manualHint" class="d-none">
            <div class="step"><span class="n">1</span><span>Open your browser menu <i class="bi bi-three-dots-vertical"></i></span></div>
            <div class="step"><span class="n">2</span><span>Tap <b>Install app</b> or <b>Add to Home screen</b></span></div>
        </div>
    </div>

    {{-- iPhone / iPad: Safari share sheet --}}
    <div id="modeIos" class="d-none">
        <div class="step mt-4"><span class="n">1</span><span>Tap <b>Share</b> <i class="bi bi-box-arrow-up text-primary"></i> at the bottom of Safari</span></div>
        <div class="step"><span class="n">2</span><span>Choose <b>Add to Home Screen</b> <i class="bi bi-plus-square"></i></span></div>
        <div class="step"><span class="n">3</span><span>Tap <b>Add</b> — the icon appears on your home screen</span></div>
        <div id="iosNotSafari" class="alert alert-warning small mt-3 mb-0 d-none">Open this page in <b>Safari</b> to install it on iPhone.</div>
    </div>

    {{-- Installed --}}
    <div id="modeDone" class="d-none">
        <div class="alert alert-success mt-4 mb-0"><i class="bi bi-check-circle-fill me-1"></i>Installed. Open <b>{{ $appName }}</b> from your home screen or apps.</div>
    </div>

    <a href="{{ route('login') }}" class="later">Continue in the browser instead</a>
</div>
<div class="powered">Powered by ElTech Systems</div>
</div>

<script>
(function () {
    const ua = navigator.userAgent;
    const isIos = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
    const show = id => ['modePrompt', 'modeIos', 'modeDone'].forEach(m => document.getElementById(m).classList.toggle('d-none', m !== id));

    if (standalone) { window.location.replace('/'); return; }   // opened from the installed app
    if (isIos) {
        show('modeIos');
        if (/crios|fxios|edgios/i.test(ua)) document.getElementById('iosNotSafari').classList.remove('d-none');
        return;
    }

    const btn = document.getElementById('installBtn');
    const ready = () => {
        btn.disabled = false;
        document.getElementById('installWait').classList.add('d-none');
        document.getElementById('installIco').classList.remove('d-none');
    };
    if (window.__installPrompt) ready();
    document.addEventListener('install-available', ready);
    // Browsers without a one-tap install (or already installed): show the menu steps
    setTimeout(() => { if (!window.__installPrompt) { btn.classList.add('d-none'); document.getElementById('manualHint').classList.remove('d-none'); } }, 3500);

    btn.addEventListener('click', async () => {
        const p = window.__installPrompt; if (!p) return;
        p.prompt();
        const choice = await p.userChoice;
        window.__installPrompt = null;
        if (choice.outcome === 'accepted') show('modeDone');
    });
    window.addEventListener('appinstalled', () => show('modeDone'));
})();
</script>
</body>
</html>
