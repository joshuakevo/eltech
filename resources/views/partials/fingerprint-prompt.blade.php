{{-- After sign-in on a phone / installed app: offer to turn on fingerprint sign-in for this device. --}}
@auth
@include('partials.webauthn-js')
<div id="fpPrompt" class="d-none" style="position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:1080;width:calc(100% - 24px);max-width:420px">
    <div class="bg-white shadow-lg border rounded-4 p-3 d-flex gap-3 align-items-start">
        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;background:#e8efff"><i class="bi bi-fingerprint fs-4 text-primary"></i></div>
        <div class="flex-grow-1">
            <div class="fw-semibold" style="color:#0f2444">Sign in with your fingerprint?</div>
            <div class="text-muted" style="font-size:.8rem">Next time, skip the password on this device — just touch the sensor or use face unlock.</div>
            <div id="fpPromptMsg" class="small mt-1"></div>
            <div class="d-flex gap-2 mt-2">
                <button type="button" class="btn btn-primary btn-sm" id="fpEnableBtn"><i class="bi bi-fingerprint me-1"></i>Enable</button>
                <button type="button" class="btn btn-light btn-sm" id="fpLaterBtn">Not now</button>
            </div>
        </div>
    </div>
</div>
<script>
(async function () {
    const key = 'fp_dismissed_{{ auth()->id() }}';
    let dismissed = false; try { dismissed = !!localStorage.getItem(key); } catch (e) {}
    const mobileOrApp = /android|iphone|ipad/i.test(navigator.userAgent) || window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
    if (dismissed || Fingerprint.enrolledHere() || !mobileOrApp || !(await Fingerprint.supported())) return;

    const box = document.getElementById('fpPrompt'), msg = document.getElementById('fpPromptMsg');
    box.classList.remove('d-none');
    document.getElementById('fpLaterBtn').onclick = () => { try { localStorage.setItem(key, '1'); } catch (e) {} box.classList.add('d-none'); };
    document.getElementById('fpEnableBtn').onclick = async (ev) => {
        ev.target.disabled = true; msg.className = 'small mt-1 text-muted'; msg.textContent = 'Confirm with your fingerprint…';
        try {
            await Fingerprint.enroll();
            msg.className = 'small mt-1 text-success'; msg.innerHTML = '<i class="bi bi-check-circle"></i> Done — use your fingerprint on the login page next time.';
            setTimeout(() => box.classList.add('d-none'), 2500);
        } catch (e) { msg.className = 'small mt-1 text-danger'; msg.textContent = e.message; ev.target.disabled = false; }
    };
})();
</script>
@endauth
