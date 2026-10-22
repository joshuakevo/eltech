<?php

namespace App\Mail;

use App\Models\LoanLockup;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Asks an approver to approve (or reject) a loan lock-up request — sent when it is requested and on "Remind approvers". */
class LockupApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LoanLockup $lockup, public User $approver, public string $roleLabel, public bool $isNew) {}

    public function build()
    {
        $orgName = \App\Models\SystemSetting::get('org_name', config('app.name'));
        $loan = $this->lockup->loan;
        return $this->subject(($this->isNew ? 'Approval needed' : 'Reminder: approval needed') . ": lock up {$loan->loan_number} (" . ($loan->client->name ?? '') . ')')
            ->view('emails.lockup-approval')
            ->with(['orgName' => $orgName, 'loan' => $loan]);
    }
}
