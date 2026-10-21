<?php

namespace App\Services;

use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\MemberShare;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Client as Http;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * "Ask the system" assistant. Talks to any provider with an OpenAI-compatible chat-completions API (Groq, Google
 * Gemini, OpenRouter, Mistral, a self-hosted Ollama …) — base URL, model and key are set in Settings → AI Assistant.
 *
 * The model only gets READ-ONLY tools that return figures keyed by MEMBER NUMBER: names, phones and emails never leave
 * the server. Member numbers in the final answer are swapped back to "Name (number)" links here, before display.
 * Tools are offered only for the areas the user is permitted to see.
 */
class AiAssistantService
{
    public const PRESETS = [
        'groq'       => ['label' => 'Groq (free tier)',           'base' => 'https://api.groq.com/openai/v1',                              'model' => 'llama-3.3-70b-versatile'],
        'gemini'     => ['label' => 'Google Gemini (free tier)',  'base' => 'https://generativelanguage.googleapis.com/v1beta/openai',     'model' => 'gemini-2.5-flash'],
        'openrouter' => ['label' => 'OpenRouter (free models)',   'base' => 'https://openrouter.ai/api/v1',                                'model' => 'meta-llama/llama-3.3-70b-instruct:free'],
        'custom'     => ['label' => 'Other (OpenAI-compatible)',  'base' => '',                                                            'model' => ''],
    ];

    private const MAX_STEPS = 6;

    /** member number => [id, name], collected from tool results for this answer */
    private array $members = [];

    public function config(): array
    {
        $key = (string) SystemSetting::get('ai_api_key', '');
        try {
            $key = $key !== '' ? Crypt::decryptString($key) : '';
        } catch (\Throwable $e) {
            $key = '';
        }
        return [
            'enabled' => (bool) SystemSetting::get('ai_enabled', 0),
            'base'    => rtrim((string) SystemSetting::get('ai_base_url', self::PRESETS['groq']['base']), '/'),
            'model'   => (string) SystemSetting::get('ai_model', self::PRESETS['groq']['model']),
            'key'     => $key,
            'limit'   => (int) SystemSetting::get('ai_daily_limit', 40),
        ];
    }

    /**
     * Answers $question given earlier turns ($history: [['role' => 'user'|'assistant', 'content' => '…'], …]).
     * Returns ['text' => plain answer (member numbers), 'html' => display HTML with names, 'members' => map].
     */
    public function ask(User $user, string $question, array $history = []): array
    {
        $cfg = $this->config();
        if (!$cfg['key'] || !$cfg['base'] || !$cfg['model']) {
            throw new \RuntimeException('The AI assistant is not set up yet — add a provider key in Settings → AI Assistant.');
        }

        $messages = [['role' => 'system', 'content' => $this->systemPrompt()]];
        foreach (array_slice($history, -10) as $h) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $question];
        $tools = $this->toolsFor($user);

        for ($step = 0; $step < self::MAX_STEPS; $step++) {
            $reply = $this->complete($cfg, $messages, $tools);
            $calls = $reply['tool_calls'] ?? [];
            if (!$calls) {
                $text = trim((string) ($reply['content'] ?? ''));
                return ['text' => $text, 'html' => $this->render($text), 'members' => $this->members];
            }
            $messages[] = ['role' => 'assistant', 'content' => $reply['content'] ?? null, 'tool_calls' => $calls];
            foreach ($calls as $call) {
                $name = $call['function']['name'] ?? '';
                $args = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];
                try {
                    $result = $this->runTool($user, $name, $args);
                } catch (\Throwable $e) {
                    $result = ['error' => 'Could not get that data: ' . $e->getMessage()];
                }
                $messages[] = ['role' => 'tool', 'tool_call_id' => $call['id'] ?? $name, 'content' => json_encode($result)];
            }
        }
        $text = 'Sorry — that question needed too many steps. Try asking something more specific.';
        return ['text' => $text, 'html' => e($text), 'members' => $this->members];
    }

    /** Sends a short request to check the key and model. */
    public function test(): string
    {
        $reply = $this->complete($this->config(), [['role' => 'user', 'content' => 'Reply with the single word OK.']], []);
        return trim((string) ($reply['content'] ?? ''));
    }

    // ── Provider call ────────────────────────────────────────────────────────

    private function complete(array $cfg, array $messages, array $tools): array
    {
        $body = ['model' => $cfg['model'], 'messages' => $messages, 'temperature' => 0.2, 'max_tokens' => 1200];
        if ($tools) {
            $body['tools'] = $tools;
            $body['tool_choice'] = 'auto';
        }
        try {
            $res = (new Http(['timeout' => 60]))->post($cfg['base'] . '/chat/completions', [
                'headers' => ['Authorization' => 'Bearer ' . $cfg['key'], 'Content-Type' => 'application/json'],
                'json'    => $body,
            ]);
        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode();
            $detail = $e->getResponse() ? (json_decode((string) $e->getResponse()->getBody(), true)['error']['message'] ?? '') : $e->getMessage();
            throw new \RuntimeException(match (true) {
                $status === 429 => 'The free AI allowance is used up for the moment — try again in a minute.',
                in_array($status, [401, 403], true) => 'The AI provider rejected the key — check it in Settings → AI Assistant.',
                $status === 404 => 'The AI model or address was not found — check the model name in Settings → AI Assistant.',
                default => 'The AI provider could not answer' . ($detail ? ": {$detail}" : '.'),
            });
        }
        $data = json_decode((string) $res->getBody(), true);
        return $data['choices'][0]['message'] ?? [];
    }

    private function systemPrompt(): string
    {
        $org = SystemSetting::get('org_name', 'the SACCO');
        $currency = SystemSetting::get('currency', 'UGX');
        return "You are the assistant inside {$org}'s financial management system (a SACCO). Today is " . now()->format('l j F Y') . ". "
            . "Answer staff questions about savings, loans, deposits and members using ONLY the tools — never invent figures. "
            . "Amounts are in {$currency}. Members are identified by their member number (e.g. MK00163); always refer to a member by that number exactly as given, never guess a name. "
            . "'This week' means Monday to today, 'this month' the 1st to today, unless the user says otherwise. "
            . "Keep answers short and practical: a one-line summary, then the key figures or a short list (at most 10 items), and a brief recommendation when useful. "
            . "Use plain text with short bullet points ('- '). If the tools can't answer something, say so.";
    }

    // ── Tools (read-only) ────────────────────────────────────────────────────

    private function toolsFor(User $user): array
    {
        $fn = fn ($name, $desc, $props = [], $required = []) => ['type' => 'function', 'function' => [
            'name' => $name, 'description' => $desc,
            'parameters' => ['type' => 'object', 'properties' => (object) $props, 'required' => $required],
        ]];
        $date = ['type' => 'string', 'description' => 'Date YYYY-MM-DD'];
        $tools = [];
        if ($user->can('view savings')) {
            $tools[] = $fn('savings_summary', 'Savings activity for a period: deposits, withdrawals, interest and net flow per savings product, current balances, and the same figures for the previous period of equal length for comparison.',
                ['from' => $date, 'to' => $date], ['from', 'to']);
            $tools[] = $fn('top_savers', 'Top members by savings — either by current total balance, or by deposits made in a period.',
                ['by' => ['type' => 'string', 'enum' => ['balance', 'deposits']], 'from' => $date, 'to' => $date, 'limit' => ['type' => 'integer']], ['by']);
        }
        if ($user->can('view loans')) {
            $tools[] = $fn('loans_to_watch', 'Running loans that are behind: days overdue, missed installments, amount overdue, outstanding principal, penalty owed and last repayment. Sorted most overdue first.',
                ['limit' => ['type' => 'integer']]);
            $tools[] = $fn('loan_portfolio', 'Loan book summary: outstanding principal and interest by product, number of loans, portfolio at risk (PAR 1/30/90 and arrears buckets), Locked-Up loans.');
            $tools[] = $fn('loan_activity', 'Loans disbursed and repayments recovered in a period (principal, interest, penalty).',
                ['from' => $date, 'to' => $date], ['from', 'to']);
        }
        if ($user->can('view fixed-deposits')) {
            $tools[] = $fn('fixed_deposits_maturing', 'Fixed deposits matured or maturing within the next N days.', ['days' => ['type' => 'integer']]);
        }
        if ($user->can('view clients')) {
            $tools[] = $fn('find_member', 'Find members by part of their name or number. Returns member numbers only.', ['query' => ['type' => 'string']], ['query']);
            $tools[] = $fn('member_profile', "One member's savings, loans, fixed deposits and shares.", ['member_number' => ['type' => 'string']], ['member_number']);
        }
        return $tools;
    }

    private function runTool(User $user, string $name, array $a): array
    {
        $allowed = collect($this->toolsFor($user))->pluck('function.name')->all();
        if (!in_array($name, $allowed, true)) {
            return ['error' => 'Not available for this user.'];
        }
        $d = fn ($k, $default) => isset($a[$k]) && strtotime($a[$k]) ? Carbon::parse($a[$k])->toDateString() : $default;
        return match ($name) {
            'savings_summary'          => $this->savingsSummary($d('from', now()->startOfWeek()->toDateString()), $d('to', today()->toDateString())),
            'top_savers'               => $this->topSavers($a['by'] ?? 'balance', $d('from', now()->startOfMonth()->toDateString()), $d('to', today()->toDateString()), min(20, max(1, (int) ($a['limit'] ?? 10)))),
            'loans_to_watch'           => $this->loansToWatch(min(25, max(1, (int) ($a['limit'] ?? 10)))),
            'loan_portfolio'           => $this->loanPortfolio(),
            'loan_activity'            => $this->loanActivity($d('from', now()->startOfMonth()->toDateString()), $d('to', today()->toDateString())),
            'fixed_deposits_maturing'  => $this->fdMaturing(min(365, max(1, (int) ($a['days'] ?? 30)))),
            'find_member'              => $this->findMember((string) ($a['query'] ?? '')),
            'member_profile'           => $this->memberProfile((string) ($a['member_number'] ?? '')),
            default                    => ['error' => 'Unknown tool.'],
        };
    }

    private function code(?Client $c): ?string
    {
        if (!$c) {
            return null;
        }
        $this->members[$c->client_number] = ['id' => $c->id, 'name' => $c->name];
        return $c->client_number;
    }

    private const OPENING = 'Opening balance%';

    private function flows(string $from, string $to): array
    {
        $rows = SavingsTransaction::join('savings_accounts as a', 'a.id', '=', 'savings_transactions.savings_account_id')
            ->join('savings_products as p', 'p.id', '=', 'a.product_id')
            ->whereBetween('savings_transactions.transaction_date', [$from, $to])
            ->where(fn ($q) => $q->whereNull('savings_transactions.description')->orWhere('savings_transactions.description', 'not like', self::OPENING))
            ->groupBy('p.name', 'savings_transactions.transaction_type')
            ->selectRaw('p.name as product, savings_transactions.transaction_type as type, SUM(savings_transactions.amount) as total, COUNT(*) as n')
            ->get();
        $out = [];
        foreach ($rows as $r) {
            $out[$r->product][$r->type] = ['amount' => round((float) $r->total), 'count' => (int) $r->n];
        }
        return $out;
    }

    private function savingsSummary(string $from, string $to): array
    {
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $prevTo = Carbon::parse($from)->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);
        $balances = SavingsProduct::orderBy('name')->get()->map(fn ($p) => [
            'product' => $p->name,
            'active_accounts' => SavingsAccount::where('product_id', $p->id)->where('status', 'active')->count(),
            'total_balance_now' => round((float) SavingsAccount::where('product_id', $p->id)->where('status', 'active')->sum('balance')),
        ])->all();
        return [
            'period' => compact('from', 'to'), 'flows' => $this->flows($from, $to),
            'previous_period' => ['from' => $prevFrom->toDateString(), 'to' => $prevTo->toDateString(), 'flows' => $this->flows($prevFrom->toDateString(), $prevTo->toDateString())],
            'balances_by_product' => $balances,
            'note' => 'Opening balances from the old system are excluded from flows.',
        ];
    }

    private function topSavers(string $by, string $from, string $to, int $limit): array
    {
        if ($by === 'deposits') {
            $rows = SavingsTransaction::join('savings_accounts as a', 'a.id', '=', 'savings_transactions.savings_account_id')
                ->where('savings_transactions.transaction_type', 'deposit')->whereBetween('savings_transactions.transaction_date', [$from, $to])
                ->where(fn ($q) => $q->whereNull('savings_transactions.description')->orWhere('savings_transactions.description', 'not like', self::OPENING))
                ->groupBy('a.client_id')->selectRaw('a.client_id, SUM(savings_transactions.amount) as total, COUNT(*) as n')
                ->orderByDesc('total')->limit($limit)->get();
            $clients = Client::whereIn('id', $rows->pluck('client_id'))->get()->keyBy('id');
            return ['by' => 'deposits', 'period' => compact('from', 'to'), 'members' => $rows->map(fn ($r) => [
                'member' => $this->code($clients[$r->client_id] ?? null), 'deposits' => round((float) $r->total), 'deposit_count' => (int) $r->n,
            ])->all()];
        }
        $rows = SavingsAccount::where('status', 'active')->groupBy('client_id')
            ->selectRaw('client_id, SUM(balance) as total, COUNT(*) as n')->orderByDesc('total')->limit($limit)->get();
        $clients = Client::whereIn('id', $rows->pluck('client_id'))->get()->keyBy('id');
        return ['by' => 'balance', 'members' => $rows->map(fn ($r) => [
            'member' => $this->code($clients[$r->client_id] ?? null), 'total_balance' => round((float) $r->total), 'accounts' => (int) $r->n,
        ])->all()];
    }

    private function loansToWatch(int $limit): array
    {
        $lockedUp = LoanProduct::where('name', 'Locked-Up Loans')->value('id');
        $today = today()->toDateString();
        $overdue = DB::table('loan_schedules as s')->join('loans as l', 'l.id', '=', 's.loan_id')
            ->whereNull('l.deleted_at')->whereIn('l.status', ['active', 'defaulted'])
            ->when($lockedUp, fn ($q) => $q->where('l.loan_product_id', '!=', $lockedUp))
            ->whereIn('s.status', ['pending', 'partial', 'overdue'])->where('s.due_date', '<', $today)
            ->where('s.due_date', '>', LoanInterestService::TRANSFER_DATE)   // installments before the transfer belong to the old system
            ->groupBy('l.id')
            ->selectRaw('l.id, MIN(s.due_date) as oldest, COUNT(*) as missed, SUM(s.principal_due + s.interest_due - s.principal_paid - s.interest_paid) as overdue_amount')
            ->orderBy('oldest')->limit($limit)->get();
        $loans = Loan::with('client', 'product')->whereIn('id', $overdue->pluck('id'))->get()->keyBy('id');
        $svc = app(LoanService::class);
        return ['as_of' => $today, 'note' => 'Counts installments due after the 31 Jul 2026 transfer.', 'loans' => $overdue->map(function ($o) use ($loans, $svc) {
            $l = $loans[$o->id];
            return [
                'member' => $this->code($l->client), 'loan' => $l->loan_number, 'product' => $l->product->name ?? '',
                'days_overdue' => Carbon::parse($o->oldest)->diffInDays(today()), 'missed_installments' => (int) $o->missed,
                'amount_overdue' => round((float) $o->overdue_amount), 'outstanding_principal' => round((float) $l->outstanding_principal),
                'penalty_owed' => round($svc->calculatePenaltyPublic($l)),
                'last_repayment' => optional($l->repayments()->reorder()->latest('payment_date')->first())->payment_date?->toDateString(),
            ];
        })->values()->all()];
    }

    private function loanPortfolio(): array
    {
        $lockedUp = LoanProduct::where('name', 'Locked-Up Loans')->value('id');
        $running = Loan::whereIn('status', ['active', 'defaulted']);
        $byProduct = (clone $running)->when($lockedUp, fn ($q) => $q->where('loan_product_id', '!=', $lockedUp))
            ->join('loan_products as p', 'p.id', '=', 'loans.loan_product_id')->groupBy('p.name')
            ->selectRaw('p.name as product, COUNT(*) as loans, SUM(outstanding_principal) as principal, SUM(outstanding_interest) as interest')->get();
        $standard = (float) $byProduct->sum('principal');
        $par = app(DashboardInsightsService::class)->par($standard);
        $lu = $lockedUp ? Loan::where('loan_product_id', $lockedUp)->where('status', '!=', 'closed') : null;
        return [
            'standard_loans_by_product' => $byProduct->map(fn ($r) => ['product' => $r->product, 'loans' => (int) $r->loans, 'principal' => round((float) $r->principal), 'interest' => round((float) $r->interest)])->all(),
            'standard_principal_total' => round($standard),
            'portfolio_at_risk' => ['par1_pct' => $par['par1'], 'par30_pct' => $par['par30'], 'par90_pct' => $par['par90'],
                'buckets' => collect($par['buckets'])->map(fn ($b) => ['days' => $b['label'], 'loans' => $b['count'], 'principal' => round($b['amount'])])->all()],
            'locked_up' => $lu ? ['loans' => (clone $lu)->count(), 'principal' => round((float) (clone $lu)->sum('outstanding_principal')), 'interest' => round((float) (clone $lu)->sum('outstanding_interest'))] : null,
            'pending_applications' => Loan::where('status', 'pending')->count(),
        ];
    }

    private function loanActivity(string $from, string $to): array
    {
        $lockedUp = LoanProduct::where('name', 'Locked-Up Loans')->value('id');
        $rep = LoanRepayment::whereBetween('payment_date', [$from, $to]);
        return [
            'period' => compact('from', 'to'),
            'disbursed' => ['loans' => Loan::whereBetween('disbursement_date', [$from, $to])->when($lockedUp, fn ($q) => $q->where('loan_product_id', '!=', $lockedUp))->whereIn('status', ['active', 'defaulted', 'closed'])->count(),
                'amount' => round((float) Loan::whereBetween('disbursement_date', [$from, $to])->when($lockedUp, fn ($q) => $q->where('loan_product_id', '!=', $lockedUp))->whereIn('status', ['active', 'defaulted', 'closed'])->sum('principal'))],
            'recovered' => ['repayments' => (clone $rep)->count(), 'total' => round((float) (clone $rep)->sum('amount')), 'principal' => round((float) (clone $rep)->sum('principal_paid')),
                'interest' => round((float) (clone $rep)->sum('interest_paid')), 'penalty' => round((float) (clone $rep)->sum('penalty_paid'))],
        ];
    }

    private function fdMaturing(int $days): array
    {
        $rows = FixedDeposit::with('client')->where('status', 'active')->whereDate('maturity_date', '<=', today()->addDays($days))->orderBy('maturity_date')->limit(30)->get();
        return ['within_days' => $days, 'deposits' => $rows->map(fn ($f) => [
            'member' => $this->code($f->client), 'deposit' => $f->deposit_number, 'principal' => round((float) $f->principal),
            'maturity_amount' => round((float) ($f->maturity_amount ?: $f->principal)), 'maturity_date' => $f->maturity_date->toDateString(),
            'status' => $f->maturity_date->lte(today()) ? 'matured' : 'maturing',
        ])->all()];
    }

    private function findMember(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return ['members' => []];
        }
        $rows = Client::where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('client_number', 'like', "%{$q}%"))->limit(8)->get();
        return ['members' => $rows->map(fn ($c) => ['member' => $this->code($c), 'status' => $c->status])->all()];
    }

    private function memberProfile(string $number): array
    {
        $c = Client::where('client_number', trim($number))->first();
        if (!$c) {
            return ['error' => "No member {$number}."];
        }
        $svc = app(LoanService::class);
        return [
            'member' => $this->code($c), 'status' => $c->status, 'segment' => $c->segment->name ?? null,
            'savings' => SavingsAccount::with('product')->where('client_id', $c->id)->where('status', 'active')->get()
                ->map(fn ($s) => ['account' => $s->account_number, 'product' => $s->product->name ?? '', 'balance' => round((float) $s->balance)])->all(),
            'loans' => Loan::with('product')->where('client_id', $c->id)->whereIn('status', ['active', 'defaulted', 'pending'])->get()
                ->map(fn ($l) => ['loan' => $l->loan_number, 'product' => $l->product->name ?? '', 'status' => $l->status, 'principal' => round((float) $l->principal),
                    'outstanding_principal' => round((float) $l->outstanding_principal), 'outstanding_interest' => round((float) $l->outstanding_interest),
                    'penalty_owed' => round($svc->calculatePenaltyPublic($l))])->all(),
            'fixed_deposits' => FixedDeposit::where('client_id', $c->id)->where('status', 'active')->get()
                ->map(fn ($f) => ['deposit' => $f->deposit_number, 'principal' => round((float) $f->principal), 'maturity_date' => $f->maturity_date?->toDateString()])->all(),
            'shares_paid' => round((float) MemberShare::where('client_id', $c->id)->where('status', '!=', 'liquidated')->sum('amount_paid')),
        ];
    }

    // ── Display ──────────────────────────────────────────────────────────────

    /** Escapes the answer, applies light formatting, and turns member numbers back into "Name (number)" links. */
    public function render(string $text, array $members = null): string
    {
        $members ??= $this->members;
        $html = e($text);
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
        $lines = preg_split('/\r?\n/', $html);
        $out = '';
        $inList = false;
        foreach ($lines as $line) {
            if (preg_match('/^\s*(?:[-*•]|\d+[.)])\s+(.*)$/', $line, $m)) {
                $out .= ($inList ? '' : '<ul class="mb-1 ps-3">') . '<li>' . $m[1] . '</li>';
                $inList = true;
                continue;
            }
            if ($inList) {
                $out .= '</ul>';
                $inList = false;
            }
            $out .= trim($line) === '' ? '' : '<p class="mb-1">' . $line . '</p>';
        }
        if ($inList) {
            $out .= '</ul>';
        }
        // longest numbers first so MK00163 isn't caught inside XMK00163
        uksort($members, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($members as $code => $m) {
            $link = '<a href="' . e(route('clients.show', $m['id'])) . '" class="fw-semibold">' . e($m['name']) . '</a> <span class="text-muted">(' . e($code) . ')</span>';
            $out = preg_replace('/(?<![A-Za-z0-9-])' . preg_quote(e($code), '/') . '(?![A-Za-z0-9])/', "\x01" . base64_encode($link) . "\x02", $out);
        }
        return preg_replace_callback('/\x01([A-Za-z0-9+\/=]+)\x02/', fn ($m) => base64_decode($m[1]), $out);
    }
}
