<?php

namespace Database\Seeders;

use App\Models\LoanPenaltyTier;
use Illuminate\Database\Seeder;

class LoanPenaltyTierSeeder extends Seeder
{
    public function run(): void
    {
        if (LoanPenaltyTier::count() > 0) {
            return;
        }

        $tiers = [
            ['min_installment' => 50000,      'max_installment' => 500000,   'penalty_amount' => 15000],
            ['min_installment' => 501000,     'max_installment' => 1000000,  'penalty_amount' => 25000],
            ['min_installment' => 1001000,    'max_installment' => 3000000,  'penalty_amount' => 35000],
            ['min_installment' => 3001000,    'max_installment' => 4000000,  'penalty_amount' => 45000],
            ['min_installment' => 4001000,    'max_installment' => null,     'penalty_amount' => 60000],
        ];

        foreach ($tiers as $tier) {
            LoanPenaltyTier::create($tier);
        }
    }
}
