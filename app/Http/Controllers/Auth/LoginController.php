<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /** Where a signed-in user goes: their only portal, or the chooser when they have several. */
    public function redirectForUser($user, bool $useIntended = false)
    {
        $portals = $user->availablePortals();
        $route = match (true) {
            count($portals) > 1          => route('choose-portal'),
            $portals === ['client']      => route('client-portal.dashboard'),
            $portals === ['leader']      => route('group-portal.leader'),
            $portals === ['member']      => route('group-portal.member'),
            default                      => route('dashboard'),
        };
        return $useIntended ? redirect()->intended($route) : redirect($route);
    }

    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectForUser(Auth::user());
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            if (!Auth::user()->is_active) {
                Auth::logout();
                return back()->withErrors(['email' => 'Your account has been deactivated.']);
            }

            return $this->redirectForUser(Auth::user(), true);
        }

        return back()->withErrors(['email' => 'Invalid email or password.'])->withInput($request->only('email'));
    }

    /** Fingerprint (passkey) sign-in: challenge for the device. */
    public function fingerprintOptions(\App\Services\WebAuthnService $webauthn)
    {
        return response()->json($webauthn->loginOptions());
    }

    /** Fingerprint (passkey) sign-in: verify the device's signature and log the user in. */
    public function fingerprintLogin(Request $request, \App\Services\WebAuthnService $webauthn)
    {
        $request->validate(['credential' => 'required|array']);
        try {
            $user = $webauthn->login($request->input('credential'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => 'The device sent an unreadable response. Please try again.'], 422);
        }
        if (!$user->is_active) {
            return response()->json(['message' => 'Your account has been deactivated.'], 422);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        return response()->json(['redirect' => $this->redirectForUser($user, true)->getTargetUrl()]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
