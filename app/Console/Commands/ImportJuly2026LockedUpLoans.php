<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\AccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time additive import: the old system's separate "Lock Up Report" tracks
 * 76 frozen/non-performing loans (LnBal + IntBal, no further interest/penalty
 * accrual) under its own Fcode scheme -- entirely disconnected from the
 * MemberId codes the July statement migration used, and not reflected in any
 * of that migration's Loan_Value/Loan_Int figures at all.
 *
 * Resolved by name-matching against every client-name source available (the
 * imported 492-client roster, the 537 XX-excluded clients, and the full
 * ClientInfo.xlsx): 62 of 76 match clients already in the system (all
 * currently loan-free, so no conflict), and 14 match XX-excluded clients
 * specifically -- created here as new clients, a deliberate narrow exception
 * to the "don't import XX" rule made only because they carry real,
 * quantifiable locked-up debt.
 *
 * Booked to a dedicated 1104 "Locked-Up Loans Receivable" account (own
 * LoanProduct, 0% rate since these don't accrue further) rather than the
 * normal loan receivable accounts, matching how the old system itself keeps
 * this loan book separate (its own 2111/2112 codes).
 */
class ImportJuly2026LockedUpLoans extends Command
{
    protected $signature = 'eltech:import-locked-up-loans-2026-07-31 {--confirm : Required to actually run this}';
    protected $description = 'Import the 76 locked-up (frozen, non-performing) loans from the old system\'s Lock Up Report as of 31/07/2026';

    protected const OPENING_DATE = '2026-07-31';

    public function handle(AccountingService $accounting): int
    {
        if (!$this->option('confirm')) {
            $this->error('Re-run with --confirm to proceed.');
            return self::FAILURE;
        }

        $path = database_path('data/locked_up_loans_2026_07_31.json');
        if (!file_exists($path)) {
            $this->error("Data bundle not found at {$path}");
            return self::FAILURE;
        }
        $bundle = json_decode(file_get_contents($path), true);

        $receivableAccount = Account::where('account_code', '1104')->firstOrFail();
        $openingEquity      = Account::where('account_code', '3004')->firstOrFail();

        $product = LoanProduct::firstOrCreate(
            ['name' => 'Locked-Up Loans'],
            [
                'interest_rate'              => 0,
                'interest_method'            => 'flat',
                'term_months'                => 0,
                'repayment_frequency'        => 'monthly',
                'penalty_rate'                => 0,
                'min_amount'                  => 0,
                'max_amount'                  => 999999999999,
                'receivable_account_id'       => $receivableAccount->id,
                'interest_income_account_id'  => $receivableAccount->id,
                'is_active'                   => false,
            ]
        );

        $newClients = 0;
        $newLoans   = 0;

        DB::transaction(function () use ($bundle, $accounting, $receivableAccount, $openingEquity, $product, &$newClients, &$newLoans) {
            foreach ($bundle as $row) {
                $client = Client::where('client_number', $row['client_number'])->first();

                if (!$client) {
                    if (!$row['is_new_client']) {
                        throw new \RuntimeException("Expected existing client {$row['client_number']} not found.");
                    }
                    $client = $this->createClient($row);
                    $newClients++;
                }

                $this->createLockedUpLoan($client, $row, $product, $accounting, $receivableAccount, $openingEquity);
                $newLoans++;

                $this->line("{$row['fcode']} -> {$row['client_number']} ({$client->name}): principal " . number_format($row['lnbal']) . ', interest ' . number_format($row['intbal']) . ($row['is_new_client'] ? ' [NEW CLIENT]' : ''));
            }
        });

        $this->info("Done. Created {$newClients} new clients, {$newLoans} locked-up loans.");
        return self::SUCCESS;
    }

    protected function createClient(array $row): Client
    {
        return Client::create([
            'client_number'    => $row['client_number'],
            'client_type'      => 'individual',
            'name'             => $row['name'],
            'first_name'       => $row['first_name'] ?: null,
            'last_name'        => $row['last_name'] ?: null,
            'gender'           => $row['gender'] ?? null,
            'date_of_birth'    => $row['date_of_birth'] ?? null,
            'phone'            => $row['phone'] ?? null,
            'email'            => $row['email'] ?? null,
            'address'          => $row['address'] ?? null,
            'village'          => $row['village'] ?? null,
            'next_of_kin_name' => $row['next_of_kin_name'] ?? null,
            'status'           => 'inactive',
            'joining_date'     => $row['joining_date'] ?? self::OPENING_DATE,
        ]);
    }

    protected function createLockedUpLoan(Client $client, array $row, LoanProduct $product, AccountingService $accounting, Account $receivableAccount, Account $openingEquity): void
    {
        $principal = (float) $row['lnbal'];
        $interest  = (float) $row['intbal'];

        Loan::create([
            'loan_number'           => 'LU-' . $row['fcode'],
            'client_id'             => $client->id,
            'loan_product_id'       => $product->id,
            'principal'             => $principal,
            'interest_rate'         => 0,
            'interest_method'       => 'flat',
            'term_months'           => 0,
            'disbursement_date'     => self::OPENING_DATE,
            'outstanding_principal' => $principal,
            'outstanding_interest'  => $interest,
            'status'                => 'defaulted',
        ]);

        if (abs($principal) < 0.01) {
            return;
        }

        $magnitude = abs($principal);
        $lines = $principal > 0
            ? [
                ['account_id' => $receivableAccount->id, 'debit' => $magnitude, 'credit' => 0, 'client_id' => $client->id],
                ['account_id' => $openingEquity->id, 'debit' => 0, 'credit' => $magnitude, 'client_id' => $client->id],
            ]
            : [
                ['account_id' => $openingEquity->id, 'debit' => $magnitude, 'credit' => 0, 'client_id' => $client->id],
                ['account_id' => $receivableAccount->id, 'debit' => 0, 'credit' => $magnitude, 'client_id' => $client->id],
            ];

        $accounting->post(
            self::OPENING_DATE,
            "Opening balance (31/07/2026, Lock Up Report {$row['fcode']}) - locked-up loan principal",
            $lines,
            'loan',
            null
        );
    }
}
