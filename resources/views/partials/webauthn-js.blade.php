{{-- Fingerprint sign-in (passkeys) helpers: window.Fingerprint.supported() / enroll() / signIn() --}}
<script>
window.Fingerprint = (function () {
    const csrf = () => (document.querySelector('meta[name=csrf-token]') || {}).content || (document.querySelector('input[name=_token]') || {}).value;
    const toBuf = s => Uint8Array.from(atob(s.replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((s.length + 3) % 4)), c => c.charCodeAt(0)).buffer;
    const toB64u = b => btoa(String.fromCharCode(...new Uint8Array(b))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const post = async (url, body) => {
        const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() }, credentials: 'same-origin', body: JSON.stringify(body || {}) });
        const j = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error((j.errors && Object.values(j.errors)[0][0]) || j.message || 'Something went wrong.');
        return j;
    };
    const deviceName = () => {
        const ua = navigator.userAgent;
        const os = /android/i.test(ua) ? 'Android' : /iphone/i.test(ua) ? 'iPhone' : /ipad/i.test(ua) ? 'iPad' : /windows/i.test(ua) ? 'Windows PC' : /mac/i.test(ua) ? 'Mac' : 'Device';
        const br = /edg\//i.test(ua) ? 'Edge' : /samsungbrowser/i.test(ua) ? 'Samsung Internet' : /chrome|crios/i.test(ua) ? 'Chrome' : /safari/i.test(ua) ? 'Safari' : /firefox/i.test(ua) ? 'Firefox' : '';
        const app = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone ? ' (app)' : '';
        return (os + (br ? ' · ' + br : '') + app).slice(0, 100);
    };
    const friendly = e => e && e.name === 'NotAllowedError' ? 'Cancelled — the fingerprint was not confirmed.' : (e && e.name === 'InvalidStateError' ? 'This device is already set up for fingerprint sign-in.' : (e && e.message) || 'Fingerprint sign-in failed.');

    return {
        async supported() {
            try { return !!(window.PublicKeyCredential && await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()); } catch (e) { return false; }
        },
        enrolledHere() { try { return localStorage.getItem('fp_enrolled') === '1'; } catch (e) { return false; } },
        async enroll() {
            try {
                const o = await post(@json(route('fingerprint.options')));
                o.challenge = toBuf(o.challenge); o.user.id = toBuf(o.user.id);
                o.excludeCredentials = (o.excludeCredentials || []).map(c => ({ ...c, id: toBuf(c.id) }));
                const cred = await navigator.credentials.create({ publicKey: o });
                const res = await post(@json(route('fingerprint.store')), { name: deviceName(), credential: {
                    id: cred.id, rawId: toB64u(cred.rawId), type: cred.type,
                    response: { clientDataJSON: toB64u(cred.response.clientDataJSON), attestationObject: toB64u(cred.response.attestationObject) } } });
                try { localStorage.setItem('fp_enrolled', '1'); } catch (e) {}
                return res;
            } catch (e) { throw new Error(friendly(e)); }
        },
        async signIn() {
            try {
                const o = await post(@json(route('login.fingerprint.options')));
                o.challenge = toBuf(o.challenge);
                const a = await navigator.credentials.get({ publicKey: o });
                return await post(@json(route('login.fingerprint')), { credential: {
                    id: a.id, rawId: toB64u(a.rawId), type: a.type,
                    response: { clientDataJSON: toB64u(a.response.clientDataJSON), authenticatorData: toB64u(a.response.authenticatorData), signature: toB64u(a.response.signature) } } });
            } catch (e) { throw new Error(friendly(e)); }
        },
    };
})();
</script>
