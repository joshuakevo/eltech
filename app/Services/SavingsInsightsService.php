<?php

namespace App\Services;

use App\Models\SavingsAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Savings activity analytics for the Savings report.
 *
 * Deposits are split into member deposits, payroll (salary / staff savings) and interest.
 * Opening-balance rows from the data migration are balances, not saving activity, so they
 * count towards balances but never towards deposits.
 */
class SavingsInsightsService
{
    public const PRESETS = [
        'today'         => 'Today',
        'week'          => 'This Week',
        'month'         => 'This Month',
        'last_month'    => 'Last Month',
        'last_2_months' => 'Last 2 Months',
        'last_3_months' => 'Last 3 Months',
        'year'          => 'This Year',
        'custom'        => 'Custom',
    ];

    public const BANDS = [
        'Overdrawn'   => [null, 0],
        '0 – 100K'    => [0, 100000],
        '100K – 500K' => [100000, 500000],
        '500K – 1M'   => [500000, 1000000],
        '1M – 5M'     => [1000000, 5000000],
        '5M+'         => [5000000, null],
    ];

    /** Days without a member deposit before an account counts as dormant. */
    public const DORMANT_DAYS = 60;

    // SQL fragments ----------------------------------------------------------
    private const AMT      = 'ABS(st.amount)';
    private const SIGNED   = "CASE WHEN st.transaction_type = 'withdrawal' THEN -ABS(st.amount) ELSE ABS(st.amount) END";
    private const OPENING  = "LOWER(st.description) LIKE 'opening balance%'";
    private const INTEREST = "LOWER(st.description) LIKE '%interest credit%'";
    private const PAYROLL  = "(st.description LIKE 'Salary —%' OR st.description LIKE 'Staff savings —%')";

    private function isDeposit(): string { return "st.transaction_type = 'deposit' AND NOT (" . self::OPENING . ')'; }
    private function isMemberDeposit(): string { return $this->isDeposit() . ' AND NOT (' . self::INTEREST . ') AND NOT ' . self::PAYROLL; }
    private function isWithdrawal(): string { return "st.transaction_type = 'withdrawal'"; }

    /** @return array{0: Carbon, 1: Carbon, 2: string} [from, to, label] */
    public function resolvePeriod(?string $preset, ?string $from, ?string $to): array
    {
        $today = now()->startOfDay();
        $preset = array_key_exists($preset ?? '', self::PRESETS) ? $preset : 'month';

        [$start, $end] = match ($preset) {
            'today'         => [$today->copy(), $today->copy()],
            'week'          => [$today->copy()->startOfWeek(), $today->copy()],
            'last_month'    => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'last_2_months' => [$today->copy()->subMonthsNoOverflow(1)->startOfMonth(), $today->copy()],
            'last_3_months' => [$today->copy()->subMonthsNoOverflow(2)->startOfMonth(), $today->copy()],
            'year'          => [$today->copy()->startOfYear(), $today->copy()],
            'custom'        => [Carbon::parse($from ?: $today->copy()->startOfMonth())->startOfDay(), Carbon::parse($to ?: $today)->startOfDay()],
            default         => [$today->copy()->startOfMonth(), $today->copy()],
        };
        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end, $preset];
    }

    /** Optional member segment filter (clients.segment_id), applied to every figure. */
    private ?int $segmentId = null;

    public function forSegment(?int $segmentId): static
    {
        $this->segmentId = $segmentId;
        return $this;
    }

    private function base(?int $productId)
    {
        return DB::table('savings_transactions as st')
            ->join('savings_accounts as sa', 'sa.id', '=', 'st.savings_account_id')
            ->whereNull('sa.deleted_at')
            ->when($productId, fn ($q) => $q->where('sa.product_id', $productId))
            ->when($this->segmentId, fn ($q) => $q->whereIn('sa.client_id',
                DB::table('clients')->select('id')->where('segment_id', $this->segmentId)));
    }

    /** Deposits / withdrawals / net / savers for a date range. */
    public function flows(Carbon $from, Carbon $to, ?int $productId = null): array
    {
        $amt = self::AMT;
        $row = $this->base($productId)
            ->whereBetween('st.transaction_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN {$this->isMemberDeposit()} THEN {$amt} END), 0) AS member_deposits,
                COALESCE(SUM(CASE WHEN {$this->isDeposit()} AND " . self::PAYROLL . " THEN {$amt} END), 0) AS payroll,
                COALESCE(SUM(CASE WHEN {$this->isDeposit()} AND " . self::INTEREST . " THEN {$amt} END), 0) AS interest,
                COALESCE(SUM(CASE WHEN {$this->isWithdrawal()} THEN {$amt} END), 0) AS withdrawals,
                COUNT(CASE WHEN {$this->isMemberDeposit()} THEN 1 END) AS deposit_count,
                COUNT(CASE WHEN {$this->isWithdrawal()} THEN 1 END) AS withdrawal_count,
                COUNT(DISTINCT CASE WHEN {$this->isMemberDeposit()} OR ({$this->isDeposit()} AND " . self::PAYROLL . ") THEN st.savings_account_id END) AS savers
            ")->first();

        $deposits = (float) $row->member_deposits + (float) $row->payroll;
        return [
            'member_deposits'  => (float) $row->member_deposits,
            'payroll'          => (float) $row->payroll,
            'interest'         => (float) $row->interest,
            'deposits'         => $deposits,
            'withdrawals'      => (float) $row->withdrawals,
            'net'              => $deposits + (float) $row->interest - (float) $row->withdrawals,
            'deposit_count'    => (int) $row->deposit_count,
            'withdrawal_count' => (int) $row->withdrawal_count,
            'savers'           => (int) $row->savers,
            'avg_deposit'      => $row->deposit_count ? (float) $row->member_deposits / $row->deposit_count : 0,
        ];
    }

    /** Snapshot tiles shown regardless of the selected period. */
    public function snapshots(?int $productId = null): array
    {
        $today = now()->startOfDay();
        $periods = [
            'today'         => ['Today', $today->copy(), $today->copy()],
            'week'          => ['This Week', $today->copy()->startOfWeek(), $today->copy()],
            'month'         => ['This Month', $today->copy()->startOfMonth(), $today->copy()],
            'last_2_months' => ['Last 2 Months', $today->copy()->subMonthsNoOverflow(1)->startOfMonth(), $today->copy()],
        ];
        $out = [];
        foreach ($periods as $key => [$label, $from, $to]) {
            $out[$key] = ['label' => $label, 'from' => $from, 'to' => $to] + $this->flows($from, $to, $productId);
        }
        return $out;
    }

    /** Deposits vs withdrawals over time; daily up to ~2 months, weekly up to a year, else monthly. */
    public function trend(Carbon $from, Carbon $to, ?int $productId = null): array
    {
        $days = $from->diffInDays($to) + 1;
        $grain = $days <= 62 ? 'day' : ($days <= 370 ? 'week' : 'month');
        $amt = self::AMT;

        $rows = $this->base($productId)
            ->whereBetween('st.transaction_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("st.transaction_date AS d,
                COALESCE(SUM(CASE WHEN {$this->isDeposit()} AND NOT (" . self::INTEREST . ") THEN {$amt} END), 0) AS dep,
                COALESCE(SUM(CASE WHEN {$this->isWithdrawal()} THEN {$amt} END), 0) AS wd")
            ->groupBy('st.transaction_date')
            ->get();

        // Build every bucket in range (so quiet days show as zero), then fill.
        $buckets = [];
        $key = fn (Carbon $d) => match ($grain) {
            'day'   => $d->toDateString(),
            'week'  => $d->copy()->startOfWeek()->toDateString(),
            'month' => $d->format('Y-m'),
        };
        $label = fn (Carbon $d) => match ($grain) {
            'day'   => $d->format('d M'),
            'week'  => 'Wk ' . $d->copy()->startOfWeek()->format('d M'),
            'month' => $d->format('M Y'),
        };
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $buckets[$key($d)] ??= ['label' => $label($d), 'deposits' => 0, 'withdrawals' => 0];
        }
        foreach ($rows as $r) {
            $k = $key(Carbon::parse($r->d));
            if (isset($buckets[$k])) {
                $buckets[$k]['deposits']    += (float) $r->dep;
                $buckets[$k]['withdrawals'] += (float) $r->wd;
            }
        }

        return ['grain' => $grain, 'points' => array_values($buckets)];
    }

    /** Member deposits by weekday within the period. */
    public function weekdayPattern(Carbon $from, Carbon $to, ?int $productId = null): array
    {
        $amt = self::AMT;
        $rows = $this->base($productId)
            ->whereBetween('st.transaction_date', [$from->toDateString(), $to->toDateString()])
            ->whereRaw($this->isMemberDeposit())
            ->selectRaw("WEEKDAY(st.transaction_date) AS wd, COUNT(*) AS n, SUM({$amt}) AS total")
            ->groupBy('wd')->get()->keyBy('wd');

        $names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $out = [];
        foreach ($names as $i => $name) {
            $out[] = ['day' => $name, 'count' => (int) ($rows[$i]->n ?? 0), 'total' => (float) ($rows[$i]->total ?? 0)];
        }
        return $out;
    }

    /**
     * Per-account figures for the period: balance at start, deposits, withdrawals, net,
     * balance at end, last member deposit, deposit weeks in the last 4 weeks.
     */
    public function accounts(Carbon $from, Carbon $to, ?int $productId = null): \Illuminate\Support\Collection
    {
        $amt    = self::AMT;
        $signed = self::SIGNED;
        $f = $from->toDateString();
        $t = $to->toDateString();
        $fourWeeksAgo = now()->startOfDay()->subDays(27)->toDateString();

        $stats = $this->base($productId)
            ->where('st.transaction_date', '<=', $t)
            ->groupBy('st.savings_account_id')
            ->selectRaw("st.savings_account_id AS id,
                COALESCE(SUM(CASE WHEN st.transaction_date < '{$f}' THEN {$signed} END), 0) AS opening,
                COALESCE(SUM({$signed}), 0) AS closing,
                COALESCE(SUM(CASE WHEN st.transaction_date >= '{$f}' AND ({$this->isDeposit()}) AND NOT (" . self::INTEREST . ") THEN {$amt} END), 0) AS deposits,
                COALESCE(SUM(CASE WHEN st.transaction_date >= '{$f}' AND ({$this->isDeposit()}) AND " . self::INTEREST . " THEN {$amt} END), 0) AS interest,
                COALESCE(SUM(CASE WHEN st.transaction_date >= '{$f}' AND {$this->isWithdrawal()} THEN {$amt} END), 0) AS withdrawals,
                COUNT(CASE WHEN st.transaction_date >= '{$f}' AND ({$this->isDeposit()}) AND NOT (" . self::INTEREST . ") THEN 1 END) AS deposit_count,
                MAX(CASE WHEN {$this->isMemberDeposit()} OR ({$this->isDeposit()} AND " . self::PAYROLL . ") THEN st.transaction_date END) AS last_deposit,
                MIN(CASE WHEN {$this->isMemberDeposit()} OR ({$this->isDeposit()} AND " . self::PAYROLL . ") THEN st.transaction_date END) AS first_deposit,
                COUNT(DISTINCT CASE WHEN st.transaction_date >= '{$fourWeeksAgo}' AND ({$this->isMemberDeposit()} OR ({$this->isDeposit()} AND " . self::PAYROLL . ")) THEN YEARWEEK(st.transaction_date, 1) END) AS recent_weeks")
            ->get()->keyBy('id');

        $accounts = SavingsAccount::with('client:id,name', 'product:id,name')
            ->where('status', 'active')
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->when($this->segmentId, fn ($q) => $q->whereHas('client', fn ($c) => $c->where('segment_id', $this->segmentId)))
            ->get(['id', 'account_number', 'client_id', 'product_id', 'balance', 'status']);

        $dormantCutoff = now()->startOfDay()->subDays(self::DORMANT_DAYS);

        return $accounts->map(function ($acc) use ($stats, $from, $dormantCutoff) {
            $s = $stats->get($acc->id);
            $deposits    = (float) ($s->deposits ?? 0);
            $withdrawals = (float) ($s->withdrawals ?? 0);
            $interest    = (float) ($s->interest ?? 0);
            $net         = $deposits + $interest - $withdrawals;
            $lastDeposit = !empty($s?->last_deposit) ? Carbon::parse($s->last_deposit) : null;
            $firstDep    = !empty($s?->first_deposit) ? Carbon::parse($s->first_deposit) : null;
            $balance     = (float) $acc->balance;

            $trend = match (true) {
                $balance < 0                                        => 'overdrawn',
                !$lastDeposit || $lastDeposit->lt($dormantCutoff)   => 'dormant',
                $net > 0                                            => 'growing',
                $net < 0                                            => 'declining',
                default                                             => 'steady',
            };

            return (object) [
                'account'       => $acc,
                'opening'       => (float) ($s->opening ?? 0),
                'deposits'      => $deposits,
                'interest'      => $interest,
                'withdrawals'   => $withdrawals,
                'net'           => $net,
                'closing'       => (float) ($s->closing ?? 0),
                'balance'       => $balance,
                'deposit_count' => (int) ($s->deposit_count ?? 0),
                'last_deposit'  => $lastDeposit,
                'is_new'        => $firstDep && $firstDep->gte($from),
                'recent_weeks'  => (int) ($s->recent_weeks ?? 0),
                'trend'         => $trend,
            ];
        });
    }

    /** Headline insights derived from the account rows. */
    public function insights(\Illuminate\Support\Collection $rows, array $flows, array $previous): array
    {
        $active = $rows->count();

        $bands = [];
        foreach (self::BANDS as $label => [$min, $max]) {
            $in = $rows->filter(fn ($r) =>
                ($min === null ? $r->balance < 0 : $r->balance >= $min) && ($max === null || $r->balance < $max)
                && !($label !== 'Overdrawn' && $r->balance < 0));
            $bands[] = ['label' => $label, 'count' => $in->count(), 'total' => $in->sum('balance')];
        }

        $pct = fn ($now, $before) => $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
        $opening = $rows->sum('opening');
        $closing = $rows->sum('closing');

        return [
            'active_accounts'   => $active,
            'participation'     => $active ? round($flows['savers'] / $active * 100, 1) : 0,
            'regular_savers'    => $rows->where('recent_weeks', '>=', 3)->count(),
            'new_savers'        => $rows->where('is_new', true)->count(),
            'dormant'           => $rows->where('trend', 'dormant')->count(),
            'dormant_balance'   => $rows->where('trend', 'dormant')->sum('balance'),
            'overdrawn'         => $rows->where('trend', 'overdrawn')->count(),
            'overdrawn_balance' => $rows->where('trend', 'overdrawn')->sum('balance'),
            'growing'           => $rows->where('trend', 'growing')->count(),
            'declining'         => $rows->where('trend', 'declining')->count(),
            'withdrawal_ratio'  => $flows['deposits'] > 0 ? round($flows['withdrawals'] / $flows['deposits'] * 100, 1) : null,
            'deposits_change'   => $pct($flows['deposits'], $previous['deposits']),
            'net_change'        => $previous['net'] != 0 ? round(($flows['net'] - $previous['net']) / abs($previous['net']) * 100, 1) : null,
            'opening_total'     => $opening,
            'closing_total'     => $closing,
            'portfolio_growth'  => $opening > 0 ? round(($closing - $opening) / $opening * 100, 1) : null,
            'total_balance'     => $rows->sum('balance'),
            'bands'             => $bands,
            'top_savers'        => $rows->filter(fn ($r) => $r->net > 0)->sortByDesc('net')->take(10)->values(),
            'top_withdrawals'   => $rows->filter(fn ($r) => $r->withdrawals > 0)->sortByDesc('withdrawals')->take(10)->values(),
        ];
    }
}
