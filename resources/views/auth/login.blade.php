<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — {{ \App\Models\SystemSetting::get('org_name', 'Eltech Systems') }}</title>
    @include('partials.pwa-head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --primary:#0f2444; --accent:#2563eb; }
        html, body { height: 100%; margin: 0; padding: 0; }
        body { min-height: 100vh; display: flex; font-family: 'Segoe UI', system-ui, sans-serif; background: #fff; }
        .login-side { flex: 1 1 50%; background: linear-gradient(135deg, var(--primary) 0%, #1a3a6e 100%); display: flex; align-items: center; justify-content: center; }
        .brand-side { order: -1; flex: 1 1 50%; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 2rem; }
        .brand-name { font-size: 2rem; font-weight: 700; letter-spacing: .02em; color: #2b2f36; margin-top: 1.1rem; line-height: 1; }
        .brand-tag { font-size: .9rem; font-weight: 700; letter-spacing: .14em; color: #1565c0; margin-top: .6rem; }
        .brand-mark { width: 130px; height: auto; }
        .install-link { margin-top: 2rem; background: none; border: 1px solid #dbe3ef; border-radius: 999px; padding: .45rem 1rem; color: #0f2444; font-size: .8rem; font-weight: 600; }
        .install-link:hover { background: #f1f5fb; }
        .brand-foot { color: #9ca3af; font-size: .72rem; margin-top: 2.5rem; }
        @media (max-width: 991.98px) { body { flex-direction: column; } .brand-side { flex: 0 0 auto; padding: 1.5rem; order: -1; } .brand-name { font-size: 1.4rem; } .brand-mark { width: 80px; } .install-link { margin-top: 1rem; } .login-side { flex: 1 0 auto; padding: 2rem 0; } .brand-foot { display: none; } }
        .login-wrap { width: 100%; max-width: 420px; padding: 1rem; }
        .login-card { border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.35); overflow: hidden; }
        .login-header { background: var(--primary); padding: 1.25rem 2rem; text-align: center; }
        .login-logo { width: 46px; height: 46px; background: var(--accent); border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.35rem; color: #fff; margin-bottom: .5rem; }
        .login-body { background: #fff; padding: 1.5rem 2rem; }
        .login-title { color: #fff; font-weight: 700; margin-bottom: 0; font-size: 1.1rem; }
        .login-subtitle { color: rgba(255,255,255,.55); font-size: .78rem; }
        .section-title { color: #6b7280; font-size: .8rem; font-weight: 600; text-align: center; margin-bottom: .85rem; }
        .form-label { font-size: .78rem; font-weight: 600; }
        .form-control { border-radius: 8px; border-color: #d1d5db; font-size: .875rem; }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
        .input-group-text { background: #f9fafb; border-color: #d1d5db; }
        .form-check-label { font-size: .8rem; }
        .btn-login { background: var(--accent); border: none; border-radius: 8px; padding: .65rem 1rem; font-weight: 600; font-size: .875rem; width: 100%; transition: background .15s; }
        .btn-login:hover { background: #1d4ed8; }
        .hint-text { color: #9ca3af; font-size: .73rem; text-align: center; }
        /* sign-in loader */
        .signin-loader { position: fixed; inset: 0; z-index: 2000; display: flex; flex-direction: column; align-items: center; justify-content: center;
            background: rgba(15,36,68,.82); backdrop-filter: blur(4px); opacity: 0; visibility: hidden; transition: opacity .25s, visibility .25s; }
        .signin-loader.show { opacity: 1; visibility: visible; }
        .loader-ring { position: relative; width: 108px; height: 108px; }
        .loader-ring::before, .loader-ring::after { content: ''; position: absolute; inset: 0; border-radius: 50%; border: 3px solid transparent; }
        .loader-ring::before { border-top-color: #60a5fa; border-right-color: #60a5fa; animation: spin 1s linear infinite; }
        .loader-ring::after { inset: 10px; border-bottom-color: rgba(255,255,255,.55); border-left-color: rgba(255,255,255,.55); animation: spin 1.6s linear infinite reverse; }
        .loader-ring .core { position: absolute; inset: 22px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; animation: pulse 1.6s ease-in-out infinite; }
        .loader-ring .core img { width: 70%; height: auto; }
        .loader-text { color: #fff; font-weight: 600; margin-top: 1.4rem; letter-spacing: .02em; }
        .loader-sub { color: rgba(255,255,255,.6); font-size: .8rem; margin-top: .25rem; min-height: 1.2em; }
        .loader-dots span { animation: blink 1.4s infinite both; }
        .loader-dots span:nth-child(2) { animation-delay: .2s; } .loader-dots span:nth-child(3) { animation-delay: .4s; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(.93); } }
        @keyframes blink { 0%,80%,100% { opacity: 0; } 40% { opacity: 1; } }
    </style>
</head>
<body>
<div class="signin-loader" id="signinLoader" role="status" aria-live="polite">
    <div class="loader-ring"><div class="core"><img src="{{ asset('images/eltech-mark.png') }}" alt=""></div></div>
    <div class="loader-text">Signing you in<span class="loader-dots"><span>.</span><span>.</span><span>.</span></span></div>
    <div class="loader-sub" id="loaderSub">Checking your details</div>
</div>
<div class="login-side">
<div class="login-wrap">
<div class="login-card">
    <div class="login-header">
        <div class="login-logo"><i class="bi bi-bank"></i></div>
        <div class="login-title">{{ \App\Models\SystemSetting::get('org_name', 'Eltech Systems') }}</div>
        <div class="login-subtitle">Financial Management System</div>
    </div>
    <div class="login-body">
        <p class="section-title">Sign in to your account</p>

        @if($errors->any())
            <div class="alert alert-danger py-2 small mb-3">
                <i class="bi bi-exclamation-circle me-1"></i>{{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-2">
                <label class="form-label mb-1">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope text-muted"></i></span>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}"
                           placeholder="you@example.com" required autofocus>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label mb-1">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>
            <div class="d-flex align-items-center mb-3 mt-2">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
            </div>
            <button type="submit" class="btn btn-login text-white">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </form>

        <div id="fpLoginWrap" class="d-none">
            <div class="d-flex align-items-center my-3"><hr class="flex-grow-1 my-0"><span class="px-2 text-muted" style="font-size:.72rem">OR</span><hr class="flex-grow-1 my-0"></div>
            <button type="button" id="fpLoginBtn" class="btn btn-outline-primary w-100 fw-semibold" style="border-radius:8px;padding:.6rem 1rem">
                <i class="bi bi-fingerprint me-2 fs-5 align-middle"></i>Sign in with fingerprint
            </button>
            <div id="fpLoginMsg" class="small text-danger text-center mt-2"></div>
        </div>

    </div>
</div>
</div>
</div>
<div class="brand-side">
    <img src="{{ asset('images/eltech-mark.png') }}" alt="ElTech Systems" class="brand-mark">
    <div class="brand-name">ELTECH SYSTEMS</div>
    <div class="brand-tag">ENGINEERED FOR IMPACT</div>
    <button type="button" class="install-link" data-bs-toggle="modal" data-bs-target="#installAppModal">
        <i class="bi bi-qr-code me-1"></i>Install the app · scan QR code
    </button>
    <div class="brand-foot">Financial management for SACCOs &amp; microfinance</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
@include('partials.install-app')
@include('partials.webauthn-js')
<script>
(async function () {
    // Fingerprint sign-in: shown on devices where it has been enabled
    if (!Fingerprint.enrolledHere() || !(await Fingerprint.supported())) return;
    const wrap = document.getElementById('fpLoginWrap'), btn = document.getElementById('fpLoginBtn'), msg = document.getElementById('fpLoginMsg');
    wrap.classList.remove('d-none');
    btn.onclick = async () => {
        msg.textContent = ''; btn.disabled = true;
        try {
            const res = await Fingerprint.signIn();
            document.getElementById('loaderSub').textContent = 'Fingerprint confirmed';
            document.getElementById('signinLoader').classList.add('show');
            window.location.href = res.redirect;
        } catch (e) { msg.textContent = e.message; btn.disabled = false; }
    };
    if (window.matchMedia('(display-mode: standalone)').matches || navigator.standalone) btn.click();   // installed app: ask straight away
})();
</script>
<script>
(function () {
    var loader = document.getElementById('signinLoader'), sub = document.getElementById('loaderSub'), timers = [];
    document.addEventListener('submit', function (e) {
        var form = e.target;
        form.querySelectorAll('button:not([type="button"]):not([type="reset"]), input[type="submit"]').forEach(function (btn) {
            btn.disabled = true;
            if (btn.tagName === 'BUTTON') {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Signing in…';
            }
        });
        loader.classList.add('show');
        timers.push(setTimeout(function () { sub.textContent = 'Loading your dashboard'; }, 2500));
        timers.push(setTimeout(function () { sub.textContent = 'Almost there — the connection is a little slow'; }, 7000));
    });
    // Coming back with the browser Back button: clear the loader and re-enable the form
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        loader.classList.remove('show'); timers.forEach(clearTimeout); timers = [];
        document.querySelectorAll('form button[disabled]').forEach(function (b) { b.disabled = false; b.innerHTML = '<i class="bi bi-box-arrow-in-right me-2"></i>Sign In'; });
    });
})();
</script>
</body>
</html>
