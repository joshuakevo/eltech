<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    private function redirectForUser($user, bool $useIntended = false)
    {
        $portalRoles = ['client', 'group_leader', 'group_member'];
        $hasPortal   = collect($portalRoles)->filter(fn($r) => $user->hasRole($r));
        $hasAdmin    = $user->hasAnyRole(['super_admin', 'admin', 'cashier']);

        // Multiple portal types or portal + admin → let them choose
        if ($hasPortal->count() + ($hasAdmin ? 1 : 0) > 1) {
            $route = route('choose-portal');
            return $useIntended ? redirect()->intended($route) : redirect($route);
        }

        if ($user->hasRole('client')) {
            $route = route('client-portal.dashboard');
            return $useIntended ? redirect()->intended($route) : redirect($route);
        }
        if ($user->hasRole('group_leader')) {
            $route = route('group-portal.leader');
            return $useIntended ? redirect()->intended($route) : redirect($route);
        }
        if ($user->hasRole('group_member')) {
            $route = route('group-portal.member');
            return $useIntended ? redirect()->intended($route) : redirect($route);
        }

        $route = route('dashboard');
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

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
