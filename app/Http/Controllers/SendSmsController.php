<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientSegment;
use App\Models\LoanSchedule;
use App\Models\SavingsAccount;
use App\Models\SmsLog;
use App\Models\SystemSetting;
use App\Services\SmsService;
use App\Services\SmsSubscriptionService;
use Illuminate\Http\Request;

class SendSmsController extends Controller
{
    private const TEMPLATES = [
        'all' => "Dear {name}, this is a message from {org}.",
        'loan_due' => "Dear {name}, this is a reminder that your loan installment of {amount} is due on {due_date}. Kindly make your payment on time. - {org}",
        'loan_overdue' => "Dear {name}, your loan installment of {amount} was due on {due_date} and is now overdue. Please settle it as soon as possible to avoid penalties. - {org}",
        'dormant_savings' => "Dear {name}, we noticed your savings account {account_number} has been inactive. Kindly visit us or make a deposit to keep it active. - {org}",
    ];

    public function __construct(protected SmsService $sms, protected SmsSubscriptionService $subscription) {}

    public function index(Request $request)
    {
        $this->subscription->reconcileRecent();

        $category = $request->category ?? 'all';
        $dueDays  = (int) ($request->due_days ?? 7);

        $segments = ClientSegment::orderBy('name')->get();
        $rows     = $this->buildCategoryRows($category, $request, $dueDays);

        $message = $request->has('message') ? $request->message : self::TEMPLATES[$category];

        $canSend            = $this->subscription->canSend();
        $freeTrialRemaining = $this->subscription->freeTrialRemaining();
        $activeSubscription = $this->subscription->activeSubscription();
        $subscriptionPrice  = $this->subscription->subscriptionPrice();

        return view('send-sms.index', compact(
            'category', 'dueDays', 'segments', 'rows', 'message',
            'canSend', 'freeTrialRemaining', 'activeSubscription', 'subscriptionPrice'
        ));
    }

    public function reports(Request $request)
    {
        $logs = SmsLog::with('sentBy')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->when($request->search, fn($q) => $q->where(fn($q2) => $q2->where('phone', 'like', "%{$request->search}%")
                ->orWhereHas('client', fn($q3) => $q3->where('name', 'like', "%{$request->search}%"))))
            ->when($request->from_date, fn($q) => $q->whereDate('created_at', '>=', $request->from_date))
            ->when($request->to_date, fn($q) => $q->whereDate('created_at', '<=', $request->to_date))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $sentCount   = SmsLog::where('status', 'sent')->count();
        $failedCount = SmsLog::where('status', 'failed')->count();

        return view('send-sms.reports', compact('logs', 'sentCount', 'failedCount'));
    }

    public function send(Request $request)
    {
        $request->validate([
            'category' => 'required|in:all,loan_due,loan_overdue,dormant_savings',
            'ids'      => 'required|array|min:1',
            'ids.*'    => 'integer',
            'message'  => 'required|string|max:918',
        ]);

        if (!$this->subscription->canSend()) {
            return back()->with('error', 'Your free trial has ended and there is no active SMS subscription. Subscribe below to continue sending SMS.');
        }

        set_time_limit(0);

        $rows    = $this->resolveRecipientRows($request->category, $request->ids);
        $gateway = config('services.sms.default', 'africastalking');

        $sent           = 0;
        $skippedNoPhone = [];
        $errors         = [];

        foreach ($rows as $row) {
            if (!$row['phone']) {
                $skippedNoPhone[] = $row['name'];
                continue;
            }

            $text   = $this->fillTemplate($request->message, $row);
            $result = $this->sms->send($row['phone'], $text);

            SmsLog::create([
                'client_id' => $row['client_id'] ?? null,
                'phone'     => $row['phone'],
                'message'   => $text,
                'category'  => $request->category,
                'status'    => $result['success'] ? 'sent' : 'failed',
                'gateway'   => $gateway,
                'error'     => $result['success'] ? null : $result['message'],
                'sent_by'   => auth()->id(),
            ]);

            if ($result['success']) {
                $sent++;
            } else {
                $errors[] = "{$row['name']}: {$result['message']}";
            }
        }

        $msg = "Sent {$sent} SMS.";
        if ($skippedNoPhone) {
            $msg .= ' No phone on file (' . count($skippedNoPhone) . '): ' . implode(', ', array_slice($skippedNoPhone, 0, 5))
                . (count($skippedNoPhone) > 5 ? ', …' : '') . '.';
        }
        if ($errors) {
            $msg .= ' Errors: ' . implode('; ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? '; …' : '');
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    /**
     * Rows for the listing/selection screen, filtered by search/segment.
     */
    protected function buildCategoryRows(string $category, Request $request, int $dueDays): array
    {
        $search    = $request->search;
        $segmentId = $request->segment_id;

        $clientFilter = function ($q) use ($search, $segmentId) {
            $q->when($search, fn($q2) => $q2->where('name', 'like', "%{$search}%")->orWhere('client_number', 'like', "%{$search}%"))
              ->when($segmentId, fn($q2) => $q2->where('segment_id', $segmentId));
        };

        if ($category === 'loan_due' || $category === 'loan_overdue') {
            $query = LoanSchedule::with('loan.client')
                ->whereHas('loan', fn($q) => $q->where('status', 'active')->whereHas('client', $clientFilter))
                ->where('status', '!=', 'paid');

            if ($category === 'loan_due') {
                $query->whereBetween('due_date', [now()->toDateString(), now()->addDays($dueDays)->toDateString()]);
            } else {
                $query->where('due_date', '<', now()->toDateString());
            }

            return $query->get()->map(fn($schedule) => [
                'id'             => $schedule->id,
                'name'           => $schedule->loan->client->name,
                'client_number'  => $schedule->loan->client->client_number,
                'phone'          => $schedule->loan->client->phone,
                'detail'         => "Loan {$schedule->loan->loan_number} — due " . $schedule->due_date->format('d M Y'),
                'amount'         => number_format($schedule->principal_balance + $schedule->interest_balance, 2),
                'due_date'       => $schedule->due_date->format('d M Y'),
                'account_number' => null,
            ])->values()->all();
        }

        if ($category === 'dormant_savings') {
            $query = SavingsAccount::with('client')
                ->where('status', 'dormant')
                ->whereHas('client', $clientFilter);

            return $query->get()->map(fn($account) => [
                'id'             => $account->id,
                'name'           => $account->client->name,
                'client_number'  => $account->client->client_number,
                'phone'          => $account->client->phone,
                'detail'         => "Savings {$account->account_number} — dormant",
                'amount'         => null,
                'due_date'       => null,
                'account_number' => $account->account_number,
            ])->values()->all();
        }

        // 'all'
        $query = Client::where('status', 'active')->where($clientFilter);

        return $query->orderBy('name')->get()->map(fn($client) => [
            'id'             => $client->id,
            'name'           => $client->name,
            'client_number'  => $client->client_number,
            'phone'          => $client->phone,
            'detail'         => null,
            'amount'         => null,
            'due_date'       => null,
            'account_number' => null,
        ])->values()->all();
    }

    /**
     * Re-resolve the exact same rows server-side from category + ids at send time --
     * never trust merge-field values (amount, due_date, etc.) submitted by the client.
     */
    protected function resolveRecipientRows(string $category, array $ids): array
    {
        if ($category === 'loan_due' || $category === 'loan_overdue') {
            return LoanSchedule::with('loan.client')
                ->whereIn('id', $ids)
                ->get()
                ->filter(fn($s) => $s->loan && $s->loan->client)
                ->map(fn($schedule) => [
                    'client_id'      => $schedule->loan->client->id,
                    'name'           => $schedule->loan->client->name,
                    'client_number'  => $schedule->loan->client->client_number,
                    'phone'          => $schedule->loan->client->phone,
                    'amount'         => number_format($schedule->principal_balance + $schedule->interest_balance, 2),
                    'due_date'       => $schedule->due_date->format('d M Y'),
                    'account_number' => '',
                    'loan_number'    => $schedule->loan->loan_number,
                ])->values()->all();
        }

        if ($category === 'dormant_savings') {
            return SavingsAccount::with('client')
                ->whereIn('id', $ids)
                ->get()
                ->filter(fn($a) => $a->client)
                ->map(fn($account) => [
                    'client_id'      => $account->client->id,
                    'name'           => $account->client->name,
                    'client_number'  => $account->client->client_number,
                    'phone'          => $account->client->phone,
                    'amount'         => '',
                    'due_date'       => '',
                    'account_number' => $account->account_number,
                    'loan_number'    => '',
                ])->values()->all();
        }

        return Client::whereIn('id', $ids)->get()->map(fn($client) => [
            'client_id'      => $client->id,
            'name'           => $client->name,
            'client_number'  => $client->client_number,
            'phone'          => $client->phone,
            'amount'         => '',
            'due_date'       => '',
            'account_number' => '',
            'loan_number'    => '',
        ])->values()->all();
    }

    protected function fillTemplate(string $template, array $row): string
    {
        return strtr($template, [
            '{name}'           => $row['name'] ?? '',
            '{client_number}'  => $row['client_number'] ?? '',
            '{amount}'         => $row['amount'] ?? '',
            '{due_date}'       => $row['due_date'] ?? '',
            '{account_number}' => $row['account_number'] ?? '',
            '{loan_number}'    => $row['loan_number'] ?? '',
            '{org}'            => SystemSetting::get('org_name', config('app.name')),
        ]);
    }
}
