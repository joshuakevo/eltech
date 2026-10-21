<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** CRM → Send Email: one personalised message (subject + body already filled in) with an optional attachment. */
class CrmEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $bodyText,
        public ?array $attachment = null,   // ['name' => …, 'content' => …, 'mime' => …]
    ) {}

    public function build()
    {
        $orgName = \App\Models\SystemSetting::get('org_name', config('app.name'));
        $mail = $this->subject($this->mailSubject)->view('emails.crm')->with([
            'orgName' => $orgName,
            'bodyHtml' => nl2br(e($this->bodyText)),
        ]);
        if ($this->attachment) {
            $mail->attachData($this->attachment['content'], $this->attachment['name'], ['mime' => $this->attachment['mime']]);
        }
        return $mail;
    }
}
