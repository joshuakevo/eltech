<?php

namespace App\Mail;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SavingsStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, array{filename: string, content: string}> $attachmentsData
     */
    public function __construct(
        public Client $client,
        public array $attachmentsData,
        public string $fromDate,
        public string $toDate,
    ) {}

    public function build()
    {
        $orgName = \App\Models\SystemSetting::get('org_name', config('app.name'));

        $mail = $this->subject("{$orgName} — Savings Statement ({$this->fromDate} to {$this->toDate})")
            ->view('emails.savings-statement')
            ->with([
                'client'   => $this->client,
                'orgName'  => $orgName,
                'fromDate' => $this->fromDate,
                'toDate'   => $this->toDate,
            ]);

        foreach ($this->attachmentsData as $attachment) {
            $mail->attachData($attachment['content'], $attachment['filename'], ['mime' => 'application/pdf']);
        }

        return $mail;
    }
}
