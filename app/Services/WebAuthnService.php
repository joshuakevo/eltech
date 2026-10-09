<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Validation\ValidationException;

/**
 * Passkey (WebAuthn) sign-in — fingerprint / Face ID / Windows Hello — without external packages.
 *
 * Registration uses attestation "none" (the passkey standard: the device is trusted, not certified),
 * so we read the public key from authenticatorData and store it as PEM. Every ceremony checks:
 * one-time challenge (session), origin + RP ID hash (this host), user present + user verified flags,
 * and for sign-in the signature over authenticatorData || SHA-256(clientDataJSON) via OpenSSL, plus
 * the signature counter (cloned-key protection). Supported keys: ES256 (-7) and RS256 (-257).
 */
class WebAuthnService
{
    private const SESSION_KEY = 'webauthn_challenge';

    // ── Options ────────────────────────────────────────────────────────────

    public function registrationOptions(User $user): array
    {
        return [
            'challenge' => $this->newChallenge('register'),
            'rp' => ['id' => $this->rpId(), 'name' => \App\Models\SystemSetting::get('org_name', 'ElTech Finance')],
            'user' => ['id' => self::b64u(hash('sha256', 'user:' . $user->id, true)), 'name' => $user->email, 'displayName' => $user->name],
            'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
            'authenticatorSelection' => ['authenticatorAttachment' => 'platform', 'residentKey' => 'required', 'requireResidentKey' => true, 'userVerification' => 'required'],
            'attestation' => 'none',
            'timeout' => 60000,
            'excludeCredentials' => WebauthnCredential::where('user_id', $user->id)->pluck('credential_id')
                ->map(fn ($id) => ['type' => 'public-key', 'id' => $id])->values()->all(),
        ];
    }

    public function loginOptions(): array
    {
        return [
            'challenge' => $this->newChallenge('login'),
            'rpId' => $this->rpId(),
            'userVerification' => 'required',
            'timeout' => 60000,
            'allowCredentials' => [],   // discoverable passkeys: the device offers the user's key
        ];
    }

    // ── Verification ───────────────────────────────────────────────────────

    public function register(User $user, array $data, ?string $name = null): WebauthnCredential
    {
        $clientJson = self::b64uDecode($data['response']['clientDataJSON'] ?? '');
        $this->checkClientData($clientJson, 'webauthn.create', 'register');

        $att = (new CborDecoder(self::b64uDecode($data['response']['attestationObject'] ?? '')))->decode();
        $auth = $this->parseAuthData($att['authData'] ?? '', true);

        $credId = self::b64u($auth['credentialId']);
        if (WebauthnCredential::where('credential_id', $credId)->exists()) {
            $this->fail('This device is already registered.');
        }
        [$pem, $alg] = $this->coseToPem($auth['coseKey']);

        return WebauthnCredential::create([
            'user_id' => $user->id, 'credential_id' => $credId, 'public_key' => $pem, 'alg' => $alg,
            'sign_count' => $auth['signCount'], 'name' => $name ?: 'This device',
        ]);
    }

    /** Verify a sign-in assertion and return the user it belongs to. */
    public function login(array $data): User
    {
        $credential = WebauthnCredential::with('user')->where('credential_id', $data['rawId'] ?? $data['id'] ?? '')->first();
        if (!$credential || !$credential->user) {
            $this->fail('This fingerprint is not registered. Sign in with your password, then enable fingerprint sign-in again.');
        }

        $clientJson = self::b64uDecode($data['response']['clientDataJSON'] ?? '');
        $this->checkClientData($clientJson, 'webauthn.get', 'login');

        $authData = self::b64uDecode($data['response']['authenticatorData'] ?? '');
        $auth = $this->parseAuthData($authData, false);

        $signature = self::b64uDecode($data['response']['signature'] ?? '');
        $ok = openssl_verify($authData . hash('sha256', $clientJson, true), $signature, $credential->public_key, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            $this->fail('Fingerprint check failed. Please try again.');
        }

        if ($auth['signCount'] > 0 || $credential->sign_count > 0) {
            if ($auth['signCount'] <= $credential->sign_count) {
                $this->fail('This passkey looks copied and was blocked. Sign in with your password.');
            }
        }
        $credential->update(['sign_count' => $auth['signCount'], 'last_used_at' => now()]);

        return $credential->user;
    }

    // ── Internals ──────────────────────────────────────────────────────────

    private function newChallenge(string $purpose): string
    {
        $challenge = self::b64u(random_bytes(32));
        session([self::SESSION_KEY => ['value' => $challenge, 'purpose' => $purpose, 'at' => time()]]);
        return $challenge;
    }

    private function checkClientData(string $json, string $type, string $purpose): void
    {
        $c = json_decode($json, true);
        $stored = session()->pull(self::SESSION_KEY);   // one use only
        if (!is_array($c) || ($c['type'] ?? '') !== $type) {
            $this->fail('Invalid response from the device.');
        }
        if (!$stored || $stored['purpose'] !== $purpose || time() - $stored['at'] > 300 || !hash_equals($stored['value'], (string) ($c['challenge'] ?? ''))) {
            $this->fail('The request expired. Please try again.');
        }
        $origin = parse_url((string) ($c['origin'] ?? ''));
        $local = in_array($this->rpId(), ['localhost', '127.0.0.1'], true);
        if (($origin['host'] ?? '') !== $this->rpId() || (($origin['scheme'] ?? '') !== 'https' && !$local)) {
            $this->fail('This request did not come from this site.');
        }
    }

    private function parseAuthData(string $d, bool $expectCredential): array
    {
        if (strlen($d) < 37 || !hash_equals(hash('sha256', $this->rpId(), true), substr($d, 0, 32))) {
            $this->fail('This passkey belongs to a different site.');
        }
        $flags = ord($d[32]);
        if (!($flags & 0x01) || !($flags & 0x04)) {
            $this->fail('Fingerprint / screen-lock verification is required.');
        }
        $out = ['signCount' => unpack('N', substr($d, 33, 4))[1]];
        if ($expectCredential) {
            if (!($flags & 0x40) || strlen($d) < 55) {
                $this->fail('The device did not return a key.');
            }
            $len = unpack('n', substr($d, 53, 2))[1];
            $out['credentialId'] = substr($d, 55, $len);
            $out['coseKey'] = (new CborDecoder(substr($d, 55 + $len)))->decode();
        }
        return $out;
    }

    /** @return array{0:string,1:int} PEM public key + COSE alg */
    private function coseToPem(array $k): array
    {
        $kty = $k[1] ?? null; $alg = $k[3] ?? null;
        if ($kty === 2 && $alg === -7 && ($k[-1] ?? null) === 1) {          // EC2 P-256
            $x = $k[-2]; $y = $k[-3];
            if (strlen($x) !== 32 || strlen($y) !== 32) {
                $this->fail('Unsupported key.');
            }
            $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $x . $y;
        } elseif ($kty === 3 && $alg === -257) {                               // RSA
            $seq = $this->der(0x30, $this->derInt($k[-1]) . $this->derInt($k[-2]));
            $der = $this->der(0x30, hex2bin('300d06092a864886f70d0101010500') . $this->der(0x03, "\x00" . $seq));
        } else {
            $this->fail('This device uses an unsupported key type.');
        }
        return ["-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n", $alg];
    }

    private function derInt(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || ord($bytes[0]) & 0x80) {
            $bytes = "\x00" . $bytes;
        }
        return $this->der(0x02, $bytes);
    }

    private function der(int $tag, string $value): string
    {
        $len = strlen($value);
        if ($len < 0x80) {
            return chr($tag) . chr($len) . $value;
        }
        $l = ltrim(pack('N', $len), "\x00");
        return chr($tag) . chr(0x80 | strlen($l)) . $l . $value;
    }

    private function rpId(): string
    {
        return request()->getHost();
    }

    private function fail(string $msg): void
    {
        throw ValidationException::withMessages(['webauthn' => $msg]);
    }

    public static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64uDecode(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
    }
}
