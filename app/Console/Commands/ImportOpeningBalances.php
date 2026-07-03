<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\FixedDepositProduct;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\MemberShare;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\ShareTransaction;
use App\Services\AccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportOpeningBalances extends Command
{
    protected $signature = 'import:opening-balances
        {file : Path to the CSV file}
        {--date=2026-05-31 : Opening balance date (YYYY-MM-DD)}
        {--branch=1 : Branch ID to assign new clients to}
        {--opt-save-product= : Savings product ID for Opt/General savings (asked interactively if omitted)}
        {--young-save-product= : Savings product ID for Young savings (0 to skip column)}
        {--group-save-product= : Savings product ID for Group savings (0 to skip column)}
        {--fd-product= : Fixed deposit product ID (0 to skip column)}
        {--loan-product= : Loan product ID (0 to skip column)}
        {--opening-account=3002 : GL account code used to absorb the opening balance difference}
        {--include-save-int : Add the save_int column to the member\'s opt savings balance}
        {--skip-zero-clients : Skip rows where every financial field is zero}
        {--dry-run : Parse and validate without writing anything to the database}';

    protected $description = 'Import member opening balances from a 12-column CSV into ElTech Finance';

    public function handle(AccountingService $accounting): int
    {
        // ── Resolve CSV path ─────────────────────────────────────────────────
        $file = $this->argument('file');
        if (! file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $date      = $this->option('date');
        $branchId  = (int) $this->option('branch') ?: null;
        $dryRun    = (bool) $this->option('dry-run');
        $inclInt   = (bool) $this->option('include-save-int');
        $skipZero  = (bool) $this->option('skip-zero-clients');

        if ($dryRun) {
            $this->warn('DRY RUN — no data will be written.');
        }

        // ── Resolve savings / FD / loan products ─────────────────────────────
        $optSaveProd   = $this->resolveProduct('savings', 'opt-save-product',   'Opt/General Savings product (required)',  false);
        if (! $optSaveProd) {
            $this->error('Opt/General Savings product is required.');
            return self::FAILURE;
        }

        $youngSaveProd = $this->resolveProduct('savings', 'young-save-product', 'Young Savings product (0 to skip)',       true);
        $groupSaveProd = $this->resolveProduct('savings', 'group-save-product', 'Group Savings product (0 to skip)',       true);
        $fdProd        = $this->resolveProduct('fd',      'fd-product',         'Fixed Deposit product (0 to skip)',       true);
        $loanProd      = $this->resolveProduct('loan',    'loan-product',       'Loan product (0 to skip)',                true);

        // ── Resolve GL accounts ──────────────────────────────────────────────
        $openingAcct  = Account::where('account_code', $this->option('opening-account'))->first();
        $accrualAcct  = Account::where('account_code', '1302')->first();
        $shareCapAcct = Account::where('account_code', '3001')->first();
        $loanFallback = Account::where('account_code', '1101')->first();
        $fdFallback   = Account::where('account_code', '2002')->first();

        if (! $openingAcct || ! $accrualAcct || ! $shareCapAcct) {
            $this->error('One or more required GL accounts are missing (1302, 3001, or opening-account). Run db:seed first.');
            return self::FAILURE;
        }

        // ── Parse CSV ────────────────────────────────────────────────────────
        $rows = $this->parseCsv($file);
        if (empty($rows)) {
            $this->error('No data rows found in the CSV file.');
            return self::FAILURE;
        }

        $this->info('Loaded ' . count($rows) . ' row(s). Starting import...');
        $this->line('');

        // ── Import loop ──────────────────────────────────────────────────────
        $imported = 0;
        $skipped  = 0;
        $errors   = 0;
        $errorLog = [];

        DB::beginTransaction(); // outer — commit or rollback at end

        try {
            foreach ($rows as $idx => $row) {
                $lineNo   = $idx + 2; // row 2 is the first data row (row 1 = header)
                $memberId = trim($row['member_id'] ?? '');
                $name     = trim($row['name']      ?? '');
                $phone    = trim($row['phone']     ?? '') ?: null;

                if ($memberId === '' || $name === '') {
                    $errorLog[] = "Line {$lineNo}: missing member_id or name — skipped.";
                    $skipped++;
                    continue;
                }

                // Parse financial figures (strip commas, coerce to float)
                $sharesVal = max(0.0, $this->num($row['shares_val']   ?? 0));
                $optSave   = $this->num($row['opt_save']              ?? 0);
                $fdSave    = $this->num($row['fix_dep_save']          ?? 0);
                $loanVal   = $this->num($row['loan_value']            ?? 0);
                $loanInt   = max(0.0, $this->num($row['loan_int']     ?? 0));
                $youngSave = $this->num($row['young_save']            ?? 0);
                $groupSave = $this->num($row['group_save']            ?? 0);
                $saveInt   = max(0.0, $this->num($row['save_int']     ?? 0));

                // Negative savings balances cannot be imported
                if ($optSave < 0 || $youngSave < 0 || $groupSave < 0 || $fdSave < 0) {
                    $errorLog[] = "Line {$lineNo}: {$memberId} {$name} — negative savings value, skipped.";
                    $skipped++;
                    continue;
                }

                $optTotal  = $optSave + ($inclInt ? $saveInt : 0);
                $anyValue  = ($optTotal + $youngSave + $groupSave + $fdSave + $loanVal + $loanInt + $sharesVal) > 0.0;

                if (! $anyValue && $skipZero) {
                    $skipped++;
                    continue;
                }

                try {
                    DB::beginTransaction(); // per-member savepoint

                    // ── Create client ─────────────────────────────────────────
                    $client = Client::firstOrCreate(
                        ['client_number' => $memberId],
                        [
                            'name'         => $name,
                            'phone'        => $phone,
                            'branch_id'    => $branchId,
                            'status'       => 'active',
                            'joining_date' => $date,
                            'created_by'   => null,
                        ]
                    );

                    if (! $anyValue) {
                        // Client created, no financial records needed
                        DB::commit();
                        $imported++;
                        $this->line("  [CLIENT] {$memberId} — {$name} (no balances)");
                        continue;
                    }

                    // ── Build GL journal lines ────────────────────────────────
                    $lines       = [];
                    $totalDebit  = 0.0;
                    $totalCredit = 0.0;

                    // DR Loans Receivable
                    if ($loanVal > 0) {
                        $loanAcctId = $loanProd ? $loanProd->receivable_account_id : ($loanFallback ? $loanFallback->id : null);
                        if ($loanAcctId) {
                            $lines[]    = ['account_id' => $loanAcctId, 'debit' => $loanVal, 'credit' => 0, 'description' => "Opening loan — {$name}", 'client_id' => $client->id];
                            $totalDebit += $loanVal;
                        } else {
                            $errorLog[] = "Line {$lineNo}: {$memberId} — no loan GL account, loan balance skipped.";
                        }
                    }

                    // DR Accrued Income (1302) for outstanding loan interest
                    if ($loanInt > 0) {
                        $lines[]    = ['account_id' => $accrualAcct->id, 'debit' => $loanInt, 'credit' => 0, 'description' => "Opening accrued interest — {$name}", 'client_id' => $client->id];
                        $totalDebit += $loanInt;
                    }

                    // CR Opt/General Savings
                    if ($optTotal > 0) {
                        $lines[]     = ['account_id' => $optSaveProd->savings_liability_account_id, 'credit' => $optTotal, 'debit' => 0, 'description' => "Opening savings — {$name}", 'client_id' => $client->id];
                        $totalCredit += $optTotal;
                    }

                    // CR Young Savings
                    if ($youngSave > 0 && $youngSaveProd) {
                        $lines[]     = ['account_id' => $youngSaveProd->savings_liability_account_id, 'credit' => $youngSave, 'debit' => 0, 'description' => "Opening young savings — {$name}", 'client_id' => $client->id];
                        $totalCredit += $youngSave;
                    } elseif ($youngSave > 0 && ! $youngSaveProd) {
                        $errorLog[] = "Line {$lineNo}: {$memberId} — young_save {$youngSave} skipped (no product mapped).";
                    }

                    // CR Group Savings
                    if ($groupSave > 0 && $groupSaveProd) {
                        $lines[]     = ['account_id' => $groupSaveProd->savings_liability_account_id, 'credit' => $groupSave, 'debit' => 0, 'description' => "Opening group savings — {$name}", 'client_id' => $client->id];
                        $totalCredit += $groupSave;
                    } elseif ($groupSave > 0 && ! $groupSaveProd) {
                        $errorLog[] = "Line {$lineNo}: {$memberId} — group_save {$groupSave} skipped (no product mapped).";
                    }

                    // CR Fixed Deposit Liabilities
                    if ($fdSave > 0 && $fdProd) {
                        $lines[]     = ['account_id' => $fdProd->deposit_liability_account_id, 'credit' => $fdSave, 'debit' => 0, 'description' => "Opening FD — {$name}", 'client_id' => $client->id];
                        $totalCredit += $fdSave;
                    } elseif ($fdSave > 0 && ! $fdProd && $fdFallback) {
                        $lines[]     = ['account_id' => $fdFallback->id, 'credit' => $fdSave, 'debit' => 0, 'description' => "Opening FD — {$name}", 'client_id' => $client->id];
                        $totalCredit += $fdSave;
                        $errorLog[]  = "Line {$lineNo}: {$memberId} — no FD product mapped; posted to 2002 (no FD sub-ledger).";
                    }

                    // CR Share Capital (3001)
                    if ($sharesVal > 0) {
                        $lines[]     = ['account_id' => $shareCapAcct->id, 'credit' => $sharesVal, 'debit' => 0, 'description' => "Opening shares — {$name}", 'client_id' => $client->id];
                        $totalCredit += $sharesVal;
                    }

                    // Balancing line — DR or CR opening-account (default: Retained Earnings 3002)
                    $diff = round($totalCredit - $totalDebit, 4);
                    if (abs($diff) > 0.001) {
                        if ($diff > 0) {
                            $lines[] = ['account_id' => $openingAcct->id, 'debit' => $diff,       'credit' => 0,    'description' => "Opening balance — {$memberId}", 'client_id' => null];
                        } else {
                            $lines[] = ['account_id' => $openingAcct->id, 'debit' => 0,           'credit' => abs($diff), 'description' => "Opening balance — {$memberId}", 'client_id' => null];
                        }
                    }

                    if (count($lines) < 2) {
                        DB::rollBack();
                        $errorLog[] = "Line {$lineNo}: {$memberId} — could not build a balanced journal (all mapped amounts are zero).";
                        $skipped++;
                        continue;
                    }

                    // ── Post GL journal ───────────────────────────────────────
                    $txn = $accounting->post(
                        $date,
                        "Opening balance — {$memberId} {$name}",
                        $lines,
                        'opening_balance',
                        $client->id,
                        "OB-{$memberId}"
                    );

                    // ── Opt/General savings account + statement ───────────────
                    if ($optTotal > 0) {
                        $savAcct = SavingsAccount::firstOrCreate(
                            ['account_number' => "SA-{$memberId}-01"],
                            [
                                'client_id'   => $client->id,
                                'product_id'  => $optSaveProd->id,
                                'balance'     => 0,
                                'status'      => 'active',
                                'opened_date' => $date,
                                'created_by'  => null,
                            ]
                        );
                        if ($savAcct->wasRecentlyCreated || $savAcct->balance == 0) {
                            $savAcct->update(['balance' => $optTotal]);
                            SavingsTransaction::create([
                                'savings_account_id' => $savAcct->id,
                                'transaction_type'   => 'deposit',
                                'amount'             => $optTotal,
                                'balance_before'     => 0,
                                'balance_after'      => $optTotal,
                                'transaction_date'   => $date,
                                'reference'          => "OB-{$memberId}",
                                'description'        => 'Opening balance',
                                'transaction_id'     => $txn->id,
                                'created_by'         => null,
                            ]);
                        }
                    }

                    // ── Young savings account + statement ─────────────────────
                    if ($youngSave > 0 && $youngSaveProd) {
                        $ySavAcct = SavingsAccount::firstOrCreate(
                            ['account_number' => "YS-{$memberId}-02"],
                            [
                                'client_id'   => $client->id,
                                'product_id'  => $youngSaveProd->id,
                                'balance'     => 0,
                                'status'      => 'active',
                                'opened_date' => $date,
                                'created_by'  => null,
                            ]
                        );
                        if ($ySavAcct->wasRecentlyCreated || $ySavAcct->balance == 0) {
                            $ySavAcct->update(['balance' => $youngSave]);
                            SavingsTransaction::create([
                                'savings_account_id' => $ySavAcct->id,
                                'transaction_type'   => 'deposit',
                                'amount'             => $youngSave,
                                'balance_before'     => 0,
                                'balance_after'      => $youngSave,
                                'transaction_date'   => $date,
                                'reference'          => "OB-{$memberId}",
                                'description'        => 'Opening balance',
                                'transaction_id'     => $txn->id,
                                'created_by'         => null,
                            ]);
                        }
                    }

                    // ── Group savings account + statement ─────────────────────
                    if ($groupSave > 0 && $groupSaveProd) {
                        $gSavAcct = SavingsAccount::firstOrCreate(
                            ['account_number' => "GS-{$memberId}-03"],
                            [
                                'client_id'   => $client->id,
                                'product_id'  => $groupSaveProd->id,
                                'balance'     => 0,
                                'status'      => 'active',
                                'opened_date' => $date,
                                'created_by'  => null,
                            ]
                        );
                        if ($gSavAcct->wasRecentlyCreated || $gSavAcct->balance == 0) {
                            $gSavAcct->update(['balance' => $groupSave]);
                            SavingsTransaction::create([
                                'savings_account_id' => $gSavAcct->id,
                                'transaction_type'   => 'deposit',
                                'amount'             => $groupSave,
                                'balance_before'     => 0,
                                'balance_after'      => $groupSave,
                                'transaction_date'   => $date,
                                'reference'          => "OB-{$memberId}",
                                'description'        => 'Opening balance',
                                'transaction_id'     => $txn->id,
                                'created_by'         => null,
                            ]);
                        }
                    }

                    // ── Member shares + share transaction ─────────────────────
                    if ($sharesVal > 0) {
                        $share = MemberShare::firstOrCreate(
                            ['share_number' => "SH-{$memberId}-01"],
                            [
                                'client_id'   => $client->id,
                                'share_value' => $sharesVal,
                                'amount_paid' => $sharesVal,
                                'status'      => 'paid',
                                'notes'       => 'Opening balance import',
                                'created_by'  => null,
                            ]
                        );
                        if ($share->wasRecentlyCreated) {
                            ShareTransaction::create([
                                'share_id'               => $share->id,
                                'client_id'              => $client->id,
                                'type'                   => 'payment',
                                'amount'                 => $sharesVal,
                                'amount_paid_before'     => 0,
                                'transaction_date'       => $date,
                                'reference'              => "OB-{$memberId}",
                                'notes'                  => 'Opening balance import',
                                'journal_transaction_id' => $txn->id,
                                'created_by'             => null,
                            ]);
                        }
                    }

                    // ── Fixed deposit ─────────────────────────────────────────
                    if ($fdSave > 0 && $fdProd) {
                        $termMonths  = $fdProd->term_months ?: 12;
                        $iRate       = (float) $fdProd->interest_rate;
                        $intAmount   = round($fdSave * ($iRate / 100) * ($termMonths / 12), 2);
                        $maturityAmt = round($fdSave + $intAmount, 2);
                        $maturityDt  = date('Y-m-d', strtotime("+{$termMonths} months", strtotime($date)));

                        FixedDeposit::firstOrCreate(
                            ['deposit_number' => "FD-{$memberId}-01"],
                            [
                                'client_id'        => $client->id,
                                'product_id'       => $fdProd->id,
                                'principal'        => $fdSave,
                                'interest_rate'    => $iRate,
                                'term_months'      => $termMonths,
                                'start_date'       => $date,
                                'maturity_date'    => $maturityDt,
                                'interest_amount'  => $intAmount,
                                'maturity_amount'  => $maturityAmt,
                                'accrued_interest' => 0,
                                'status'           => 'active',
                                'created_by'       => null,
                            ]
                        );
                    }

                    // ── Loan ──────────────────────────────────────────────────
                    if ($loanVal > 0 && $loanProd) {
                        $termMonths = $loanProd->term_months ?: 12;
                        $maturityDt = date('Y-m-d', strtotime("+{$termMonths} months", strtotime($date)));

                        Loan::firstOrCreate(
                            ['loan_number' => "LN-{$memberId}-01"],
                            [
                                'client_id'             => $client->id,
                                'loan_product_id'       => $loanProd->id,
                                'principal'             => $loanVal,
                                'interest_rate'         => $loanProd->interest_rate,
                                'interest_method'       => $loanProd->interest_method,
                                'repayment_frequency'   => $loanProd->repayment_frequency,
                                'term_months'           => $termMonths,
                                'disbursement_date'     => $date,
                                'maturity_date'         => $maturityDt,
                                'outstanding_principal' => $loanVal,
                                'outstanding_interest'  => $loanInt,
                                'outstanding_penalty'   => 0,
                                'status'                => 'active',
                                'notes'                 => 'Opening balance import',
                                'created_by'            => null,
                            ]
                        );
                    }

                    DB::commit(); // release per-member savepoint
                    $imported++;

                    $this->line(sprintf(
                        '  [OK] %-12s %-32s  Loan:%s  Sav:%s  FD:%s  Sh:%s',
                        $memberId,
                        mb_substr($name, 0, 32),
                        number_format($loanVal + $loanInt),
                        number_format($optTotal + $youngSave + $groupSave),
                        number_format($fdSave),
                        number_format($sharesVal)
                    ));

                } catch (\Throwable $e) {
                    DB::rollBack(); // roll back to per-member savepoint
                    $errors++;
                    $errorLog[] = "Line {$lineNo}: {$memberId} {$name} — {$e->getMessage()}";
                    $this->error("  [ERROR] Line {$lineNo}: {$memberId} — {$e->getMessage()}");
                }
            } // end foreach

            if ($dryRun) {
                DB::rollBack();
                $this->line('');
                $this->warn('Dry run complete — all changes rolled back.');
            } else {
                DB::commit();
            }

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Fatal: ' . $e->getMessage());
            return self::FAILURE;
        }

        // ── Summary ──────────────────────────────────────────────────────────
        $this->line('');
        $this->info('Import ' . ($dryRun ? '(dry run) ' : '') . 'complete.');
        $this->table(
            ['Imported', 'Skipped', 'Errors'],
            [[$imported, $skipped, $errors]]
        );

        if (! empty($errorLog)) {
            $this->line('');
            $this->warn('Warnings / issues:');
            foreach ($errorLog as $msg) {
                $this->warn("  • {$msg}");
            }
        }

        return ($errors > 0) ? self::FAILURE : self::SUCCESS;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Parse a CSV file into an array of associative rows keyed by header. */
    private function parseCsv(string $path): array
    {
        $rows = [];
        if (($fh = fopen($path, 'r')) === false) {
            return [];
        }

        $headers = null;
        while (($line = fgetcsv($fh)) !== false) {
            if ($headers === null) {
                $headers = array_map('trim', array_map('strtolower', $line));
                continue;
            }
            if (count($line) < count($headers)) {
                continue;
            }
            $rows[] = array_combine($headers, array_slice($line, 0, count($headers)));
        }

        fclose($fh);
        return $rows;
    }

    /** Strip commas and cast to float. */
    private function num(mixed $val): float
    {
        return (float) str_replace([',', ' '], '', (string) $val);
    }

    /**
     * Resolve a product by CLI option or interactive choice.
     *
     * @param  string  $type       'savings' | 'fd' | 'loan'
     * @param  string  $optionKey  Artisan option name without leading --
     * @param  string  $label      Human-readable label for the interactive prompt
     * @param  bool    $allowSkip  Whether "0 to skip" is a valid choice
     */
    private function resolveProduct(string $type, string $optionKey, string $label, bool $allowSkip): ?object
    {
        $val = $this->option($optionKey);

        if ($val !== null) {
            if ($allowSkip && ((int) $val) === 0) {
                return null;
            }
            $product = $this->findProduct($type, (int) $val);
            if (! $product) {
                $this->warn("Product ID {$val} not found for option --{$optionKey}.");
            }
            return $product;
        }

        // Interactive
        $products = $this->listProducts($type);

        if ($products->isEmpty()) {
            $this->warn("No active {$type} products found in the database.");
            return null;
        }

        $choices = $products->map(fn ($p) => "{$p->id}: {$p->name}")->values()->toArray();

        if ($allowSkip) {
            array_unshift($choices, '0: Skip this column');
        }

        $choice = $this->choice($label, $choices, 0);
        $id     = (int) explode(':', $choice)[0];

        if ($id === 0) {
            return null;
        }

        return $this->findProduct($type, $id);
    }

    private function listProducts(string $type)
    {
        return match ($type) {
            'savings' => SavingsProduct::where('is_active', true)->get(['id', 'name']),
            'fd'      => FixedDepositProduct::where('is_active', true)->get(['id', 'name']),
            'loan'    => LoanProduct::where('is_active', true)->get(['id', 'name']),
            default   => collect(),
        };
    }

    private function findProduct(string $type, int $id): ?object
    {
        return match ($type) {
            'savings' => SavingsProduct::find($id),
            'fd'      => FixedDepositProduct::find($id),
            'loan'    => LoanProduct::find($id),
            default   => null,
        };
    }
}
