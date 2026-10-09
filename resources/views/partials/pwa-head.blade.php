{{-- Installable app (PWA): manifest, icons, service worker. Included in every page <head>. --}}
@php $pwaV = substr(md5((string) \App\Models\SystemSetting::get('org_logo')), 0, 8); @endphp
<link rel="manifest" href="{{ route('app.manifest') }}">
<meta name="theme-color" content="#0f2444">
<link rel="icon" type="image/png" sizes="192x192" href="{{ route('app.icon', ['size' => 192]) }}?v={{ $pwaV }}">
<link rel="apple-touch-icon" href="{{ route('app.icon', ['size' => 180]) }}?v={{ $pwaV }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
    window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); window.__installPrompt = e; document.dispatchEvent(new Event('install-available')); });
</script>
