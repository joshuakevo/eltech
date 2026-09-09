<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customer Health Score
    |--------------------------------------------------------------------------
    |
    | A weighted blend of five factors, each scored 0-100, combined into one
    | 0-100 score. A factor that doesn't apply to a given client (e.g.
    | "repayment" for a client who has never had a loan) is left out of that
    | client's blend and the remaining weights are redistributed
    | proportionally -- see App\Services\ClientHealthService.
    |
    | Weights are relative, not required to sum to 100 (they're normalized
    | across whichever factors actually apply to a given client).
    |
    */
    'health_score' => [

        // "products" is intentionally a minor weight: a single-product saver
        // who deposits reliably and never misses a payment is a healthy,
        // low-risk member -- holding few products is a cross-sell signal
        // (see the Opportunities feature), not a health/risk signal, so it
        // shouldn't drag a perfectly fine member's score down on its own.
        //
        // "financial_position" (is the client's total balance negative, or
        // do they have an overdrawn savings account) is backed up by a hard
        // override in ClientHealthService: a negative balance forces the
        // classification to At Risk outright, regardless of the blended
        // score -- the weight here only affects the numeric score shown,
        // not whether the override itself fires.
        'weights' => [
            'recency'            => 30, // days since last activity across any product
            'frequency'          => 15, // transaction count in the trailing window
            'repayment'          => 20, // % of due loan installments not overdue
            'products'           => 10, // core products held / total core products
            'trend'              => 10, // total value now vs the trailing window
            'financial_position' => 15, // is the client's total balance negative / overdrawn
        ],

        // Score bands the blended 0-100 score is classified into. Calibrated
        // against this org's actual score distribution (not arbitrary round
        // numbers): most members show naturally lumpy, infrequent savings
        // activity that's genuinely healthy behavior, which centers typical
        // scores around 55-65 rather than 80-100.
        'thresholds' => [
            'healthy'         => 60, // score >= this -> Healthy
            'needs_attention' => 35, // score >= this (and < healthy) -> Needs Attention; below -> At Risk
        ],

        // Recency: 100 at 0 days since last activity, decaying linearly to 0
        // at this many days. Matches the dashboard's dormancy heuristic.
        'recency_decay_days' => 180,

        // Frequency: count of transactions in the trailing N days; a client
        // hitting this many (or more) scores 100, scaling linearly below it.
        // A SACCO savings member depositing monthly is healthy, not just
        // someone transacting weekly -- 3 in a quarter is the bar, not 6+.
        'frequency_window_days' => 90,
        'frequency_benchmark'   => 3,

        // Trend: compare total asset value now vs this many days ago.
        // A change beyond +/- this percentage counts as growing/declining;
        // within the band counts as stable.
        'trend_window_days'  => 90,
        'trend_growth_pct'   => 5,
    ],

];
