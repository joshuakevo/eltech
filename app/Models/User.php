<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'email', 'password', 'branch_id', 'phone', 'is_active', 'client_id', 'is_relationship_manager',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at'       => 'datetime',
        'is_active'               => 'boolean',
        'is_relationship_manager' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class);
    }

    /**
     * Portals this user can really use: 'client' (client role + linked member), 'leader' / 'member'
     * (group role + an actual group member profile), 'staff' (any other role). A role without its
     * profile (e.g. group_member with no linked GroupMember) is ignored, so sign-in never lands on a 403.
     */
    public function availablePortals(): array
    {
        $portals = [];
        if ($this->hasRole('client') && ($this->client_id || $this->portalClients()->exists())) {
            $portals[] = 'client';
        }
        $member = \App\Models\GroupMember::where('user_id', $this->id)->first();
        if ($member && $this->hasRole('group_leader')) {
            $portals[] = 'leader';
        }
        if ($member && $this->hasRole('group_member')) {
            $portals[] = 'member';
        }
        if ($this->roles->pluck('name')->diff(['client', 'group_leader', 'group_member'])->isNotEmpty()) {
            $portals[] = 'staff';
        }
        return $portals;
    }

    public function portalClients()
    {
        return $this->belongsToMany(\App\Models\Client::class, 'client_portal_users');
    }

    public function sendPasswordResetNotification($token)
    {
        $orgName = \App\Models\SystemSetting::get('org_name', config('app.name'));

        $this->notify(new class($token, $orgName) extends \Illuminate\Auth\Notifications\ResetPassword {
            public function __construct(string $token, private string $orgName)
            {
                parent::__construct($token);
            }

            public function toMail($notifiable)
            {
                $url = url(route('password.reset', [
                    'token' => $this->token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ], false));

                return (new \Illuminate\Notifications\Messages\MailMessage)
                    ->subject("Your {$this->orgName} Password Reset Request")
                    ->greeting("Hello {$notifiable->name},")
                    ->line("We received a request to reset your password for your {$this->orgName} account.")
                    ->action('Reset My Password', $url)
                    ->line('This link will expire in ' . config('auth.passwords.users.expire', 60) . ' minutes.')
                    ->line('If you did not request a password reset, no action is needed — your account remains secure.')
                    ->salutation("Regards,\n{$this->orgName}");
            }
        });
    }

    public function getRoleNameAttribute(): string
    {
        return $this->roles->first()?->name ?? 'No Role';
    }
}
