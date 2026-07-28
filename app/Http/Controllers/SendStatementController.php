<?php

namespace App\Http\Controllers;

use App\Mail\SavingsStatementMail;
use App\Models\Client;
use App\Models\ClientSegment;
use App\Models\SavingsAccount;
use App\Services\SavingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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

        $segments = ClientSegment::orderBy('name')->get();

        return view('send-statements.index', compact('clients', 'segments', 'fromDate', 'toDate'));
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
