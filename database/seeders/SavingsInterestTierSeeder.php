<?php

namespace Database\Seeders;

use App\Models\SavingsInterestTier;
use Illuminate\Database\Seeder;

class SavingsInterestTierSeeder extends Seeder
{
    public function run(): void
    {
        if (SavingsInterestTier::count() > 0) {
            return;
        }

        $tiers = [
            ['min_balance' => 0,          'max_balance' => 499999,   'rate' => 0],
            ['min_balance' => 500000,     'max_balance' => 4999999,  'rate' => 4],
            ['min_balance' => 5000000,    'max_balance' => 9999999,  'rate' => 6],
            ['min_balance' => 10000000,   'max_balance' => null,     'rate' => 7],
        ];

        foreach ($tiers as $tier) {
            SavingsInterestTier::create($tier);
        }
    }
}
