<?php

namespace App\Http\Controllers;

use App\Mail\CrmEmail;
use App\Models\ClientSegment;
use App\Models\EmailLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * CRM → Send Email. Same recipient groups, filters and placeholders as Send SMS (it reuses SendSmsController's
 * recipient lists), plus a subject, an optional attachment and a test send. Every email is logged in email_logs.
 */
class SendEmailController extends SendSmsController
{
    public const EMAIL_TEMPLATES = [
        'all' => ['subject' => 'A message from {org}',
            'body' => "Dear {name},\n\n\n\nKind regards,\n{org}"],
        'loan_due' => ['subject' => 'Reminder: loan installment due on {due_date}',
            'body' => "Dear {name},\n\nThis is a friendly reminder that your loan installment of {amount} on loan {loan_number} is due on {due_date}.\n\nPlease make sure the money is available on your savings account or pay it at our office by the due date.\n\nKind regards,\n{org}"],
        'loan_overdue' => ['subject' => 'Your loan installment is overdue',
            'body' => "Dear {name},\n\nYour loan installment of {amount} on loan {loan_number} was due on {due_date} and is now overdue.\n\nPlease settle it as soon as possible to avoid penalties. If you have already paid, kindly ignore this message.\n\nKind regards,\n{org}"],
        'dormant_savings' => ['subject' => 'Your savings account {account_number}',
            'body' => "Dear {name},\n\nWe noticed that your savings account {account_number} has been inactive for some time.\n\nKindly visit us or make a deposit to keep it active. We are happy to help.\n\nKind regards,\n{org}"],
    ];

    public function index(Request $request)
    {
        $category = in_array($request->category, ['all', 'loan_due', 'loan_overdue', 'dormant_savings'], true) ? $request->category : 'all';
        $dueDays  = (int) ($request->due_days ?? 7);
        $segments = ClientSegment::orderBy('name')->get();
        $rows     = $this->buildCategoryRows($category, $request, $dueDays);
        $subject  = $request->get('subject', self::EMAIL_TEMPLATES[$category]['subject']);
        $body     = $request->get('body', self::EMAIL_TEMPLATES[$category]['body']);
        $fromAddress = config('mail.from.address');

        return view('send-email.index', compact('category', 'dueDays', 'segments', 'rows', 'subject', 'body', 'fromAddress'));
    }

    public function send(Request $request)
    {
        $request->validate([
            'category'   => 'required|in:all,loan_due,loan_overdue,dormant_savings',
            'subject'    => 'required|string|max:200',
            'body'       => 'required|string|max:10000',
            'attachment' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv',
            'test'       => 'nullable|boolean',
            'ids'        => 'required_unless:test,1|array',
            'ids.*'      => 'integer',
        ]);
        $attachment = $request->hasFile('attachment') ? [
            'name' => $request->file('attachment')->getClientOriginalName(),
            'content' => file_get_contents($request->file('attachment')->getRealPath()),
            'mime' => $request->file('attachment')->getMimeType() ?: 'application/octet-stream',
        ] : null;

        // Test: to the signed-in user, filled with the first selected recipient (or sample values)
        if ($request->boolean('test')) {
            $rows = $request->ids ? $this->resolveRecipientRows($request->category, array_slice($request->ids, 0, 1)) : [];
            $row = $rows[0] ?? ['name' => auth()->user()->name, 'client_number' => 'MK00000', 'amount' => '1,000,000.00', 'due_date' => now()->format('d M Y'), 'account_number' => 'SA-MK00000', 'loan_number' => 'LN-MK00000'];
            $to = auth()->user()->email;
            try {
                Mail::to($to)->send(new CrmEmail('[TEST] ' . $this->fillTemplate($request->subject, $row), $this->fillTemplate($request->body, $row), $attachment));
            } catch (\Throwable $e) {
                return back()->withInput()->with('error', "Test email could not be sent: {$e->getMessage()}");
            }
            return back()->withInput()->with('success', "Test email sent to {$to}" . (isset($rows[0]) ? " (filled in for {$row['name']})." : '.'));
        }

        set_time_limit(0);
        $rows = $this->resolveRecipientRows($request->category, $request->ids);
        $sent = 0;
        $noEmail = [];
        $errors = [];
        $done = [];
        foreach ($rows as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $noEmail[] = $row['name'];
                continue;
            }
            $subject = $this->fillTemplate($request->subject, $row);
            $body = $this->fillTemplate($request->body, $row);
            $key = strtolower($email) . '|' . $subject . '|' . $body;
            if (isset($done[$key])) {
                continue;   // same member listed twice (e.g. two overdue installments): one email
            }
            $done[$key] = true;
            try {
                Mail::to($email)->send(new CrmEmail($subject, $body, $attachment));
                $status = 'sent';
                $error = null;
                $sent++;
            } catch (\Throwable $e) {
                $status = 'failed';
                $error = mb_strimwidth($e->getMessage(), 0, 500, '…');
                $errors[] = "{$row['name']}: " . mb_strimwidth($e->getMessage(), 0, 120, '…');
            }
            EmailLog::create([
                'client_id' => $row['client_id'] ?? null, 'email' => $email, 'subject' => $subject, 'body' => $body,
                'attachment_name' => $attachment['name'] ?? null, 'category' => $request->category,
                'status' => $status, 'error' => $error, 'sent_by' => auth()->id(),
            ]);
        }

        $msg = "Sent {$sent} email(s).";
        if ($noEmail) {
            $msg .= ' No email on file (' . count($noEmail) . '): ' . implode(', ', array_slice($noEmail, 0, 5)) . (count($noEmail) > 5 ? ', …' : '') . '.';
        }
        if ($errors) {
            $msg .= ' Failed: ' . implode('; ', array_slice($errors, 0, 3)) . (count($errors) > 3 ? '; …' : '');
        }
        return redirect()->route('send-email.index', ['category' => $request->category])->with($errors ? 'error' : 'success', $msg);
    }

    public function reports(Request $request)
    {
        $logs = EmailLog::with('sentBy', 'client')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->search, fn ($q) => $q->where(fn ($q2) => $q2->where('email', 'like', "%{$request->search}%")->orWhere('subject', 'like', "%{$request->search}%")
                ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', "%{$request->search}%"))))
            ->when($request->from_date, fn ($q) => $q->whereDate('created_at', '>=', $request->from_date))
            ->when($request->to_date, fn ($q) => $q->whereDate('created_at', '<=', $request->to_date))
            ->latest()->paginate(30)->withQueryString();
        $sentCount   = EmailLog::where('status', 'sent')->count();
        $failedCount = EmailLog::where('status', 'failed')->count();

        return view('send-email.reports', compact('logs', 'sentCount', 'failedCount'));
    }
}
