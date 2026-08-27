<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\LoanService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off correction (August 2026) for NORMAL (non-legacy) loans whose
 * schedule was baked while FixLoanInterestRatesAndReschedule's second,
 * corrupting run had temporarily multiplied their interest_rate by 12 --
 * confirmed on LN-2026-00001 (48% Reducing, but installment #1's interest
 * (12,000,000 on a 25,000,000 balance) is exactly principal x 0.48, i.e. the
 * ANNUAL rate applied as if it were the per-period rate -- consistent with a
 * schedule generated when interest_rate briefly read 576% (48 x 12), since
 * dividing that back down by 12 periods/year reproduces the same 0.48 figure
 * even though interest_rate has since been corrected back to 48%).
 *
 * These loans are NOT in loan_terms_2026_07_31.json (the legacy 31/07
 * migration list) -- likely disbursed after the loan_rate_baseline_2026_08_27
 * snapshot was taken, so the earlier RestoreLoanRatesFromBaseline pass never
 * saw them. Their interest_rate/principal/term_months fields are trusted
 * (already correct), only the previously-generated schedule rows are stale.
 *
 * Only touches loans with ZERO repayments recorded (regenerating a schedule
 * with real repayments allocated against it would orphan them -- out of
 * scope here, same as every other schedule-fix command in this series).
 *
 * Detection: rebuild what LoanService::buildScheduleRows() would produce
 * today from the loan's current disbursement_date/principal/rate/term/method
 * (a pure calculation, no side effects) and compare its total interest
 * against the loan's ACTUAL current schedule rows. A mismatch beyond a small
 * rounding tolerance means the stored schedule is stale/corrupted. On
 * --confirm, regenerates via the loan's normal LoanService::generateSchedule()
 * -- the same safe, standard path used for every other loan in the system.
 */
class FixCorruptedNonLegacyLoanSchedules extends Command
{
    protected $signature = 'eltech:fix-corrupted-non-legacy-schedules {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = 'Regenerate normal (non-legacy) loans\' schedules that were baked with a corrupted interest rate, for loans with zero repayments';

    private const TOLERANCE = 10.00;

    public function handle(LoanService $loanService): int
    {
        $confirm = (bool) $this->option('confirm');
        if (!$confirm) {
            $this->warn('DRY RUN — no changes will be saved. Re-run with --confirm to apply.');
        }
        $this->line('');

        $legacyLoanNumbers = [];
        $legacyPath = database_path('data/loan_terms_2026_07_31.json');
        if (file_exists($legacyPath)) {
            $bundle = json_decode(file_get_contents($legacyPath), true) ?? [];
            $legacyLoanNumbers = collect($bundle)->map(fn ($row) => strtolower('LN-' . $row['client_number']))->all();
        }

        $fixed = 0;
        $skippedRepay = 0;
        $skippedBadData = 0;
        $skippedOk = 0;

        $loans = Loan::whereIn('status', ['active', 'pending'])
            ->get()
            ->filter(fn ($loan) => !in_array(strtolower($loan->loan_number), $legacyLoanNumbers, true));

        DB::beginTransaction();
        try {
            foreach ($loans as $loan) {
                if ($loan->repayments()->exists()) {
                    $skippedRepay++;
                    continue;
                }

                if (!$loan->disbursement_date || (float) $loan->principal <= 0 || (int) $loan->term_months <= 0 || (float) $loan->interest_rate <= 0 || !$loan->interest_method) {
                    $skippedBadData++;
                    continue;
                }

                $freshRows = $loanService->buildScheduleRows($loan, Carbon::parse($loan->disbursement_date));
                $freshInterest = round(collect($freshRows)->sum('interest_due'), 2);

                $currentSchedule = $loan->schedules;
                if ($currentSchedule->isEmpty()) {
                    $skippedBadData++;
                    continue;
                }
                $currentInterest = round($currentSchedule->sum('interest_due'), 2);

                if (abs($freshInterest - $currentInterest) <= self::TOLERANCE) {
                    $skippedOk++;
                    continue;
                }

                $this->line(sprintf(
                    '%s (id %d, %s%% %s, principal %s): schedule total interest %s -> %s',
                    $loan->loan_number,
                    $loan->id,
                    rtrim(rtrim(number_format($loan->interest_rate, 2), '0'), '.'),
                    $loan->interest_method,
                    number_format($loan->principal, 2),
                    number_format($currentInterest, 2),
                    number_format($freshInterest, 2)
                ));

                if ($confirm) {
                    $loanService->generateSchedule($loan);
                }

                $fixed++;
            }

            if ($confirm) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->line('');
        $this->info(sprintf(
            'Done. Fixed: %d | Already correct: %d | Skipped (has repayments): %d | Skipped (bad/missing data): %d',
            $fixed, $skippedOk, $skippedRepay, $skippedBadData
        ));

        if (!$confirm) {
            $this->line('');
            $this->info('Dry run only — re-run with --confirm to apply.');
        }

        return self::SUCCESS;
    }
}
