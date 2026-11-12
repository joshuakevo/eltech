{{-- Investor portal demo (sample data, no login) — same look as layouts/client-portal --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    @php $org = \App\Models\SystemSetting::get('org_name', 'ElTech Finance'); @endphp
    <title>@yield('title', 'Investor Portal Demo') — {{ $org }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --primary:#0f2444; --accent:#2563eb; --sidebar-w:230px; --topbar-h:52px; --banner-h:34px; --body-bg:#f0f2f5; }
        *, *::before, *::after { box-sizing: border-box; }
        body { background: var(--body-bg); font-family: 'Segoe UI', system-ui, sans-serif; font-size: .875rem; margin: 0; }
        .demo-banner { position: fixed; top: 0; left: 0; right: 0; height: var(--banner-h); z-index: 210;
            background: #fef3c7; color: #78350f; border-bottom: 1px solid #fcd34d;
            display: flex; align-items: center; justify-content: center; gap: .5rem; font-size: .78rem; padding: 0 .75rem; text-align: center; }
        .cp-top { position: fixed; top: var(--banner-h); left: 0; right: 0; height: var(--topbar-h); z-index: 200;
            background: var(--primary); color: #fff; display: flex; align-items: center; gap: 1rem; padding: 0 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.25); }
        .cp-top .brand { font-weight: 700; font-size: .95rem; line-height: 1.1; }
        .cp-top .sub { font-size: .68rem; opacity: .6; }
        .cp-top .avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--accent);
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .8rem; }
        .cp-sidebar { position: fixed; top: calc(var(--banner-h) + var(--topbar-h)); left: 0; bottom: 0; width: var(--sidebar-w);
            background: #fff; border-right: 1px solid #e5e7eb; overflow-y: auto; z-index: 100; display: flex; flex-direction: column; }
        .cp-sidebar .user-panel { padding: 1rem 1rem .75rem; border-bottom: 1px solid #f3f4f6; }
        .cp-sidebar .uname { font-weight: 700; font-size: .85rem; color: var(--primary); }
        .cp-sidebar .urole { font-size: .7rem; color: #9ca3af; }
        .cp-sidebar .nav-section { padding: .6rem 1rem .2rem; font-size: .63rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #9ca3af; }
        .cp-sidebar a.cp-nav-item { display: flex; align-items: center; gap: .65rem; padding: .5rem 1rem; color: #374151; text-decoration: none; font-size: .82rem; }
        .cp-sidebar a.cp-nav-item i { font-size: 1rem; width: 18px; text-align: center; }
        .cp-sidebar a.cp-nav-item:hover { background: #f9fafb; }
        .cp-sidebar a.cp-nav-item.active { background: #eff6ff; color: var(--accent); font-weight: 600; border-right: 3px solid var(--accent); }
        .cp-body { margin-left: var(--sidebar-w); margin-top: calc(var(--banner-h) + var(--topbar-h)); }
        .cp-main { padding: 1.5rem; max-width: 1050px; }
        .card { border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .card-header { font-weight: 600; font-size: .875rem; background: #fff; border-bottom: 1px solid #f3f4f6; }
        .table th { font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; }
        .stat-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
        .stat-card .sc-label { font-size: .7rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
        .stat-card .sc-value { font-size: 1.45rem; font-weight: 700; line-height: 1.2; margin: .2rem 0; }
        .stat-card .sc-sub { font-size: .72rem; color: #9ca3af; }
        .stat-card .sc-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .badge-status-active { background:#d1fae5; color:#065f46; }
        .badge-status-matured, .badge-status-closed { background:#fee2e2; color:#991b1b; }
        @media(max-width:767px){
            :root { --banner-h: 44px; }
            .cp-sidebar { display: none; }
            .cp-body { margin-left: 0; }
            .cp-main { padding: 1rem; }
            body { padding-bottom: 76px; }
            .dnav { display: flex !important; }
        }
        .dnav { display: none; position: fixed; left: 0; right: 0; bottom: 0; z-index: 1045; justify-content: space-around;
            padding: 6px 4px calc(6px + env(safe-area-inset-bottom)); background: rgba(255,255,255,.97); border-top: 1px solid #e5e7eb; }
        .dnav a { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 3px; color: #6b7280; font-size: .66rem; font-weight: 600; text-decoration: none; }
        .dnav a i { width: 48px; height: 30px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .dnav a.active { color: #0f2444; }
        .dnav a.active i { background: #e0e9ff; color: #1d4ed8; }
    </style>
    @stack('styles')
</head>
<body>
@php
    $nav = [
        ['Dashboard', 'bi-speedometer2', 'bi-house-door-fill', route('demo.investor'), request()->routeIs('demo.investor')],
        ['My Statements', 'bi-wallet2', 'bi-wallet2', route('demo.investor.statements'), request()->routeIs('demo.investor.statements', 'demo.investor.investment', 'demo.investor.savings')],
    ];
@endphp

<div class="demo-banner">
    <i class="bi bi-eye"></i>
    <span><b>Demo:</b> sample data for illustration only. Investors see their own accounts after signing in.</span>
</div>

<header class="cp-top">
    <i class="bi bi-bank2 fs-5 opacity-75"></i>
    <div>
        <div class="brand">{{ $org }}</div>
        <div class="sub">Member Portal</div>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
        <div class="avatar d-none d-sm-flex">{{ strtoupper(substr($client->name, 0, 2)) }}</div>
        <div class="d-none d-sm-block text-end">
            <div class="small" style="line-height:1.2">{{ $client->name }}</div>
            <div style="font-size:.68rem;opacity:.6">{{ $client->client_number }}</div>
        </div>
        <a href="{{ route('login') }}" class="btn btn-sm btn-outline-light px-2"><i class="bi bi-box-arrow-in-right me-1"></i><span class="d-none d-sm-inline">Member sign in</span></a>
    </div>
</header>

<nav class="cp-sidebar">
    <div class="user-panel">
        <div class="uname">{{ $client->name }}</div>
        <div class="urole">{{ $client->client_number }}</div>
    </div>
    <div class="nav-section">Overview</div>
    <a href="{{ $nav[0][3] }}" class="cp-nav-item {{ $nav[0][4] ? 'active' : '' }}"><i class="bi {{ $nav[0][1] }}"></i> {{ $nav[0][0] }}</a>
    <div class="nav-section">My Finance</div>
    <a href="{{ $nav[1][3] }}" class="cp-nav-item {{ $nav[1][4] ? 'active' : '' }}"><i class="bi {{ $nav[1][1] }}"></i> {{ $nav[1][0] }}</a>
    <div class="mt-auto p-3 border-top small text-muted">
        Real members sign in with their own login, or install the app and use their fingerprint.
    </div>
</nav>

<div class="cp-body"><main class="cp-main">@yield('content')</main></div>

<nav class="dnav">
    @foreach($nav as [$label, , $icon, $url, $active])
        <a href="{{ $url }}" class="{{ $active ? 'active' : '' }}"><i class="bi {{ $icon }}"></i>{{ $label === 'Dashboard' ? 'Home' : 'Statements' }}</a>
    @endforeach
    <a href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right"></i>Sign in</a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
