<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\WebauthnCredential;
use App\Services\WebAuthnService;
use Illuminate\Http\Request;

/** Fingerprint sign-in: register passkeys on the user's devices and manage them. */
class WebAuthnController extends Controller
{
    public function __construct(protected WebAuthnService $webauthn) {}

    public function index()
    {
        $credentials = WebauthnCredential::where('user_id', auth()->id())->latest()->get();
        return view('security.fingerprint', compact('credentials'));
    }

    public function options()
    {
        return response()->json($this->webauthn->registrationOptions(auth()->user()));
    }

    public function store(Request $request)
    {
        $request->validate(['credential' => 'required|array', 'name' => 'nullable|string|max:100']);
        try {
            $credential = $this->webauthn->register(auth()->user(), $request->input('credential'), $request->input('name'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => 'The device sent an unreadable response. Please try again.'], 422);
        }
        AuditLog::record('fingerprint_enabled', "Fingerprint sign-in enabled on {$credential->name}", 'users');
        return response()->json(['ok' => true, 'id' => $credential->id, 'name' => $credential->name]);
    }

    public function destroy(WebauthnCredential $credential)
    {
        abort_unless((int) $credential->user_id === (int) auth()->id(), 403);
        $credential->delete();
        AuditLog::record('fingerprint_removed', "Fingerprint sign-in removed from {$credential->name}", 'users');
        return back()->with('success', 'Device removed. It can no longer sign in with a fingerprint.');
    }
}
