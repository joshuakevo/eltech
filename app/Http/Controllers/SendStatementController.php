<?php

namespace App\Http\Controllers;

use App\Mail\SavingsStatementMail;
use App\Models\Client;
use App\Models\ClientSegment;
use App\Models\SavingsAccount;
use App\Models\SystemSetting;
use App\Services\SavingsService;
use App\Support\PhoneNumber;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendStatementController extends Controller
{
    public function __construct(protected SavingsService $savingsService) {}

    public function index(Request $request)
    {
        $fromDate = $request->from_date ?? now()->startOfMonth()->toDateString();
        $toDate   = $request->to_date   ?? now()->toDateString();

        $clients = Client::with(['segment', 'savingsAccounts' => fn($q) => $q->where('status', 'active')])
            ->where('client_type', 'individual')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('client_number', 'like', "%{$request->search}%"))
            ->when($request->segment_id, fn($q) => $q->where('segment_id', $request->segment_id))
            ->where('status', $request->status ?? 'active')
            ->orderBy('name')
            ->get()
            ->filter(fn($c) => $c->savingsAccounts->isNotEmpty())
            ->values();

        $clients->each(function ($client) use ($fromDate, $toDate) {
            $client->whatsapp_link = $this->buildWhatsAppLink($client, $fromDate, $toDate);
        });

        $segments = ClientSegment::orderBy('name')->get();

        return view('send-statements.index', compact('clients', 'segments', 'fromDate', 'toDate'));
    }

    /**
     * Public landing page for a WhatsApp-shared statement link -- lists the client's
     * active accounts with a download button each. Signed and time-limited (see
     * buildWhatsAppLink()); no login required since the recipient is on WhatsApp, not
     * logged into the portal.
     */
    public function sharedView(Request $request, Client $client)
    {
        $fromDate = $request->from_date;
        $toDate   = $request->to_date;
        abort_unless($fromDate && $toDate, 400);

        $accounts = SavingsAccount::where('client_id', $client->id)->where('status', 'active')->with('product')->get();

        $accounts->each(function ($account) use ($fromDate, $toDate) {
            $account->pdf_link = URL::temporarySignedRoute(
                'send-statements.shared-pdf',
                now()->addDays(14),
                ['client' => $account->client_id, 'account' => $account->id, 'from_date' => $fromDate, 'to_date' => $toDate]
            );
        });

        return view('send-statements.shared', compact('client', 'accounts', 'fromDate', 'toDate'));
    }

    public function sharedPdf(Request $request, Client $client, SavingsAccount $account)
    {
        abort_unless((int) $account->client_id === (int) $client->id, 404);

        $fromDate = $request->from_date;
        $toDate   = $request->to_date;
        abort_unless($fromDate && $toDate, 400);

        $pdf = $this->buildStatementPdf($account, $fromDate, $toDate);

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="savings-statement-' . $account->account_number . '.pdf"',
        ]);
    }

    /**
     * WhatsApp deep link pre-filled with a signed, time-limited link to the client's
     * shared statement page. No WhatsApp Business API involved -- this just opens the
     * staff member's own WhatsApp with the message ready to send, same as clicking a
     * mailto: link. Null if the client has no usable phone number.
     */
    protected function buildWhatsAppLink(Client $client, string $fromDate, string $toDate): ?string
    {
        $phone = PhoneNumber::normalize($client->phone);
        if (!$phone) {
            return null;
        }

        $shareUrl = URL::temporarySignedRoute(
            'send-statements.shared',
            now()->addDays(14),
            ['client' => $client->id, 'from_date' => $fromDate, 'to_date' => $toDate]
        );

        $orgName = SystemSetting::get('org_name', 'ElTech Finance');
        $message = "Hello {$client->name}, here is your {$orgName} savings statement for "
            . \Carbon\Carbon::parse($fromDate)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($toDate)->format('d M Y')
            . ": {$shareUrl}\n\nThis link expires in 14 days.";

        return 'https://wa.me/' . ltrim($phone, '+') . '?text=' . rawurlencode($message);
    }

    public function send(Request $request)
    {
        $request->validate([
            'client_ids'   => 'required|array|min:1',
            'client_ids.*' => 'exists:clients,id',
            'from_date'    => 'required|date',
            'to_date'      => 'required|date|after_or_equal:from_date',
        ]);

        // Sending to many recipients in one request with no queue worker configured --
        // lift PHP's own execution limit so a large batch isn't cut off mid-send.
        set_time_limit(0);

        $clients = Client::with(['savingsAccounts' => fn($q) => $q->where('status', 'active')])
            ->whereIn('id', $request->client_ids)
            ->get();

        $sent             = 0;
        $skippedNoEmail   = [];
        $skippedNoAccount = [];
        $errors           = [];

        foreach ($clients as $client) {
            if (!$client->email) {
                $skippedNoEmail[] = $client->name;
                continue;
            }

            $accounts = $client->savingsAccounts;
            if ($accounts->isEmpty()) {
                $skippedNoAccount[] = $client->name;
                continue;
            }

            try {
                $attachments = [];
                foreach ($accounts as $account) {
                    $account->setRelation('client', $client);
                    $attachments[] = [
                        'filename' => "savings-statement-{$account->account_number}.pdf",
                        'content'  => $this->buildStatementPdf($account, $request->from_date, $request->to_date),
                    ];
                }

                Mail::to($client->email)->send(
                    new SavingsStatementMail($client, $attachments, $request->from_date, $request->to_date)
                );
                $sent++;
            } catch (\Throwable $e) {
                $errors[] = "{$client->name}: {$e->getMessage()}";
            }
        }

        $msg = "Sent {$sent} statement email(s).";
        if ($skippedNoEmail) {
            $msg .= ' No email on file (' . count($skippedNoEmail) . '): ' . implode(', ', array_slice($skippedNoEmail, 0, 5))
                . (count($skippedNoEmail) > 5 ? ', …' : '') . '.';
        }
        if ($skippedNoAccount) {
            $msg .= ' No active savings account: ' . count($skippedNoAccount) . '.';
        }
        if ($errors) {
            $msg .= ' Errors: ' . implode('; ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? '; …' : '');
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    /**
     * Render a savings statement PDF to raw bytes (for email attachment) rather than
     * streaming a download -- same view/data as SavingsAccountController::statementPdf().
     */
    protected function buildStatementPdf(SavingsAccount $account, string $fromDate, string $toDate): string
    {
        $account->loadMissing('product');

        $transactions = $account->transactions()
            ->with('createdBy')
            ->where('transaction_date', '>=', $fromDate)
            ->where('transaction_date', '<=', $toDate)
            ->reorder('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $projectedInterest = $account->product->interest_method === 'tiered'
            ? $this->savingsService->previewAccruedInterest($account, $toDate)
            : 0;

        return Pdf::loadView('pdf.savings-statement', [
            'account'           => $account,
            'transactions'      => $transactions,
            'fromDate'          => $fromDate,
            'toDate'            => $toDate,
            'projectedInterest' => $projectedInterest,
        ])->setPaper('a4', 'portrait')->output();
    }
}
