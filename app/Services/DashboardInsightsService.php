<?php

namespace App\Services;

use App\Models\ClientSegment;
use App\Models\LoanProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard analytics: portfolio-at-risk ageing, per-segment performance with
 * attention flags, and data-driven recommendations.
 *
 * Arrears = days since the oldest installment that is due and not fully paid
 * (standard loans only — Locked-Up Loans have no schedule). Savings flows exclude
 * opening balances brought over from previous systems ("Opening balance…").
 */
class DashboardInsightsService
{
    private ?int $lockedUpId;

    public function __construct()
    {
        $this->lockedUpId = LoanProduct::where('name', 'Locked-Up Loans')->value('id');
    }

    /** Running standard loans in arrears: loan_id => [days, principal, segment_id]. */
    private function arrears(): Collection
    {
        return DB::table('loan_schedules as s')
            ->join('loans as l', 'l.id', '=', 's.loan_id')
            ->join('clients as c', 'c.id', '=', 'l.client_id')
            ->whereNull('l.deleted_at')
            ->whereIn('l.status', ['active', 'defaulted'])
            ->when($this->lockedUpId, fn ($q) => $q->where('l.loan_product_id', '!=', $this->lockedUpId))
            ->where('l.outstanding_principal', '>', 0)
            ->whereIn('s.status', ['pending', 'partial', 'overdue'])
            ->where('s.due_date', '<', today()->toDateString())
            ->groupBy('l.id', 'l.outstanding_principal', 'c.segment_id')
            ->selectRaw('l.id, l.outstanding_principal as principal, c.segment_id, MIN(s.due_date) as oldest')
            ->get()
            ->map(fn ($r) => ['days' => (int) \Carbon\Carbon::parse($r->oldest)->diffInDays(today()), 'principal' => (float) $r->principal, 'segment_id' => $r->segment_id]);
    }

    /** Portfolio-at-risk buckets for the standard loan book. */
    public function par(float $standardPrincipal): array
    {
        $arrears = $this->arrears();
        $buckets = [
            ['label' => '1–30 days',   'min' => 1,  'max' => 30,   'color' => '#f59e0b'],
            ['label' => '31–60 days',  'min' => 31, 'max' => 60,   'color' => '#f97316'],
            ['label' => '61–90 days',  'min' => 61, 'max' => 90,   'color' => '#ef4444'],
            ['label' => 'Over 90 days', 'min' => 91, 'max' => null, 'color' => '#991b1b'],
        ];
        foreach ($buckets as &$b) {
            $in = $arrears->filter(fn ($a) => $a['days'] >= $b['min'] && ($b['max'] === null || $a['days'] <= $b['max']));
            $b['count'] = $in->count();
            $b['amount'] = (float) $in->sum('principal');
            $b['pct'] = $standardPrincipal > 0 ? round($b['amount'] / $standardPrincipal * 100, 1) : 0;
        }
        $at = fn ($d) => (float) $arrears->where('days', '>', $d)->sum('principal');
        $pct = fn ($v) => $standardPrincipal > 0 ? round($v / $standardPrincipal * 100, 1) : 0;

        return [
            'buckets' => $buckets,
            'par1' => $pct($at(0)), 'par30' => $pct($at(30)), 'par90' => $pct($at(90)),
            'par30_amount' => $at(30), 'current' => max(0, $standardPrincipal - $at(0)),
            'current_pct' => max(0, 100 - $pct($at(0))),
        ];
    }

    /** Locked-Up recovery activity. */
    public function lockedUpRecovery(): array
    {
        if (!$this->lockedUpId) {
            return ['count' => 0, 'recovered_30' => 0, 'stalled' => 0];
        }
        $loans = DB::table('loans as l')->whereNull('l.deleted_at')->where('l.loan_product_id', $this->lockedUpId)->where('l.status', '!=', 'closed')
            ->leftJoin('loan_repayments as r', 'r.loan_id', '=', 'l.id')
            ->groupBy('l.id', 'l.disbursement_date')->selectRaw('l.id, MAX(r.payment_date) as last_paid, GREATEST(COALESCE(MAX(r.payment_date), l.disbursement_date), l.disbursement_date) as last_activity')->get();
        return [
            'count' => $loans->count(),
            'recovered_30' => (float) DB::table('loan_repayments as r')->join('loans as l', 'l.id', '=', 'r.loan_id')
                ->where('l.loan_product_id', $this->lockedUpId)->where('r.payment_date', '>=', today()->subDays(30)->toDateString())->sum('r.amount'),
            'paying_30' => $loans->filter(fn ($l) => $l->last_paid && $l->last_paid >= today()->subDays(30)->toDateString())->count(),
            // nothing recovered in 90 days (counted from the transfer date for loans with no recovery yet)
            'stalled' => $loans->filter(fn ($l) => !$l->last_activity || $l->last_activity < today()->subDays(90)->toDateString())->count(),
        ];
    }

    /** Per-segment performance with attention flags. */
    public function segments(): Collection
    {
        $since = today()->subDays(30)->toDateString();
        $bySeg = fn ($q) => $q->pluck('v', 'segment_id');

        $members = $bySeg(DB::table('clients')->whereNull('deleted_at')->where('status', 'active')->groupBy('segment_id')->selectRaw('segment_id, COUNT(*) v'));
        $savings = $bySeg(DB::table('savings_accounts as a')->join('clients as c', 'c.id', '=', 'a.client_id')->whereNull('a.deleted_at')->where('a.status', 'active')
            ->groupBy('c.segment_id')->selectRaw('c.segment_id, SUM(a.balance) v'));
        $overdrawn = $bySeg(DB::table('savings_accounts as a')->join('clients as c', 'c.id', '=', 'a.client_id')->whereNull('a.deleted_at')->where('a.status', 'active')->where('a.balance', '<', 0)
            ->groupBy('c.segment_id')->selectRaw('c.segment_id, SUM(a.balance) v'));
        $fds = $bySeg(DB::table('fixed_deposits as f')->join('clients as c', 'c.id', '=', 'f.client_id')->whereNull('f.deleted_at')->where('f.status', 'active')
            ->groupBy('c.segment_id')->selectRaw('c.segment_id, SUM(f.principal) v'));
        $flow = $bySeg(DB::table('savings_transactions as t')->join('savings_accounts as a', 'a.id', '=', 't.savings_account_id')->join('clients as c', 'c.id', '=', 'a.client_id')
            ->where('t.transaction_date', '>=', $since)
            ->where(fn ($q) => $q->whereNull('t.description')->orWhere('t.description', 'not like', 'Opening balance%'))
            ->groupBy('c.segment_id')->selectRaw("c.segment_id, SUM(CASE WHEN t.transaction_type = 'withdrawal' THEN -t.amount ELSE t.amount END) v"));
        $loanQ = fn () => DB::table('loans as l')->join('clients as c', 'c.id', '=', 'l.client_id')->whereNull('l.deleted_at')->whereIn('l.status', ['active', 'defaulted']);
        $std = $bySeg($loanQ()->when($this->lockedUpId, fn ($q) => $q->where('l.loan_product_id', '!=', $this->lockedUpId))->groupBy('c.segment_id')->selectRaw('c.segment_id, SUM(l.outstanding_principal) v'));
        $lu = $bySeg($loanQ()->where('l.loan_product_id', $this->lockedUpId ?? 0)->groupBy('c.segment_id')->selectRaw('c.segment_id, SUM(l.outstanding_principal) v'));
        $recovered = $bySeg(DB::table('loan_repayments as r')->join('loans as l', 'l.id', '=', 'r.loan_id')->join('clients as c', 'c.id', '=', 'l.client_id')
            ->where('r.payment_date', '>=', $since)->groupBy('c.segment_id')->selectRaw('c.segment_id, SUM(r.amount) v'));
        $arrears = $this->arrears();

        $names = ClientSegment::pluck('name', 'id');
        $ids = collect([$members, $savings, $fds, $std, $lu])->flatMap(fn ($c) => $c->keys())->unique();

        return $ids->map(function ($id) use ($names, $members, $savings, $overdrawn, $fds, $flow, $std, $lu, $recovered, $arrears) {
            $key = $id === '' ? null : $id;
            $deposits = (float) ($savings[$key] ?? 0) + (float) ($fds[$key] ?? 0);
            $stdBook  = (float) ($std[$key] ?? 0);
            $par30Amt = (float) $arrears->filter(fn ($a) => (string) $a['segment_id'] === (string) $key && $a['days'] > 30)->sum('principal');
            $row = [
                'name' => $key ? ($names[$key] ?? "Segment #{$key}") : 'No segment',
                'segment_id' => $key,
                'members' => (int) ($members[$key] ?? 0),
                'deposits' => $deposits,
                'standard' => $stdBook,
                'locked_up' => (float) ($lu[$key] ?? 0),
                'par30' => $stdBook > 0 ? round($par30Amt / $stdBook * 100, 1) : 0,
                'flow' => (float) ($flow[$key] ?? 0),
                'recovered' => (float) ($recovered[$key] ?? 0),
                'overdrawn' => (float) ($overdrawn[$key] ?? 0),
            ];

            $reasons = [];
            if ($row['par30'] > 10) {
                $reasons[] = "PAR30 {$row['par30']}%";
            }
            if ($deposits > 0 && $row['flow'] < -0.05 * $deposits) {
                $reasons[] = 'Deposits falling (' . number_format($row['flow'], 0) . ' in 30 days)';
            }
            if ($row['overdrawn'] < 0 && ($deposits <= 0 || abs($row['overdrawn']) > 0.05 * $deposits)) {
                $reasons[] = 'Overdrawn ' . number_format($row['overdrawn'], 0);
            }
            if (($stdBook + $row['locked_up']) > 0 && $row['recovered'] == 0) {
                $reasons[] = 'No loan recoveries in 30 days';
            }
            $row['reasons'] = $reasons;
            $row['status'] = count($reasons) === 0 ? 'Healthy' : (count($reasons) === 1 ? 'Watch' : 'Needs attention');
            return $row;
        })
            ->filter(fn ($r) => $r['members'] || $r['deposits'] || $r['standard'] || $r['locked_up'])
            ->sortByDesc(fn ($r) => [count($r['reasons']), $r['deposits'] + $r['standard'] + $r['locked_up']])
            ->values();
    }

    /** Short, actionable recommendations from the figures. */
    public function recommendations(array $d): array
    {
        $r = [];
        $par = $d['par'];
        if ($par['par30'] > 10) {
            $worst = collect($par['buckets'])->sortByDesc('amount')->first();
            $r[] = ['danger', 'bi-exclamation-octagon', "PAR30 is {$par['par30']}% (" . number_format($par['par30_amount'], 0) . "). Largest arrears bucket: {$worst['label']} — {$worst['count']} loans. Prioritise collections and run loans on schedule."];
        } elseif ($par['par1'] > 0) {
            $r[] = ['warning', 'bi-hourglass-split', "{$par['par1']}% of the standard book has a missed installment — follow up before it passes 30 days."];
        } else {
            $r[] = ['success', 'bi-shield-check', 'No standard loans in arrears.'];
        }
        if ($d['matured'] > 0) {
            $r[] = ['warning', 'bi-calendar-x', "{$d['matured']} standard loan(s) are past maturity with principal still owed — consider restructuring or moving to Locked-Up."];
        }
        $lu = $d['lockedUp'];
        if ($lu['count'] > 0 && $lu['stalled'] > 0) {
            $r[] = ['danger', 'bi-lock', "{$lu['stalled']} of {$lu['count']} Locked-Up loans have had no recovery in 90 days" . ($lu['recovered_30'] > 0 ? ' (' . number_format($lu['recovered_30'], 0) . ' recovered in the last 30 days).' : '.')];
        }
        $attention = $d['segments']->where('status', 'Needs attention');
        if ($attention->count()) {
            $r[] = ['danger', 'bi-diagram-3', 'Segments needing attention: ' . $attention->pluck('name')->implode(', ') . '.'];
        }
        if ($d['fdDue'] > 0) {
            $r[] = ['warning', 'bi-safe', "{$d['fdDue']} fixed deposit(s) are past maturity (" . number_format($d['fdDueAmount'], 0) . ') — mature or roll them over.'];
        }
        if ($d['overdrawnCount'] > 0) {
            $r[] = ['warning', 'bi-dash-circle', "{$d['overdrawnCount']} savings accounts are overdrawn by " . number_format(abs($d['overdrawnAmount']), 0) . ' in total — agree recovery plans.'];
        }
        if ($d['loanToDeposit'] < 50) {
            $r[] = ['info', 'bi-lightbulb', "Loans are only {$d['loanToDeposit']}% of deposits — there is room to lend more to good payers."];
        } elseif ($d['loanToDeposit'] > 100) {
            $r[] = ['danger', 'bi-speedometer', "Loans exceed deposits ({$d['loanToDeposit']}%) — slow new lending and mobilise savings."];
        }
        if ($d['netFlow'] < 0) {
            $r[] = ['warning', 'bi-arrow-down-right', 'Savings outflow this month (' . number_format($d['netFlow'], 0) . ') — follow up on large withdrawals.'];
        }
        if ($d['feesUnpaid'] > 0) {
            $r[] = ['secondary', 'bi-person-badge', "{$d['feesUnpaid']} active members have not paid the membership fee."];
        }
        return array_slice($r, 0, 7);
    }
}
