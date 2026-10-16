<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;

class AuditAuthEvents
{
    public function handleLogin(Login $event): void
    {
        // Last sign-in (password, fingerprint or invite link) — shown on the Users page
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'last_login_at')) {
            \App\Models\User::whereKey($event->user->id)->update(['last_login_at' => now()]);
        }

        AuditLog::create([
            'user_id'     => $event->user->id,
            'event'       => 'login',
            'module'      => null,
            'description' => "Logged in",
            'ip_address'  => request()->ip(),
            'url'         => request()->fullUrl(),
            'method'      => 'POST',
            'user_agent'  => request()->userAgent(),
            'created_at'  => now(),
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        AuditLog::create([
            'user_id'     => $event->user?->id,
            'event'       => 'logout',
            'module'      => null,
            'description' => "Logged out",
            'ip_address'  => request()->ip(),
            'url'         => request()->fullUrl(),
            'method'      => 'POST',
            'user_agent'  => request()->userAgent(),
            'created_at'  => now(),
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        AuditLog::create([
            'user_id'     => null,
            'event'       => 'login_failed',
            'module'      => null,
            'description' => "Failed login attempt for: " . ($event->credentials['email'] ?? 'unknown'),
            'ip_address'  => request()->ip(),
            'url'         => request()->fullUrl(),
            'method'      => 'POST',
            'user_agent'  => request()->userAgent(),
            'created_at'  => now(),
        ]);
    }
}
