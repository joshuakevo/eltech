<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\FixedDepositProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Follow-up fix for the 17 clients with an active fixed deposit created by
 * MigrateJuly2026Statement: that migration only had the statement's aggregate
 * fix_dep_save total per client, so every FD was placed on the generic opening
 * date using the active product's default term. Cross-referencing the FixedSvChk
 * historical ledger against the 31/07/2026 statement found the real placement
 * date/term for each -- 16 clients have exactly one real FD (principal matches
 * the statement exactly once matched against the ledger), and BK00028 actually
 * holds 4 separate concurrent FDs (six-month terms placed roughly every two
 * months) summing to their statement total, which the migration had collapsed
 * into one record.
 *
 * Purely a sub-ledger correction: principal totals are unchanged, so the GL
 * (posted by the original migration) needs no new entries.
 */
class FixJuly2026FixedDepositTerms extends Command
{
    protected $signature = 'eltech:fix-fd-terms-2026-07-31 {--confirm : Required to actually run this}';
    protected $description = 'Correct start date/term/maturity on the 17 active FDs created by the 31/07/2026 statement migration using the real FixedSvChk ledger';

    public function handle(): int
    {
        if (!$this->option('confirm')) {
            $this->error('Re-run with --confirm to proceed.');
            return self::FAILURE;
        }

        $path = database_path('data/fd_terms_2026_07_31.json');
        if (!file_exists($path)) {
            $this->error("Data bundle not found at {$path}");
            return self::FAILURE;
        }
        $bundle = json_decode(file_get_contents($path), true);
        $product = FixedDepositProduct::where('is_active', true)->firstOrFail();

        DB::transaction(function () use ($bundle, $product) {
            foreach ($bundle['single'] as $row) {
                $this->fixSingle($row, $product);
            }
            $this->splitClient($bundle['split'], $product);
        });

        $this->info('Done.');
        return self::SUCCESS;
    }

    protected function fixSingle(array $row, FixedDepositProduct $product): void
    {
        $client = Client::where('client_number', $row['client_number'])->firstOrFail();
        $fd = FixedDeposit::where('client_id', $client->id)->where('status', 'active')->firstOrFail();

        if (abs((float) $fd->principal - (float) $row['principal']) > 0.01) {
            throw new \RuntimeException(
                "Principal mismatch for {$row['client_number']}: FD has {$fd->principal}, expected {$row['principal']}"
            );
        }

        $this->applyTerm($fd, $product, $row['start_date'], $row['term_months']);
        $this->line("Fixed {$row['client_number']}: {$row['start_date']} for {$row['term_months']} months -> matures {$fd->fresh()->maturity_date->toDateString()}");
    }

    /**
     * BK00028 held 4 real concurrent deposits the migration collapsed into one.
     * Deletes the single migrated record and recreates each real one -- total
     * principal is unchanged (30M+10M+20M+10M = the original 70M), so the GL
     * posting from the migration still balances without any new entries.
     */
    protected function splitClient(array $split, FixedDepositProduct $product): void
    {
        $client = Client::where('client_number', $split['client_number'])->firstOrFail();
        $existing = FixedDeposit::where('client_id', $client->id)->where('status', 'active')->get();

        $existingTotal = (float) $existing->sum('principal');
        $newTotal = array_sum(array_column($split['deposits'], 'principal'));
        if (abs($existingTotal - $newTotal) > 0.01) {
            throw new \RuntimeException(
                "Principal total mismatch for {$split['client_number']}: existing {$existingTotal}, split sums to {$newTotal}"
            );
        }

        $existing->each(fn (FixedDeposit $fd) => $fd->delete());

        foreach ($split['deposits'] as $i => $row) {
            $fd = new FixedDeposit([
                'client_id'        => $client->id,
                'product_id'       => $product->id,
                'deposit_number'   => 'FD-' . $split['client_number'] . '-' . ($i + 1),
                'principal'        => $row['principal'],
                'interest_rate'    => $product->interest_rate,
                'accrued_interest' => 0,
                'status'           => 'active',
            ]);
            $this->applyTerm($fd, $product, $row['start_date'], $row['term_months']);
            $fd->save();
        }

        $this->line("Split {$split['client_number']} into " . count($split['deposits']) . ' separate FDs.');
    }

    protected function applyTerm(FixedDeposit $fd, FixedDepositProduct $product, string $startDate, int $termMonths): void
    {
        $rate     = $product->interest_rate;
        $interest = (float) $fd->principal * ($rate / 100) * ($termMonths / 12);
        $maturity = \Carbon\Carbon::parse($startDate)->addMonths($termMonths)->toDateString();

        $fd->fill([
            'start_date'       => $startDate,
            'term_months'      => $termMonths,
            'maturity_date'    => $maturity,
            'interest_amount'  => $interest,
            'maturity_amount'  => (float) $fd->principal + $interest,
        ]);
        $fd->save();
    }
}
