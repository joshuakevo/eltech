<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $setupUrl,
    ) {}

    public function build()
    {
        $orgName = \App\Models\SystemSetting::get('org_name', config('app.name'));

        return $this->subject("Welcome to {$orgName} — Set up your account")
            ->view('emails.user-invite')
            ->with([
                'user'     => $this->user,
                'orgName'  => $orgName,
                'setupUrl' => $this->setupUrl,
            ]);
    }
}
