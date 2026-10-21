{{-- Floating "Ask" assistant (shown when enabled in Settings → AI Assistant and the user may use it) --}}
<style>
    #aiFab{position:fixed;right:22px;bottom:22px;z-index:1046;width:56px;height:56px;border-radius:50%;border:0;color:#fff;
        background:linear-gradient(135deg,#7c3aed,#2563eb);box-shadow:0 10px 24px rgba(37,99,235,.35);font-size:1.45rem;display:flex;align-items:center;justify-content:center}
    #aiFab:hover{transform:translateY(-1px)}
    #aiPanel{position:fixed;right:22px;bottom:90px;z-index:1047;width:390px;max-width:calc(100vw - 24px);height:560px;max-height:calc(100vh - 120px);
        background:#fff;border-radius:16px;box-shadow:0 20px 50px rgba(15,36,68,.28);display:none;flex-direction:column;overflow:hidden}
    #aiPanel.open{display:flex}
    #aiPanel .ai-head{background:linear-gradient(135deg,#0f2444,#1d4ed8);color:#fff;padding:.75rem 1rem;display:flex;align-items:center;gap:.5rem}
    #aiPanel .ai-body{flex:1;overflow-y:auto;padding:.9rem;background:#f8fafc;font-size:.86rem}
    #aiPanel .ai-msg{max-width:88%;padding:.55rem .75rem;border-radius:12px;margin-bottom:.6rem;line-height:1.45;word-wrap:break-word}
    #aiPanel .ai-user{background:#2563eb;color:#fff;margin-left:auto;border-bottom-right-radius:4px}
    #aiPanel .ai-bot{background:#fff;border:1px solid #e5e7eb;border-bottom-left-radius:4px}
    #aiPanel .ai-bot a{color:#1d4ed8}
    #aiPanel .ai-err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
    #aiPanel .ai-chips{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.25rem}
    #aiPanel .ai-chip{border:1px solid #c7d2fe;background:#eef2ff;color:#3730a3;border-radius:999px;padding:.25rem .65rem;font-size:.76rem;cursor:pointer}
    #aiPanel .ai-foot{border-top:1px solid #e5e7eb;padding:.55rem;display:flex;gap:.4rem;background:#fff}
    #aiPanel .ai-dots span{display:inline-block;width:6px;height:6px;margin:0 2px;border-radius:50%;background:#94a3b8;animation:aiDot 1s infinite}
    #aiPanel .ai-dots span:nth-child(2){animation-delay:.15s}#aiPanel .ai-dots span:nth-child(3){animation-delay:.3s}
    @keyframes aiDot{0%,80%,100%{opacity:.3;transform:translateY(0)}40%{opacity:1;transform:translateY(-3px)}}
    @media(max-width:768px){
        #aiFab{bottom:calc(84px + env(safe-area-inset-bottom));right:14px;width:50px;height:50px;font-size:1.25rem}
        #aiPanel{right:8px;left:8px;width:auto;bottom:calc(140px + env(safe-area-inset-bottom));height:calc(100vh - 210px)}
    }
    @media print{#aiFab,#aiPanel{display:none!important}}
</style>

<button type="button" id="aiFab" title="Ask the system"><i class="bi bi-stars"></i></button>
<div id="aiPanel" role="dialog" aria-label="Ask the system">
    <div class="ai-head">
        <i class="bi bi-stars"></i>
        <div class="flex-fill"><div class="fw-semibold" style="font-size:.92rem">Ask the system</div><div style="font-size:.7rem;opacity:.75">Savings, loans, deposits & members — read only</div></div>
        <button type="button" class="btn btn-sm btn-link text-white p-0 me-2" id="aiNew" title="New chat"><i class="bi bi-arrow-counterclockwise"></i></button>
        <button type="button" class="btn btn-sm btn-link text-white p-0" id="aiClose" title="Close"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="ai-body" id="aiBody">
        <div class="ai-msg ai-bot">Hi {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}! Ask me about savings, loans, deposits or a member. For example:
            <div class="ai-chips">
                @can('view savings')<span class="ai-chip">How are savings doing this week?</span><span class="ai-chip">Who are my best savers?</span>@endcan
                @can('view loans')<span class="ai-chip">Which loans should I watch?</span><span class="ai-chip">What is our PAR?</span><span class="ai-chip">How much did we recover this month?</span>@endcan
                @can('view fixed-deposits')<span class="ai-chip">Which fixed deposits mature this month?</span>@endcan
            </div>
        </div>
    </div>
    <form class="ai-foot" id="aiForm" data-no-block="1">
        <input type="text" id="aiInput" class="form-control form-control-sm" placeholder="Ask a question…" maxlength="1000" autocomplete="off">
        <button class="btn btn-primary btn-sm px-3" id="aiSend"><i class="bi bi-send"></i></button>
    </form>
</div>

<script>
(function () {
    const fab = document.getElementById('aiFab'), panel = document.getElementById('aiPanel'), body = document.getElementById('aiBody');
    const form = document.getElementById('aiForm'), input = document.getElementById('aiInput'), send = document.getElementById('aiSend');
    const token = document.querySelector('meta[name=csrf-token]').content;
    let busy = false;

    fab.addEventListener('click', () => { panel.classList.toggle('open'); if (panel.classList.contains('open')) input.focus(); });
    document.getElementById('aiClose').addEventListener('click', () => panel.classList.remove('open'));
    document.getElementById('aiNew').addEventListener('click', () => {
        fetch('{{ route('ai.reset') }}', {method: 'POST', headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json'}});
        body.querySelectorAll('.ai-msg:not(:first-child)').forEach(m => m.remove());
    });
    body.addEventListener('click', e => { if (e.target.classList.contains('ai-chip')) ask(e.target.textContent); });

    function bubble(cls, html) {
        const d = document.createElement('div'); d.className = 'ai-msg ' + cls; d.innerHTML = html;
        body.appendChild(d); body.scrollTop = body.scrollHeight; return d;
    }
    function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    async function ask(q) {
        q = (q || '').trim();
        if (!q || busy) return;
        busy = true; send.disabled = true; input.value = '';
        bubble('ai-user', esc(q));
        const wait = bubble('ai-bot', '<span class="ai-dots"><span></span><span></span><span></span></span> <span class="text-muted small">checking the figures…</span>');
        try {
            const r = await fetch('{{ route('ai.chat') }}', {method: 'POST', headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json'}, body: JSON.stringify({question: q})});
            const j = await r.json().catch(() => ({}));
            wait.remove();
            if (r.ok && j.html) bubble('ai-bot', j.html);
            else bubble('ai-bot ai-err', esc(j.error || j.message || 'Something went wrong — please try again.'));
        } catch (e) {
            wait.remove(); bubble('ai-bot ai-err', 'Could not reach the server — check the connection.');
        }
        busy = false; send.disabled = false; input.focus();
    }
    form.addEventListener('submit', e => { e.preventDefault(); ask(input.value); });
})();
</script>
