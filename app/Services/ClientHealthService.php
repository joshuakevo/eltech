<?php

namespace App\Services;

use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\Loan;
use App\Models\MemberShare;
use App\Models\SavingsAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Customer health score: a weighted blend of six factors, each 0-100,
 * combined into one 0-100 score and classified into Healthy / Needs
 * Attention / At Risk. Weights and thresholds live in config/crm.php so the
 * formula is inspectable and adjustable rather than buried in code.
 *
 * A factor that doesn't apply to a given client (e.g. "repayment" for a
 * client who has never had a loan) is excluded from that client's blend and
 * the remaining weights are redistributed proportionally -- a client isn't
 * penalized for not using a product they were never expected to.
 *
 * A negative total balance (an overdrawn savings account, or total assets
 * net negative) is a hard override to At Risk regardless of the blended
 * score -- no combination of good activity/recency should be able to mask
 * a client who is, in plain terms, in the red.
 *
 * "Recent interactions" (calls, notes, CRM tasks) is deliberately NOT a
 * factor yet -- that data doesn't exist in the system until the Notes/Tasks
 * feature ships. Adding it later just means adding a new factor here.
 */
class ClientHealthService
{
    public function __construct(
        protected ClientActivityService $activity,
        protected ClientFinancialSummaryService $financials,
    ) {
    }

    /**
     * Bulk-compute health scores for a set of client IDs. Returns a
     * Collection keyed by client_id, each value:
     * ['score' => int, 'label' => string, 'color' => string, 'emoji' => string, 'factors' => [...]]
     */
    public function scoresFor(Collection $clientIds): Collection
    {
        $clientIds = $clientIds->filter()->unique()->values();
        if ($clientIds->isEmpty()) {
            return collect();
        }

        $config = config('crm.health_score');

        $lastActivity   = $this->activity->lastActivityDatesFor($clientIds);
        $txnCounts      = $this->transactionCountsFor($clientIds, $config['frequency_window_days']);
        $repayment      = $this->repaymentStatsFor($clientIds);
        $productCounts  = $this->productCountsFor($clientIds);
        $summariesNow   = $this->financials->summariesFor($clientIds);
        $summariesPast  = $this->financials->summariesFor($clientIds, now()->subDays($config['trend_window_days'])->toDateString());
        $overdrawnSet   = SavingsAccount::where('balance', '<', 0)->whereIn('client_id', $clientIds)->distinct()->pluck('client_id')->flip();

        return $clientIds->mapWithKeys(function ($id) use (
            $config, $lastActivity, $txnCounts, $repayment, $productCounts, $summariesNow, $summariesPast, $overdrawnSet
        ) {
            $totalAssetsNow = $summariesNow->get($id)['total_assets'] ?? 0.0;
            $isOverdrawn = isset($overdrawnSet[$id]);

            $factors = [
                'recency'           => $this->recencyFactor($lastActivity->get($id), $config),
                'frequency'         => $this->frequencyFactor($txnCounts->get($id, 0), $config),
                'repayment'         => $this->repaymentFactor($repayment->get($id)),
                'products'          => $this->productsFactor($productCounts->get($id, 0)),
                'trend'             => $this->trendFactor($totalAssetsNow, $summariesPast->get($id)['total_assets'] ?? 0.0, $config),
                'financial_position' => $this->financialPositionFactor($totalAssetsNow, $isOverdrawn),
            ];

            $weights = $config['weights'];
            $weightedSum = 0;
            $totalWeight = 0;
            foreach ($factors as $key => $factor) {
                if (!$factor['applicable']) {
                    continue;
                }
                $weightedSum += $factor['score'] * $weights[$key];
                $totalWeight += $weights[$key];
            }
            $score = $totalWeight > 0 ? (int) round($weightedSum / $totalWeight) : 50;

            $result = array_merge(['score' => $score, 'factors' => $factors], $this->classify($score, $config));

            // Hard override: a negative balance or an overdrawn account can
            // never be classified Healthy or Needs Attention, no matter how
            // good the other factors look.
            if ($totalAssetsNow < 0 || $isOverdrawn) {
                $result['label'] = 'At Risk';
                $result['color'] = 'danger';
                $result['emoji'] = '🔴';
                $result['score'] = min($result['score'], $config['thresholds']['needs_attention'] - 1);
            }

            return [$id => $result];
        });
    }

    public function scoreFor(Client $client): array
    {
        return $this->scoresFor(collect([$client->id]))->get($client->id, array_merge(
            ['score' => 50, 'factors' => []],
            $this->classify(50, config('crm.health_score'))
        ));
    }

    private function classify(int $score, array $config): array
    {
        if ($score >= $config['thresholds']['healthy']) {
            return ['label' => 'Healthy', 'color' => 'success', 'emoji' => '🟢'];
        }
        if ($score >= $config['thresholds']['needs_attention']) {
            return ['label' => 'Needs Attention', 'color' => 'warning', 'emoji' => '🟡'];
        }
        return ['label' => 'At Risk', 'color' => 'danger', 'emoji' => '🔴'];
    }

    private function recencyFactor(?string $lastActivity, array $config): array
    {
        if (!$lastActivity) {
            return ['score' => 0, 'applicable' => true, 'detail' => 'No recorded activity'];
        }
        $days = (int) now()->diffInDays(\Illuminate\Support\Carbon::parse($lastActivity));
        $score = max(0, 100 - ($days / $config['recency_decay_days'] * 100));

        return ['score' => (int) round($score), 'applicable' => true, 'detail' => "Last activity {$days} day(s) ago"];
    }

    private function frequencyFactor(int $count, array $config): array
    {
        $score = min(100, $count / max(1, $config['frequency_benchmark']) * 100);

        return ['score' => (int) round($score), 'applicable' => true, 'detail' => "{$count} transaction(s) in the last {$config['frequency_window_days']} days"];
    }

    private function repaymentFactor($stats): array
    {
        if (!$stats || $stats->total_due == 0) {
            return ['score' => 0, 'applicable' => false, 'detail' => 'No loan history'];
        }
        $onTime = $stats->total_due - $stats->overdue_count;
        $score = ($onTime / $stats->total_due) * 100;

        return [
            'score' => (int) round($score), 'applicable' => true,
            'detail' => "{$onTime} of {$stats->total_due} due installment(s) not overdue",
        ];
    }

    private function productsFactor(int $count): array
    {
        $total = ClientFinancialSummaryService::CORE_PRODUCT_TYPES;
        $score = ($count / max(1, $total)) * 100;

        return ['score' => (int) round($score), 'applicable' => true, 'detail' => "{$count} of {$total} core products held"];
    }

    private function financialPositionFactor(float $totalAssets, bool $isOverdrawn): array
    {
        if ($isOverdrawn) {
            return ['score' => 0, 'applicable' => true, 'detail' => 'Has an overdrawn savings account'];
        }
        if ($totalAssets < 0) {
            return ['score' => 0, 'applicable' => true, 'detail' => 'Negative total balance'];
        }
        return ['score' => 100, 'applicable' => true, 'detail' => 'Balance is positive'];
    }

    private function trendFactor(float $now, float $past, array $config): array
    {
        if ($past == 0.0 && $now == 0.0) {
            return ['score' => 60, 'applicable' => true, 'detail' => 'No balance history to compare'];
        }
        if ($past == 0.0) {
            return ['score' => 100, 'applicable' => true, 'detail' => 'New balance since the comparison window'];
        }

        $changePct = (($now - $past) / abs($past)) * 100;
        if ($changePct > $config['trend_growth_pct']) {
            return ['score' => 100, 'applicable' => true, 'detail' => 'Growing (+' . round($changePct) . '%)'];
        }
        if ($changePct < -$config['trend_growth_pct']) {
            return ['score' => 20, 'applicable' => true, 'detail' => 'Declining (' . round($changePct) . '%)'];
        }
        return ['score' => 60, 'applicable' => true, 'detail' => 'Stable'];
    }

    private function transactionCountsFor(Collection $clientIds, int $windowDays): Collection
    {
        $since = now()->subDays($windowDays)->toDateString();

        $savings = DB::table('savings_transactions')
            ->join('savings_accounts', 'savings_accounts.id', '=', 'savings_transactions.savings_account_id')
            ->whereIn('savings_accounts.client_id', $clientIds)
            ->where('transaction_date', '>=', $since)
            ->groupBy('savings_accounts.client_id')
            ->select('savings_accounts.client_id', DB::raw('COUNT(*) as cnt'))
            ->pluck('cnt', 'client_id');

        $loans = DB::table('loan_repayments')
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->whereIn('loans.client_id', $clientIds)
            ->where('payment_date', '>=', $since)
            ->groupBy('loans.client_id')
            ->select('loans.client_id', DB::raw('COUNT(*) as cnt'))
            ->pluck('cnt', 'client_id');

        $shares = DB::table('share_transactions')
            ->whereIn('client_id', $clientIds)
            ->where('transaction_date', '>=', $since)
            ->groupBy('client_id')
            ->select('client_id', DB::raw('COUNT(*) as cnt'))
            ->pluck('cnt', 'client_id');

        $groups = DB::table('group_transactions')
            ->join('group_members', 'group_members.id', '=', 'group_transactions.member_id')
            ->whereIn('group_members.client_id', $clientIds)
            ->where('transaction_date', '>=', $since)
            ->groupBy('group_members.client_id')
            ->select('group_members.client_id', DB::raw('COUNT(*) as cnt'))
            ->pluck('cnt', 'client_id');

        return $clientIds->mapWithKeys(fn ($id) => [$id =>
            (int) ($savings[$id] ?? 0) + (int) ($loans[$id] ?? 0) + (int) ($shares[$id] ?? 0) + (int) ($groups[$id] ?? 0)
        ]);
    }

    private function repaymentStatsFor(Collection $clientIds): Collection
    {
        return DB::table('loan_schedules')
            ->join('loans', 'loans.id', '=', 'loan_schedules.loan_id')
            ->whereIn('loans.client_id', $clientIds)
            ->where('loan_schedules.due_date', '<=', now()->toDateString())
            ->groupBy('loans.client_id')
            ->select(
                'loans.client_id',
                DB::raw('COUNT(*) as total_due'),
                DB::raw("SUM(CASE WHEN loan_schedules.status IN ('pending','partial','overdue') THEN 1 ELSE 0 END) as overdue_count")
            )
            ->get()
            ->keyBy('client_id');
    }

    private function productCountsFor(Collection $clientIds): Collection
    {
        $savingsSet = SavingsAccount::whereIn('status', ['active', 'dormant'])->whereIn('client_id', $clientIds)->distinct()->pluck('client_id')->flip();
        $loanSet    = Loan::where('status', 'active')->whereIn('client_id', $clientIds)->distinct()->pluck('client_id')->flip();
        $fdSet      = FixedDeposit::where('status', 'active')->whereIn('client_id', $clientIds)->distinct()->pluck('client_id')->flip();
        $shareSet   = MemberShare::whereIn('status', ['partial', 'paid'])->whereIn('client_id', $clientIds)->distinct()->pluck('client_id')->flip();

        return $clientIds->mapWithKeys(fn ($id) => [$id =>
            (isset($savingsSet[$id]) ? 1 : 0) + (isset($loanSet[$id]) ? 1 : 0) + (isset($fdSet[$id]) ? 1 : 0) + (isset($shareSet[$id]) ? 1 : 0)
        ]);
    }
}
