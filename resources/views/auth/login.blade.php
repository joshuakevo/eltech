<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — {{ \App\Models\SystemSetting::get('org_name', 'Eltech Systems') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --primary:#0f2444; --accent:#2563eb; }
        html, body { height: 100%; margin: 0; padding: 0; }
        body { min-height: 100vh; display: flex; font-family: 'Segoe UI', system-ui, sans-serif; background: #fff; }
        .login-side { flex: 1 1 50%; background: linear-gradient(135deg, var(--primary) 0%, #1a3a6e 100%); display: flex; align-items: center; justify-content: center; }
        .brand-side { flex: 1 1 50%; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 2rem; }
        .brand-name { font-size: 2rem; font-weight: 700; letter-spacing: .02em; color: #2b2f36; margin-top: 1.1rem; line-height: 1; }
        .brand-tag { font-size: .9rem; font-weight: 700; letter-spacing: .14em; color: #1565c0; margin-top: .6rem; }
        .brand-foot { color: #9ca3af; font-size: .72rem; margin-top: 2.5rem; }
        @media (max-width: 991.98px) { body { flex-direction: column; } .brand-side { flex: 0 0 auto; padding: 1.5rem; order: -1; } .brand-name { font-size: 1.4rem; } .brand-side svg { width: 70px; height: auto; } .login-side { flex: 1 0 auto; padding: 2rem 0; } .brand-foot { display: none; } }
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
    </style>
</head>
<body>
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

    </div>
</div>
</div>
</div>
<div class="brand-side">
    {{-- ElTech Systems mark --}}
    <svg width="120" height="96" viewBox="0 0 120 96" xmlns="http://www.w3.org/2000/svg" aria-label="ElTech Systems">
        <path d="M86 10 C52 2 10 10 6 34 C3 52 22 62 40 63 C22 56 16 44 24 32 C34 18 62 12 86 10 Z" fill="#111"/>
        <path d="M41 64 C70 66 100 60 116 50 C100 62 70 70 40 68 Z" fill="#111"/>
        <path d="M44 22 L84 22 L80 32 L52 32 L50 38 L76 38 L73 46 L47 46 Z" fill="#1748c9"/>
        <path d="M36 56 L72 56 L68 72 L94 72 L88 86 L22 86 Z" fill="#111"/>
    </svg>
    <div class="brand-name">ELTECH SYSTEMS</div>
    <div class="brand-tag">ENGINEERED FOR IMPACT</div>
    <div class="brand-foot">Financial management for SACCOs &amp; microfinance</div>
</div>
<script>
document.addEventListener('submit', function (e) {
    var form = e.target;
    form.querySelectorAll('button:not([type="button"]):not([type="reset"]), input[type="submit"]').forEach(function (btn) {
        btn.disabled = true;
        if (btn.tagName === 'BUTTON') {
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Signing in…';
        }
    });
});
</script>
</body>
</html>
