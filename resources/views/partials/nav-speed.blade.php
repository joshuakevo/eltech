{{--
    Faster navigation + loading bar (all layouts).
    - Preload: navigation links (sidebar, bottom tabs, page tabs) start loading on touch / short hover, before the click
      lands. Speculation Rules where supported (Chrome/Android), <link rel=prefetch> on pointerdown elsewhere.
      Never downloads (pdf/excel/export), Run Loans or logout.
    - Loading bar: a thin bar at the top when a page takes longer than a moment; the tapped tab lights up at once.
--}}
<style>
    #navProgress{position:fixed;top:0;left:0;height:3px;width:0;z-index:2000;opacity:0;pointer-events:none;
        background:linear-gradient(90deg,#2563eb,#60a5fa,#2563eb);box-shadow:0 0 8px rgba(37,99,235,.6);transition:width .25s ease,opacity .2s ease}
    #navProgress.run{opacity:1}
    .bnav .bn-tab.pressing .bn-ico{background:#e0e9ff;color:#1d4ed8}
    .nav-link-item.pressing{background:rgba(255,255,255,.12)}
</style>
<div id="navProgress"></div>
<script type="speculationrules">
{"prefetch":[{"where":{"and":[
    {"selector_matches":".nav-link-item[href], .bn-tab[href], .nav-tabs a[href], .nav-pills a[href], a[data-prefetch]"},
    {"href_matches":"/*"},
    {"not":{"href_matches":"*pdf*"}},{"not":{"href_matches":"*excel*"}},{"not":{"href_matches":"*format=*"}},
    {"not":{"href_matches":"*export*"}},{"not":{"href_matches":"*download*"}},{"not":{"href_matches":"/loans/run*"}},
    {"not":{"href_matches":"*logout*"}}
]},"eagerness":"moderate"}]}
</script>
<script>
(function () {
    var bar = document.getElementById('navProgress'), timer = null, trickle = null, width = 0;
    function start() {
        if (timer || trickle) return;
        // only show when the page is actually slow
        timer = setTimeout(function () {
            bar.classList.add('run'); width = 12; bar.style.width = width + '%';
            trickle = setInterval(function () { width += (90 - width) * 0.08; bar.style.width = width + '%'; }, 200);
        }, 120);
        setTimeout(stop, 15000);   // safety: e.g. a response that was a download
    }
    function stop() {
        clearTimeout(timer); clearInterval(trickle); timer = trickle = null;
        if (bar.classList.contains('run')) { bar.style.width = '100%'; setTimeout(function () { bar.classList.remove('run'); bar.style.width = '0'; }, 250); }
        document.querySelectorAll('.pressing').forEach(function (e) { e.classList.remove('pressing'); });
    }
    window.addEventListener('pageshow', stop);          // back/forward cache: never leave the bar running

    function navigable(a, e) {
        if (!a || !a.href || a.target === '_blank' || a.hasAttribute('download') || a.dataset.noLoader !== undefined) return false;
        if (e && (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button > 0)) return false;
        var url; try { url = new URL(a.href, location.href); } catch (_) { return false; }
        if (url.origin !== location.origin) return false;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return false;   // in-page anchor
        if (a.getAttribute('href').charAt(0) === '#' || /^javascript:/i.test(a.getAttribute('href'))) return false;
        if (a.dataset.bsToggle) return false;
        return true;
    }
    function downloadish(href) { return /pdf|excel|format=|export|download/i.test(href); }

    document.addEventListener('click', function (e) {
        var a = e.target.closest('a');
        if (e.defaultPrevented || !navigable(a, e) || downloadish(a.href)) return;
        if (a.matches('.bn-tab, .nav-link-item')) a.classList.add('pressing');
        start();
    });
    document.addEventListener('submit', function (e) {
        var f = e.target;
        var by = e.submitter;
        if (e.defaultPrevented || f.target === '_blank' || downloadish(f.action) || (by && by.name === 'format') || f.querySelector('input[name=format]')) return;
        start();
    });

    // Fallback preload (browsers without Speculation Rules): fetch the page as soon as the finger touches the link
    if (!(HTMLScriptElement.supports && HTMLScriptElement.supports('speculationrules'))) {
        var done = {};
        document.addEventListener('pointerdown', function (e) {
            var a = e.target.closest('.nav-link-item[href], .bn-tab[href], .nav-tabs a[href], .nav-pills a[href], a[data-prefetch]');
            if (!navigable(a) || downloadish(a.href) || /\/loans\/run|logout/.test(a.href) || done[a.href]) return;
            done[a.href] = 1;
            var l = document.createElement('link'); l.rel = 'prefetch'; l.href = a.href; document.head.appendChild(l);
        }, {passive: true});
    }
})();
</script>
