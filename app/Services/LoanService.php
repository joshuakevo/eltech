<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanService
{
    public function __construct(protected AccountingService $accounting) {}

    /**
     * Create a new loan and generate its repayment schedule.
     */
    public function createLoan(array $data): Loan
    {
        return DB::transaction(function () use ($data) {
            $product = LoanProduct::findOrFail($data['loan_product_id']);

            $loan = Loan::create([
                'loan_number'     => $this->generateLoanNumber(),
                'client_id'       => $data['client_id'],
                'loan_product_id' => $data['loan_product_id'],
                'principal'       => $data['principal'],
                'interest_rate'        => $data['interest_rate']        ?? $product->interest_rate,
                'interest_method'      => $data['interest_method']      ?? $product->interest_method,
                'repayment_frequency'  => $data['repayment_frequency']  ?? $product->repayment_frequency ?? 'monthly',
                'term_months'          => $data['term_months']          ?? $product->term_months,
                'status'          => 'pending',
                'created_by'      => auth()->id(),
                'notes'           => $data['notes'] ?? null,
            ]);

            return $loan;
        });
    }

    /**
     * Disburse a loan: set status to active, generate schedule, post journal.
     * $feeData = ['method' => 'loan'|'savings', 'savings_account_id' => int|null]
     */
    public function disburseLoan(Loan $loan, string $disbursementDate, array $feeData = []): Loan
    {
        return DB::transaction(function () use ($loan, $disbursementDate, $feeData) {
            $maturityDate         = Carbon::parse($disbursementDate)->addMonths($loan->term_months)->toDateString();
            $managementFeeRate    = (float) ($feeData['management_fee_rate'] ?? 1.5);
            $insuranceFeeRate     = (float) ($feeData['insurance_fee_rate']  ?? 1.5);
            $applicationFeeMethod = $feeData['application_fee_method'] ?? 'loan';
            $managementFeeMethod  = $feeData['management_fee_method']  ?? 'loan';
            $insuranceFeeMethod   = $feeData['insurance_fee_method']   ?? 'loan';
            // Application fee is a flat amount; rate stored as 0
            $applicationFee       = round((float) ($feeData['application_fee_amount'] ?? 0), 2);
            $applicationFeeRate   = 0;
            $managementFee        = round($loan->principal * $managementFeeRate / 100, 2);
            $insuranceFee         = round($loan->principal * $insuranceFeeRate  / 100, 2);

            // Split fees by deduction method
            $loanFees    = ($applicationFeeMethod === 'loan'    ? $applicationFee : 0)
                         + ($managementFeeMethod  === 'loan'    ? $managementFee  : 0)
                         + ($insuranceFeeMethod   === 'loan'    ? $insuranceFee   : 0);
            $savingsFees = ($applicationFeeMethod === 'savings' ? $applicationFee : 0)
                         + ($managementFeeMethod  === 'savings' ? $managementFee  : 0)
                         + ($insuranceFeeMethod   === 'savings' ? $insuranceFee   : 0);

            $anySavings = $savingsFees > 0;

            $loan->update([
                'status'                   => 'active',
                'disbursement_date'        => $disbursementDate,
                'maturity_date'            => $maturityDate,
                'outstanding_principal'    => $loan->principal,
                'application_fee'          => $applicationFee,
                'application_fee_rate'     => $applicationFeeRate,
                'application_fee_method'   => $applicationFeeMethod,
                'management_fee'           => $managementFee,
                'management_fee_rate'      => $managementFeeRate,
                'management_fee_method'    => $managementFeeMethod,
                'insurance_fee'            => $insuranceFee,
                'insurance_fee_rate'       => $insuranceFeeRate,
                'insurance_fee_method'     => $insuranceFeeMethod,
                'fee_savings_account_id'   => $anySavings ? ($feeData['savings_account_id'] ?? null) : null,
                'approved_by'              => auth()->id(),
                'approved_at'              => now(),
            ]);

            $this->generateSchedule($loan);
            $this->postDisbursementJournal(
                $loan, $disbursementDate,
                $applicationFee, $applicationFeeMethod,
                $managementFee,  $managementFeeMethod,
                $insuranceFee,   $insuranceFeeMethod,
                isset($feeData['disbursement_account_id']) && is_numeric($feeData['disbursement_account_id'])
                    ? (int) $feeData['disbursement_account_id']
                    : null
            );

            // Deduct savings-method fees from the savings account
            if ($anySavings && !empty($feeData['savings_account_id'])) {
                $savingsAccount = SavingsAccount::findOrFail($feeData['savings_account_id']);

                if (!$savingsAccount->canWithdraw($savingsFees)) {
                    throw new \InvalidArgumentException(
                        'Insufficient savings balance to cover fees of ' . number_format($savingsFees, 2) . '.'
                    );
                }

                $savingsProduct = $savingsAccount->product;
                $balBefore      = $savingsAccount->balance;

                $savingsLines = [
                    [
                        'account_id'  => $savingsProduct->savings_liability_account_id,
                        'debit'       => $savingsFees,
                        'credit'      => 0,
                        'description' => "Loan fees — {$savingsAccount->account_number}",
                    ],
                ];
                if ($applicationFeeMethod === 'savings' && $applicationFee > 0) {
                    $savingsLines[] = [
                        'account_id'  => $this->getApplicationFeeAccount(),
                        'debit'       => 0,
                        'credit'      => $applicationFee,
                        'description' => "Application fee — {$loan->loan_number}",
                    ];
                }
                if ($managementFeeMethod === 'savings' && $managementFee > 0) {
                    $savingsLines[] = [
                        'account_id'  => $this->getManagementFeeAccount(),
                        'debit'       => 0,
                        'credit'      => $managementFee,
                        'description' => "Management fee — {$loan->loan_number}",
                    ];
                }
                if ($insuranceFeeMethod === 'savings' && $insuranceFee > 0) {
                    $savingsLines[] = [
                        'account_id'  => $this->getInsurancePayableAccount(),
                        'debit'       => 0,
                        'credit'      => $insuranceFee,
                        'description' => "Insurance payable — {$loan->loan_number}",
                    ];
                }
                $feeJournal = $this->accounting->post(
                    $disbursementDate,
                    "Loan fees deducted from savings — {$loan->loan_number}",
                    $savingsLines,
                    'loan',
                    $loan->id
                );

                $savingsAccount->update(['balance' => $balBefore - $savingsFees]);

                SavingsTransaction::create([
                    'savings_account_id' => $savingsAccount->id,
                    'transaction_type'   => 'withdrawal',
                    'amount'             => $savingsFees,
                    'balance_before'     => $balBefore,
                    'balance_after'      => $balBefore - $savingsFees,
                    'transaction_date'   => $disbursementDate,
                    'reference'          => $feeJournal->reference,
                    'description'        => "Loan fees deducted from savings — {$loan->loan_number}",
                    'transaction_id'     => $feeJournal->id,
                    'created_by'         => auth()->id(),
                ]);
            }

            return $loan->fresh();
        });
    }

    /**
     * Generate repayment schedule (flat or reducing balance, monthly or quarterly).
     */
    public function generateSchedule(Loan $loan): void
    {
        $loan->schedules()->delete();

        $rows      = $this->buildScheduleRows($loan, Carbon::parse($loan->disbursement_date));
        $frequency = $loan->repayment_frequency ?? 'monthly';
        $step      = \App\Models\Loan::stepMonthsFor($frequency);

        foreach ($rows as $row) {
            LoanSchedule::create([
                'loan_id'        => $loan->id,
                'installment_no' => $row['installment_no'],
                'due_date'       => $row['due_date'],
                'principal_due'  => $row['principal_due'],
                'interest_due'   => $row['interest_due'],
                'total_due'      => $row['total_due'],
                'balance_after'  => $row['balance_after'],
                'status'         => 'pending',
            ]);
        }

        // Day-based interest: nothing is charged at disbursement; Run Loans charges
        // principal × rate × days / 365 on each due date. Installments hold projections.
        app(LoanInterestService::class)->initialiseNewLoan($loan->fresh());
    }

    /**
     * Generate a projected schedule array without saving (for pending loan preview).
     * Uses today as the assumed disbursement date.
     */
    public function previewSchedule(Loan $loan, string $fromDate = null): array
    {
        return $this->buildScheduleRows($loan, Carbon::parse($fromDate ?? today()), true);
    }

    /**
     * Core schedule builder — used by both generateSchedule and previewSchedule.
     * Returns an array of rows. If $formatDates is true dates are formatted as 'd M Y', else date strings.
     */
    public function buildScheduleRows(Loan $loan, Carbon $startDate, bool $formatDates = false): array
    {
        $principal = $loan->principal;
        $rate      = $loan->interest_rate / 100;
        $months    = $loan->term_months;
        $frequency = $loan->repayment_frequency ?? 'monthly';
        $step      = \App\Models\Loan::stepMonthsFor($frequency);   // months between installments
        $periods   = intval($months / $step);               // number of installments
        $balance   = $principal;
        $rows      = [];

        if ($loan->interest_method === 'flat') {
            // Total interest over entire term (same regardless of frequency)
            $totalInterest   = $principal * $rate * ($months / 12);
            $perPrincipal    = $principal / $periods;
            $perInterest     = $totalInterest / $periods;
            $principalSaved  = 0;
            $interestSaved   = 0;

            for ($i = 1; $i <= $periods; $i++) {
                $balance -= $perPrincipal;

                if ($i === $periods) {
                    $pDue = round($principal - $principalSaved, 2);
                    $iDue = round($totalInterest - $interestSaved, 2);
                } else {
                    $pDue = round($perPrincipal, 2);
                    $iDue = round($perInterest, 2);
                }

                $principalSaved += $pDue;
                $interestSaved  += $iDue;
                $dueDate         = $startDate->copy()->addMonths($i * $step);

                $rows[] = [
                    'installment_no'      => $i,
                    'due_date'            => $formatDates ? $dueDate->format('d M Y') : $dueDate->toDateString(),
                    'principal_due'       => $pDue,
                    'interest_due'        => $iDue,
                    'total_due'           => $pDue + $iDue,
                    'balance_after'       => round(max($balance, 0), 2),
                    // Monthly equivalent breakdown (each period split across its months)
                    'monthly_principal'   => round($pDue / $step, 2),
                    'monthly_interest'    => round($iDue / $step, 2),
                    'monthly_total'       => round(($pDue + $iDue) / $step, 2),
                    'step_months'         => $step,
                ];
            }
        } else {
            // Reducing balance — use period rate (monthly or quarterly)
            $periodsPerYear = 12 / $step;                       // 12 for monthly, 4 for quarterly
            $periodRate     = $rate / $periodsPerYear;

            if ($periodRate == 0) {
                $periodInstallment = $principal / $periods;
            } else {
                $periodInstallment = $principal * ($periodRate * pow(1 + $periodRate, $periods))
                    / (pow(1 + $periodRate, $periods) - 1);
            }

            $principalSaved = 0;

            for ($i = 1; $i <= $periods; $i++) {
                $interestDue  = $balance * $periodRate;
                $principalDue = $periodInstallment - $interestDue;

                if ($i === $periods) {
                    $pStored = round($principal - $principalSaved, 2);
                    $iStored = round($interestDue, 2);
                    $tStored = $pStored + $iStored;
                } else {
                    $pStored = round($principalDue, 2);
                    $iStored = round($interestDue, 2);
                    $tStored = round($periodInstallment, 2);
                }

                $balance -= $principalDue;
                $principalSaved += $pStored;
                $dueDate = $startDate->copy()->addMonths($i * $step);

                $rows[] = [
                    'installment_no'    => $i,
                    'due_date'          => $formatDates ? $dueDate->format('d M Y') : $dueDate->toDateString(),
                    'principal_due'     => $pStored,
                    'interest_due'      => $iStored,
                    'total_due'         => $tStored,
                    'balance_after'     => round(max($balance, 0), 2),
                    'monthly_principal' => round($pStored / $step, 2),
                    'monthly_interest'  => round($iStored / $step, 2),
                    'monthly_total'     => round($tStored / $step, 2),
                    'step_months'       => $step,
                ];
            }
        }

        return $rows;
    }

    /**
     * Process a loan repayment — schedule-based allocation:
     * per-installment (interest → principal) in due order, then penalty last.
     */
    /**
     * The most a repayment on $date can be applied to — exactly what processRepayment() allocates:
     * Locked-Up: principal + interest; day-based: principal + interest already charged and unpaid + carried
     * interest (projected interest on uncharged installments is not owed); otherwise the schedule's unpaid
     * principal and interest; plus penalty as at the date. Early settlement adds days of interest on top
     * (see calculateEarlySettlement).
     */
    public function amountOwed(Loan $loan, string $date): float
    {
        $penalty = $this->calculatePenalty($loan, $date);
        $stored = max(0, (float) $loan->outstanding_principal) + max(0, (float) $loan->outstanding_interest);
        if ($loan->isLockedUp()) {
            return round($stored + $penalty, 2);
        }
        $open = $loan->schedules()->whereIn('status', ['pending', 'partial', 'overdue'])->get();
        if ($open->isEmpty()) {
            return round($stored + $penalty, 2);
        }
        $principal = $open->sum(fn ($s) => max(0, (float) $s->principal_due - (float) $s->principal_paid));
        $interest  = $open->filter(fn ($s) => !$loan->isDayBasedInterest() || $s->interest_charged)
            ->sum(fn ($s) => max(0, (float) $s->interest_due - (float) $s->interest_paid));
        if ($loan->isDayBasedInterest()) {
            $interest += max(0, (float) $loan->interest_carried);
        }
        return round($principal + $interest + $penalty, 2);
    }

    public function processRepayment(Loan $loan, array $data): LoanRepayment
    {
        return DB::transaction(function () use ($loan, $data) {
            // Every repayment works on day-based interest: a loan not yet converted moves over
            // first (carrying its interest charged at the transfer), so a repayment never pays
            // pre-scheduled interest that has not been charged.
            if (!$loan->isLockedUp() && !$loan->isDayBasedInterest() && $loan->disbursement_date && $loan->schedules()->exists()) {
                app(LoanInterestService::class)->convert($loan, Carbon::parse($data['payment_date']));
                $loan->refresh();
            }
            // Paid after an installment's due date: that installment is charged its penalty (paid last, below)
            $this->chargeLatePenalties($loan, $data['payment_date']);

            // Early settlement: charge interest by days up to the settlement date first
            if (!empty($data['settle']) && !$loan->isLockedUp() && $loan->isDayBasedInterest()) {
                app(LoanInterestService::class)->chargeForSettlement($loan, Carbon::parse($data['payment_date']));
                $loan->refresh();
            }

            $amount    = $data['amount'];
            $remaining = $amount;

            $penaltyPaid   = 0;
            $interestPaid  = 0;
            $principalPaid = 0;

            if ($loan->isLockedUp()) {
                // Locked-Up Loans are tracked manually: no schedule, no due dates,
                // no further accrual. Apply straight to the two running balances,
                // principal first then interest, until the loan closes.
                $pDue = $loan->outstanding_principal;
                if ($remaining > 0 && $pDue > 0) {
                    $principalPaid = min($remaining, $pDue);
                    $remaining    -= $principalPaid;
                }

                $iDue = $loan->outstanding_interest;
                if ($remaining > 0 && $iDue > 0) {
                    $interestPaid = min($remaining, $iDue);
                    $remaining   -= $interestPaid;
                }
            } else {
                // 1. Per-installment allocation (interest → principal), earliest first
                $schedules = $loan->schedules()
                    ->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->orderBy('installment_no')
                    ->get();

                // Day-based loans: only charged interest is owed. Pay charged installments
                // first, then carried interest, then principal ahead on later installments
                // (their projected interest is not owed yet).
                $dayBased = $loan->isDayBasedInterest();
                if ($dayBased) {
                    $charged   = $schedules->filter(fn ($s) => $s->interest_charged);
                    $uncharged = $schedules->filter(fn ($s) => !$s->interest_charged);
                    $schedules = $charged->values();
                }

                $carriedPaid = 0;
                foreach ([1, 2] as $pass) {
                if ($pass === 2) {
                    if (!$dayBased) break;
                    $carried = max(0, (float) $loan->interest_carried);
                    if ($remaining > 0 && $carried > 0) {
                        $carriedPaid   = min($remaining, $carried);
                        $interestPaid += $carriedPaid;
                        $remaining    -= $carriedPaid;
                    }
                    $schedules = $uncharged->values();
                }
                foreach ($schedules as $schedule) {
                    if ($remaining <= 0) break;

                    $iDue = ($dayBased && !$schedule->interest_charged) ? 0 : $schedule->interest_due - $schedule->interest_paid;
                    if ($remaining > 0 && $iDue > 0) {
                        $iApply = min($remaining, $iDue);
                        $schedule->interest_paid += $iApply;
                        $interestPaid            += $iApply;
                        $remaining               -= $iApply;
                    }

                    $pDue = $schedule->principal_due - $schedule->principal_paid;
                    if ($remaining > 0 && $pDue > 0) {
                        $pApply = min($remaining, $pDue);
                        $schedule->principal_paid += $pApply;
                        $principalPaid            += $pApply;
                        $remaining                -= $pApply;
                    }

                    if (
                        abs($schedule->principal_paid - $schedule->principal_due) < 0.01 &&
                        abs($schedule->interest_paid  - $schedule->interest_due)  < 0.01
                    ) {
                        $schedule->status = 'paid';
                    } elseif ($schedule->principal_paid > 0 || $schedule->interest_paid > 0) {
                        $schedule->status = 'partial';
                    }

                    $schedule->save();
                }
                }
                if ($carriedPaid > 0) {
                    $loan->interest_carried = round((float) $loan->interest_carried - $carriedPaid, 2);
                    $loan->save();
                }
            }

            // 2. Penalty last, from whatever remains after principal/interest —
            // judged against the date the money was actually recovered, not
            // today. A repayment backdated to on/before an installment's due
            // date must not be penalized just because it's being entered into
            // the system late.
            $penaltyDue = $this->calculatePenalty($loan, $data['payment_date']);
            if ($remaining > 0 && $penaltyDue > 0) {
                $penaltyPaid = min($remaining, $penaltyDue);
                $remaining  -= $penaltyPaid;
            }
            // Penalty brought forward from the old system (31/07/2026) is collected before newer penalties
            $bfPaid = round(min($penaltyPaid, max(0, (float) $loan->penalty_brought_forward)), 2);
            $left = round($penaltyPaid - $bfPaid, 2);
            if ($left > 0) {
                foreach ($loan->schedules()->whereColumn('penalty_charged', '>', 'penalty_paid')->orderBy('installment_no')->get() as $s) {
                    if ($left <= 0) {
                        break;
                    }
                    $pay = round(min($left, (float) $s->penalty_charged - (float) $s->penalty_paid), 2);
                    $s->update(['penalty_paid' => round((float) $s->penalty_paid + $pay, 2)]);
                    $left = round($left - $pay, 2);
                }
            }

            // Never record more than was applied: the extra would be taken from the member and posted nowhere
            if ($remaining > 0.01) {
                throw new \InvalidArgumentException('This repayment is ' . number_format($remaining, 2) . ' more than ' . $loan->loan_number
                    . ' owes on ' . Carbon::parse($data['payment_date'])->format('d M Y') . ' (' . number_format($amount - $remaining, 2) . '). Enter at most that amount.');
            }

            // Post journal
            $paymentSourceAccountId = isset($data['payment_source_account_id']) && is_numeric($data['payment_source_account_id'])
                ? (int) $data['payment_source_account_id']
                : null;

            $transaction = $this->postRepaymentJournal($loan, $data['payment_date'], $principalPaid, $interestPaid, $penaltyPaid, $data['reference'] ?? null, $paymentSourceAccountId);

            $repayment = LoanRepayment::create([
                'loan_id'                  => $loan->id,
                'payment_date'             => $data['payment_date'],
                'amount'                   => $amount,
                'principal_paid'           => $principalPaid,
                'interest_paid'            => $interestPaid,
                'penalty_paid'             => $penaltyPaid,
                'penalty_bf_paid'          => $bfPaid,
                'payment_method'           => $data['payment_method'] ?? 'direct',
                'payment_source_account_id'=> $paymentSourceAccountId,
                'reference'                => $data['reference'] ?? null,
                'received_by'              => auth()->id(),
                'transaction_id'           => $transaction->id,
                'notes'                    => $data['notes'] ?? null,
            ]);

            // Update loan outstanding balances
            $newPrincipal = $loan->outstanding_principal - $principalPaid;
            $newInterest  = $loan->outstanding_interest  - $interestPaid;
            $newPenalty   = max(0, $loan->outstanding_penalty - $penaltyPaid);

            $loan->update([
                'outstanding_principal' => max(0, $newPrincipal),
                'outstanding_interest'  => max(0, $newInterest),
                'outstanding_penalty'   => $newPenalty,
                'penalty_brought_forward' => round(max(0, (float) $loan->penalty_brought_forward - $bfPaid), 2),
            ]);

            if ($newPrincipal <= 0.01 && $newInterest <= 0.01 && $newPenalty <= 0.01) {
                $loan->update(['status' => 'closed']);
                // Day-based: interest projected on installments never charged is not owed — clear it
                if ($loan->isDayBasedInterest() && !$loan->isLockedUp()) {
                    foreach ($loan->schedules()->where('interest_charged', false)->get() as $s) {
                        $s->interest_due = $s->interest_paid;
                        $s->total_due    = round($s->principal_due + $s->interest_due, 2);
                        $s->status       = abs($s->principal_paid - $s->principal_due) < 0.01 ? 'paid' : $s->status;
                        $s->save();
                    }
                }
            } elseif ($loan->isDayBasedInterest() && !$loan->isLockedUp()) {
                app(LoanInterestService::class)->rebalance($loan->fresh());
            }

            return $repayment;
        });
    }

    /**
     * Calculate the amount required to settle the loan early as of a given date:
     * outstanding principal + interest already charged and unpaid + interest by days
     * from the last charge to the date (principal × rate × days ÷ 365) + penalty.
     */
    public function calculateEarlySettlement(Loan $loan, string $date): array
    {
        $principal = $loan->outstanding_principal;

        if ($loan->isLockedUp()) {
            // No schedule to walk — the manually-tracked balance is already
            // the full amount owed, with nothing further accruing.
            $penalty = $this->calculatePenalty($loan, $date);   // only an old-system penalty brought forward
            return [
                'principal' => round($principal, 2),
                'interest'  => round($loan->outstanding_interest, 2),
                'penalty'   => $penalty,
                'total'     => round($principal + $loan->outstanding_interest + $penalty, 2),
            ];
        }

        // Interest by days to the settlement date (same formula as Run Loans)
        $q = app(LoanInterestService::class)->settlementQuote($loan, Carbon::parse($date));
        $penalty = $this->calculatePenalty($loan, $date);

        return [
            'principal'     => $q['principal'],
            'interest'      => $q['interest'],
            'interest_owed' => $q['interest_owed'],
            'accrued'       => $q['accrued'],
            'days'          => $q['days'],
            'from'          => $q['from'],
            'rate'          => $q['rate'],
            'penalty'       => round($penalty, 2),
            'total'         => round($q['principal'] + $q['interest'] + $penalty, 2),
        ];
    }

    /**
     * @param string|null $asOfDate Date the money was actually recovered (e.g. a
     * repayment's payment_date). Defaults to today for general "what does this
     * loan currently owe" displays. An installment due on or after this date is
     * not yet overdue, regardless of when the transaction is entered into the
     * system — backdating a recovery to its true collection date must not
     * manufacture a penalty that recovering it today wouldn't have incurred.
     */
    public function calculatePenaltyPublic(Loan $loan, ?string $asOfDate = null): float
    {
        return $this->calculatePenalty($loan, $asOfDate);
    }

    /**
     * Returns per-installment penalty amounts keyed by schedule ID, as of a given date (default today).
     * Penalties only arise on installments due after the 31/07/2026 transfer; earlier ones were the old system's.
     */
    public function penaltyBreakdown(Loan $loan, ?string $asOfDate = null): array
    {
        $asOf = $asOfDate ?? now()->toDateString();
        $schedules = $loan->schedules()
            ->where(fn ($q) => $q
                // charged (late / short / overdue periods) and not fully paid — owed even after the installment itself is paid
                ->where(fn ($c) => $c->whereColumn('penalty_charged', '>', 'penalty_paid')
                    ->where(fn ($d) => $d->whereNull('penalty_charged_on')->orWhere('penalty_charged_on', '<=', $asOf)))
                // overdue and unpaid, not charged yet (charged when the late payment / run comes);
                // installments up to the transfer belong to the old system
                ->orWhere(fn ($u) => $u->where('penalty_charged', '<=', 0)->where('due_date', '<', $asOf)->where('status', '!=', 'paid')
                    ->where('due_date', '>', LoanInterestService::TRANSFER_DATE)))
            ->get();
        if ($schedules->isEmpty()) return [];

        $tiers = \App\Models\LoanPenaltyTier::orderBy('min_installment')->get();

        $breakdown = [];
        foreach ($schedules as $schedule) {
            $breakdown[$schedule->id] = (float) $schedule->penalty_charged > 0
                ? round((float) $schedule->penalty_charged - (float) $schedule->penalty_paid, 2)
                : $this->penaltyFor($loan, $schedule, $tiers);
        }

        return $breakdown;
    }

    /**
     * Penalty for one missed installment: the tier of the loan's installment **at disbursement** (from its terms —
     * principal, rate, method, term), not the installment's current amount, which grows when arrears roll into it.
     * Falls back to the installment's own amount when the terms give none.
     */
    public function penaltyFor(Loan $loan, $schedule, ?\Illuminate\Support\Collection $tiers = null): float
    {
        $tiers ??= \App\Models\LoanPenaltyTier::orderBy('min_installment')->get();
        $base = app(LoanCorrectionService::class)->termsInstallment($loan) ?? (float) $schedule->total_due;
        return $this->resolvePenaltyTierAmount((float) $base, $tiers);
    }

    /**
     * A loan past maturity and still owing principal is penalised once per period (each due day after maturity —
     * the days Run Loans charges the overdue interest) when less than one installment (the installment at
     * disbursement) was paid in principal + interest since the previous period. The amount is the disbursement
     * installment's tier and is added to the last installment's penalty_charged (paid last like any penalty); the
     * period is logged in loan_overdue_penalties (one per loan + date). Returns the row, or null when not charged.
     */
    public function chargeOverduePeriodPenalty(Loan $loan, string $periodDate, string $previousDate, string $source = 'run'): ?\App\Models\LoanOverduePenalty
    {
        if ($loan->isLockedUp() || (float) $loan->outstanding_principal <= 0.01 || $periodDate <= LoanInterestService::TRANSFER_DATE) {
            return null;
        }
        $last = $loan->schedules()->reorder()->orderByDesc('installment_no')->first();
        if (!$last || $last->due_date->toDateString() >= $periodDate) {
            return null;   // not past maturity yet — normal installment penalties apply
        }
        if (\App\Models\LoanOverduePenalty::where('loan_id', $loan->id)->whereDate('period_date', $periodDate)->exists()) {
            return null;
        }
        $installment = app(LoanCorrectionService::class)->termsInstallment($loan) ?? (float) $last->total_due;
        $paid = (float) $loan->repayments()->reorder()->where('payment_date', '>', $previousDate)->where('payment_date', '<=', $periodDate)
            ->sum(DB::raw('principal_paid + interest_paid'));
        if ($paid + 0.01 >= $installment) {
            return null;   // paid at least an installment in the period
        }
        $amount = $this->penaltyFor($loan, $last);
        if ($amount <= 0) {
            return null;
        }
        $last->update([
            'penalty_charged'    => round((float) $last->penalty_charged + $amount, 2),
            'penalty_charged_on' => $last->penalty_charged_on ?: $periodDate,
            'penalty_source'     => $last->penalty_source ?: 'overdue',
        ]);
        return \App\Models\LoanOverduePenalty::create([
            'loan_id' => $loan->id, 'loan_schedule_id' => $last->id, 'period_date' => $periodDate, 'amount' => $amount,
            'paid_in_period' => round($paid, 2), 'source' => $source, 'created_by' => auth()->id(),
        ]);
    }

    /**
     * Charges the flat tier penalty, once, on installments not fully paid by their due date:
     * every unpaid installment due before $date (a late payment), plus — for Run Loans — the installment due
     * on $date itself when the recovery fell short ($dueToday). Installments up to the transfer are excluded.
     * The penalty stays owed until paid, even after the installment is paid.
     */
    public function chargeLatePenalties(Loan $loan, string $date, bool $dueToday = false): float
    {
        if ($loan->isLockedUp()) {
            return 0.0;
        }
        $tiers = \App\Models\LoanPenaltyTier::orderBy('min_installment')->get();
        $charged = 0.0;
        $rows = $loan->schedules()
            ->where('due_date', '>', LoanInterestService::TRANSFER_DATE)
            ->where('due_date', $dueToday ? '<=' : '<', $date)
            ->where('status', '!=', 'paid')
            ->where('penalty_charged', '<=', 0)
            ->get();
        foreach ($rows as $s) {
            $amount = $this->penaltyFor($loan, $s, $tiers);
            if ($amount <= 0) {
                continue;
            }
            $on = $s->due_date->toDateString() === $date ? $date : $s->due_date->copy()->addDay()->toDateString();
            $s->update(['penalty_charged' => $amount, 'penalty_charged_on' => $on, 'penalty_source' => $dueToday && $on === $date ? 'run' : 'late']);
            $charged += $amount;
        }
        return $charged;
    }

    /**
     * Penalty owed as at a date: flat amount per installment missed since the transfer
     * + the penalty brought forward from the old system that is still unpaid.
     */
    protected function calculatePenalty(Loan $loan, ?string $asOfDate = null): float
    {
        $broughtForward = max(0, (float) $loan->penalty_brought_forward);
        return round(array_sum($this->penaltyBreakdown($loan, $asOfDate)) + $broughtForward, 2);
    }

    /**
     * Flat, one-time penalty for a missed installment, selected by which bracket
     * the installment's own amount falls into (org-wide Loan Penalty Tiers) —
     * not a percentage, and not multiplied by days overdue.
     */
    protected function resolvePenaltyTierAmount(float $installmentAmount, \Illuminate\Support\Collection $tiers): float
    {
        foreach ($tiers as $tier) {
            $max = $tier->max_installment;
            if ($installmentAmount >= $tier->min_installment && ($max === null || $installmentAmount <= $max)) {
                return (float) $tier->penalty_amount;
            }
        }

        return 0;
    }

    protected function updateScheduleStatuses(Loan $loan, float $principalPaid, float $interestPaid): void
    {
        $remaining = $principalPaid;
        $remainingInterest = $interestPaid;

        $pendingSchedules = $loan->schedules()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('installment_no')
            ->get();

        foreach ($pendingSchedules as $schedule) {
            if ($remaining <= 0 && $remainingInterest <= 0) break;

            $principalToApply = min($remaining, $schedule->principal_due - $schedule->principal_paid);
            $interestToApply  = min($remainingInterest, $schedule->interest_due - $schedule->interest_paid);

            $schedule->principal_paid += $principalToApply;
            $schedule->interest_paid  += $interestToApply;
            $remaining                -= $principalToApply;
            $remainingInterest        -= $interestToApply;

            if (
                abs($schedule->principal_paid - $schedule->principal_due) < 0.01 &&
                abs($schedule->interest_paid - $schedule->interest_due) < 0.01
            ) {
                $schedule->status = 'paid';
            } elseif ($schedule->principal_paid > 0 || $schedule->interest_paid > 0) {
                $schedule->status = 'partial';
            }

            $schedule->save();
        }
    }

    protected function postDisbursementJournal(
        Loan $loan, string $date,
        float $applicationFee = 0, string $applicationFeeMethod = 'loan',
        float $managementFee  = 0, string $managementFeeMethod  = 'loan',
        float $insuranceFee   = 0, string $insuranceFeeMethod   = 'loan',
        ?int $disbursementAccountId = null
    ): \App\Models\Transaction {
        $product   = $loan->product;
        $principal = $loan->principal;

        // Only fees deducted from the loan reduce the cash disbursed
        $loanFees = ($applicationFeeMethod === 'loan' ? $applicationFee : 0)
                  + ($managementFeeMethod  === 'loan' ? $managementFee  : 0)
                  + ($insuranceFeeMethod   === 'loan' ? $insuranceFee   : 0);

        $lines = [
            [
                'account_id'  => $this->resolveGlAccountId($product->receivable_account_id, '1101'),
                'debit'       => $principal,
                'credit'      => 0,
                'description' => "Loan receivable - {$loan->loan_number}",
            ],
            [
                'account_id'  => $disbursementAccountId ?? $this->resolveGlAccountId($product->disbursement_account_id, '1001'),
                'debit'       => 0,
                'credit'      => $principal - $loanFees,
                'description' => $loanFees > 0
                    ? "Cash disbursement (net of loan fees) - {$loan->loan_number}"
                    : "Cash disbursement - {$loan->loan_number}",
            ],
        ];

        if ($applicationFeeMethod === 'loan' && $applicationFee > 0) {
            $lines[] = [
                'account_id'  => $this->getApplicationFeeAccount(),
                'debit'       => 0,
                'credit'      => $applicationFee,
                'description' => "Application fee - {$loan->loan_number}",
            ];
        }
        if ($managementFeeMethod === 'loan' && $managementFee > 0) {
            $lines[] = [
                'account_id'  => $this->getManagementFeeAccount(),
                'debit'       => 0,
                'credit'      => $managementFee,
                'description' => "Management fee - {$loan->loan_number}",
            ];
        }
        if ($insuranceFeeMethod === 'loan' && $insuranceFee > 0) {
            $lines[] = [
                'account_id'  => $this->getInsurancePayableAccount(),
                'debit'       => 0,
                'credit'      => $insuranceFee,
                'description' => "Insurance payable - {$loan->loan_number}",
            ];
        }

        return $this->accounting->post(
            $date,
            "Loan disbursement: {$loan->loan_number}",
            $lines,
            'loan',
            $loan->id
        );
    }

    protected function getApplicationFeeAccount(): int
    {
        return Account::where('account_code', '4011')->value('id') ?? 1;
    }

    protected function getManagementFeeAccount(): int
    {
        return Account::where('account_code', '4009')->value('id') ?? 1;
    }

    protected function getInsuranceFeeAccount(): int
    {
        return Account::where('account_code', '4010')->value('id') ?? 1;
    }

    protected function getInsurancePayableAccount(): int
    {
        return Account::where('account_code', '2010')->value('id') ?? 1;
    }

    /**
     * Loan products may omit GL links; fall back to standard chart codes (see ChartOfAccountsSeeder).
     */
    protected function resolveGlAccountId(?int $accountId, string $fallbackAccountCode): int
    {
        if ($accountId !== null) {
            return $accountId;
        }

        return Account::where('account_code', $fallbackAccountCode)->value('id') ?? 1;
    }

    /** An income account for interest / penalty: a product mapped to a non-income account (e.g. its receivable) falls back. */
    protected function resolveIncomeAccountId(?int $accountId, string $fallbackAccountCode): int
    {
        if ($accountId !== null && Account::where('id', $accountId)->value('account_type') === 'revenue') {
            return $accountId;
        }
        return Account::where('account_code', $fallbackAccountCode)->value('id') ?? $this->resolveGlAccountId($accountId, $fallbackAccountCode);
    }

    protected function postRepaymentJournal(Loan $loan, string $date, float $principalPaid, float $interestPaid, float $penaltyPaid, ?string $reference = null, ?int $paymentSourceAccountId = null): \App\Models\Transaction
    {
        $product = $loan->product;
        $total = $principalPaid + $interestPaid + $penaltyPaid;

        $lines = [
            [
                'account_id'  => $paymentSourceAccountId ?? $this->resolveGlAccountId($product->disbursement_account_id, '1001'),
                'debit'       => $total,
                'credit'      => 0,
                'description' => "Loan repayment - {$loan->loan_number}",
            ],
        ];

        if ($principalPaid > 0) {
            $lines[] = [
                'account_id'  => $this->resolveGlAccountId($product->receivable_account_id, '1101'),
                'debit'       => 0,
                'credit'      => $principalPaid,
                'description' => "Principal repayment - {$loan->loan_number}",
            ];
        }

        if ($interestPaid > 0) {
            $lines[] = [
                'account_id'  => $this->resolveIncomeAccountId($product->interest_income_account_id, '4001'),
                'debit'       => 0,
                'credit'      => $interestPaid,
                'description' => "Interest income - {$loan->loan_number}",
            ];
        }

        if ($penaltyPaid > 0) {
            $lines[] = [
                'account_id'  => $this->resolveIncomeAccountId($product->penalty_income_account_id, '4004'),
                'debit'       => 0,
                'credit'      => $penaltyPaid,
                'description' => "Penalty income - {$loan->loan_number}",
            ];
        }

        return $this->accounting->post(
            $date,
            "Loan repayment: {$loan->loan_number}",
            $lines,
            'loan',
            $loan->id,
            $reference ?: null
        );
    }

    protected function generateLoanNumber(): string
    {
        $prefix = 'LN';
        $year   = now()->format('Y');

        // Query raw table (including soft-deleted) so we never reuse a number
        $last = DB::table('loans')
                  ->where('loan_number', 'like', "{$prefix}-{$year}-%")
                  ->max(DB::raw("CAST(SUBSTRING_INDEX(loan_number, '-', -1) AS UNSIGNED)")) ?? 0;

        return "{$prefix}-{$year}-" . str_pad((int) $last + 1, 5, '0', STR_PAD_LEFT);
    }
}
