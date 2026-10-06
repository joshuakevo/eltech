<?php
namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\SavingsAccount;
use App\Models\PayrollRun;
use App\Models\SavingsTransaction;
use App\Models\Transaction;
use App\Models\SystemSetting;
use App\Services\AccountingService;
use App\Services\SavingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller {
    public function __construct(protected AccountingService $accounting, protected SavingsService $savingsService) {}

    public function index() {
        $runs = PayrollRun::withCount('items')->latest()->paginate(20);
        return view('payroll.index', compact('runs'));
    }

    public function create() {
        $employees = Employee::with('client', 'savingsAccount.product')->where('status', 'active')->get();
        return view('payroll.create', compact('employees'));
    }

    public function store(Request $request) {
        $this->validateRun($request);

        // Prevent duplicate employees before creating anything
        if ($this->hasDuplicateEmployees($request)) {
            return back()->withErrors(['items' => 'Duplicate employees detected. Each employee can only appear once per payroll run.'])->withInput();
        }

        $run = DB::transaction(function () use ($request) {
            $run = PayrollRun::create([
                'run_number'    => $this->generateRunNumber(),
                'period_month'  => $request->period_month,
                'period_year'   => $request->period_year,
                'description'   => $request->description,
                'total_gross'   => 0,
                'status'        => 'draft',
                'created_by'    => auth()->id(),
            ]);
            $this->saveItems($run, $request->items);
            return $run;
        });

        return redirect()->route('payroll.show', $run)->with('success', 'Payroll run created. Review and process when ready.');
    }

    public function edit(PayrollRun $payroll) {
        if ($payroll->status !== 'draft') {
            return redirect()->route('payroll.show', $payroll)->with('error', 'Only draft payroll runs can be edited.');
        }
        $payroll->load('items');
        $employees = Employee::with('client', 'savingsAccount.product')
            ->where('status', 'active')
            ->orWhereIn('id', $payroll->items->pluck('employee_id'))
            ->get();
        return view('payroll.create', compact('employees', 'payroll'));
    }

    public function update(Request $request, PayrollRun $payroll) {
        if ($payroll->status !== 'draft') {
            return redirect()->route('payroll.show', $payroll)->with('error', 'Only draft payroll runs can be edited.');
        }
        $this->validateRun($request);

        if ($this->hasDuplicateEmployees($request)) {
            return back()->withErrors(['items' => 'Duplicate employees detected. Each employee can only appear once per payroll run.'])->withInput();
        }

        DB::transaction(function () use ($request, $payroll) {
            $payroll->update([
                'period_month' => $request->period_month,
                'period_year'  => $request->period_year,
                'description'  => $request->description,
            ]);
            $payroll->items()->delete();
            $this->saveItems($payroll, $request->items);
        });

        return redirect()->route('payroll.show', $payroll)->with('success', 'Payroll run updated.');
    }

    private function validateRun(Request $request): void {
        $request->validate([
            'period_month'  => 'required|integer|min:1|max:12',
            'period_year'   => 'required|integer|min:2000|max:2100',
            'description'   => 'nullable|string|max:200',
            'items'         => 'required|array|min:1',
            'items.*.employee_id'    => 'required|exists:employees,id',
            'items.*.basic_salary'   => 'required|numeric|min:0',
            'items.*.paye'           => 'nullable|numeric|min:0',
            'items.*.nssf_employee'  => 'nullable|numeric|min:0',
            'items.*.nssf_employer'  => 'nullable|numeric|min:0',
            'items.*.lunch'          => 'nullable|numeric|min:0',
            'items.*.transport'      => 'nullable|numeric|min:0',
            'items.*.staff_savings'  => 'nullable|numeric|min:0',
        ]);
    }

    private function hasDuplicateEmployees(Request $request): bool {
        $employeeIds = array_column($request->items, 'employee_id');
        return count($employeeIds) !== count(array_unique($employeeIds));
    }

    private function saveItems(PayrollRun $run, array $items): void {
        $totalNet = 0;
        foreach ($items as $item) {
            $employee  = Employee::findOrFail($item['employee_id']);
            // basic_salary holds the employee's Gross Pay (allowances are no longer used).
            $gross        = (float) $item['basic_salary'];
            $lunch        = (float) ($item['lunch'] ?? 0);
            $transport    = (float) ($item['transport'] ?? 0);
            // Blank staff savings means the default (50,000); enter 0 for someone who doesn't save.
            $staffSavings = $this->amountOrDefault($item['staff_savings'] ?? null, PayrollItem::DEFAULT_STAFF_SAVINGS);

            // PAYE / NSSF default to the statutory calculation on gross, but can be
            // overridden per employee (e.g. someone not charged PAYE or NSSF).
            // A blank cell means "use the calculated amount".
            $paye         = $this->amountOrDefault($item['paye'] ?? null, PayrollItem::calculatePaye($gross));
            $nssfEmployee = $this->amountOrDefault($item['nssf_employee'] ?? null, PayrollItem::calculateNssfEmployee($gross));
            $nssfEmployer = $this->amountOrDefault($item['nssf_employer'] ?? null, PayrollItem::calculateNssfEmployer($gross));

            // Lunch and staff savings are deducted from net; transport is paid on top (not taxed).
            $net       = $gross - $paye - $nssfEmployee - $lunch - $staffSavings + $transport;
            $totalNet += $net;

            PayrollItem::create([
                'payroll_run_id'     => $run->id,
                'employee_id'        => $employee->id,
                'savings_account_id' => $employee->savings_account_id,
                'pay_type'           => $employee->pay_type ?? 'salary',
                'basic_salary'       => $gross,
                'allowances'         => 0,
                'paye'               => $paye,
                'nssf_employee'      => $nssfEmployee,
                'nssf_employer'      => $nssfEmployer,
                'lunch'              => $lunch,
                'transport'          => $transport,
                'staff_savings'      => $staffSavings,
                'deductions'         => 0,
                'net_salary'         => $net,
            ]);
        }

        $run->update(['total_gross' => $totalNet]);
    }

    private function amountOrDefault($value, float $default): float {
        return ($value === null || $value === '') ? $default : round((float) $value, 2);
    }

    public function show(PayrollRun $payroll) {
        $payroll->load('items.employee.client', 'items.savingsAccount.product', 'processedBy');

        $journalPreview = null;
        $postedJournal  = null;
        if ($payroll->status === 'draft') {
            $journalPreview = $this->buildJournal($payroll);
        } else {
            $postedJournal = Transaction::with('lines.account')
                ->where('module', 'payroll')
                ->where('module_id', $payroll->id)
                ->whereNull('reversal_of')
                ->whereNull('reversed_by')
                ->latest('id')
                ->first();
        }

        return view('payroll.show', compact('payroll', 'journalPreview', 'postedJournal'));
    }

    /**
     * Builds the double-entry journal processing this run will post, per employee then summed:
     *   DR 5003 Staff Salaries / 5103 Agency Commissions   gross pay (by pay type)
     *   DR 5104 NSSF Expense (10%)                         employer NSSF 10%
     *   DR 5110 Local Travel                               transport
     *   CR 2011 NSSF Liability                             NSSF 5% + 10%
     *   CR 2012 PAYE Liability                             PAYE
     *   CR 4007 Other Income                               lunch
     *   CR Staff Saving Account's savings liability         staff savings (deposited to that savings account)
     *   CR savings liability (employee's savings product)  net pay
     * Used for both the pre-process preview and the actual posting, so they always agree.
     *
     * @return array{lines: array, total_debit: float, total_credit: float, total_net: float, staff_savings_account: ?SavingsAccount, issues: string[]}
     */
    private function buildJournal(PayrollRun $payroll): array {
        $issues     = [];
        $byCode     = [];   // account code => ['debit' => x, 'credit' => y, 'description' => ...]
        $byAccount  = [];   // savings liability account id => net credited
        $totalNet   = 0;
        $totalStaffSavings = 0;

        $add = function (string $code, float $debit, float $credit, string $description) use (&$byCode) {
            if (round($debit, 2) == 0 && round($credit, 2) == 0) {
                return;
            }
            $byCode[$code] ??= ['debit' => 0, 'credit' => 0, 'description' => $description];
            $byCode[$code]['debit']  += $debit;
            $byCode[$code]['credit'] += $credit;
        };

        foreach ($payroll->items as $item) {
            $name = $item->employee?->name ?? 'Employee #' . $item->employee_id;

            // Runs saved before the Deductions column was removed have no account to post it to.
            if ($item->deductions > 0) {
                $issues[] = "{$name} has an old \"Deductions\" amount (" . number_format($item->deductions, 0) . "), which is no longer used. Edit the run and save it again.";
                continue;
            }

            $net = (float) $item->net_salary;
            if ($net < 0) {
                $issues[] = "{$name}'s net pay is negative (" . number_format($net, 0) . "). Reduce their deductions.";
                continue;
            }
            if ($net > 0) {
                $acc = $item->savingsAccount;
                if (!$item->savings_account_id) {
                    $issues[] = "{$name} has no payroll savings account linked. Edit the employee and assign an active savings account.";
                    continue;
                }
                if (!$acc || $acc->status !== 'active') {
                    $issues[] = "{$name}'s linked savings account is missing or not active.";
                    continue;
                }
                $liabilityAccId = $acc->product?->savings_liability_account_id;
                if (!$liabilityAccId) {
                    $pn = $acc->product?->name ?? '(missing product)';
                    $issues[] = "Savings product “{$pn}” has no liability GL account. Configure it under Savings Products.";
                    continue;
                }
                $byAccount[$liabilityAccId] = ($byAccount[$liabilityAccId] ?? 0) + $net;
                $totalNet += $net;
            }

            $payType     = $item->pay_type ?: 'salary';
            $expenseCode = Employee::PAY_TYPE_EXPENSE_ACCOUNTS[$payType] ?? Employee::PAY_TYPE_EXPENSE_ACCOUNTS['salary'];
            $gross       = (float) $item->basic_salary + (float) $item->allowances;

            $add($expenseCode, $gross, 0, Employee::payTypeLabel($payType) . " — gross pay");
            $add(PayrollItem::GL_NSSF_EXPENSE, (float) $item->nssf_employer, 0, 'Employer NSSF 10%');
            $add(PayrollItem::GL_TRANSPORT, (float) $item->transport, 0, 'Staff transport');
            $add(PayrollItem::GL_NSSF_LIABILITY, 0, (float) $item->nssf_employee + (float) $item->nssf_employer, 'NSSF 5% employee + 10% employer');
            $add(PayrollItem::GL_PAYE_LIABILITY, 0, (float) $item->paye, 'PAYE deducted');
            $add(PayrollItem::GL_LUNCH_INCOME, 0, (float) $item->lunch, 'Lunch deducted');
            $totalStaffSavings += (float) $item->staff_savings;
        }

        // Staff savings go into one designated savings account (Settings → Financial).
        $staffAccount = null;
        if (round($totalStaffSavings, 2) > 0) {
            $number       = trim((string) SystemSetting::get(PayrollItem::STAFF_SAVINGS_SETTING, ''));
            $staffAccount = $number !== '' ? SavingsAccount::with('product', 'client')->where('account_number', $number)->first() : null;
            if (!$staffAccount) {
                $issues[] = $number === ''
                    ? 'No Staff Savings account is set. Set "Payroll — Staff Savings account number" under Settings → Financial.'
                    : "Staff Savings account {$number} was not found. Check \"Payroll — Staff Savings account number\" under Settings → Financial.";
            } elseif ($staffAccount->status !== 'active') {
                $issues[] = "Staff Savings account {$number} is not active.";
                $staffAccount = null;
            } elseif (!$staffAccount->product?->savings_liability_account_id) {
                $issues[] = "Staff Savings account {$number}'s savings product has no liability GL account. Configure it under Savings Products.";
                $staffAccount = null;
            }
        }

        ksort($byCode);
        $accounts = Account::whereIn('account_code', array_keys($byCode))->get()->keyBy('account_code');
        $lines = [];
        // Debits first, then credits -- reads like a normal journal.
        foreach ([true, false] as $debitSide) {
            foreach ($byCode as $code => $row) {
                if (($row['debit'] > 0) !== $debitSide) {
                    continue;
                }
                $account = $accounts->get((string) $code);
                if (!$account) {
                    $issues[] = "Chart of accounts is missing account {$code} (needed for {$row['description']}). Add it under Chart of Accounts, or run migrations.";
                    continue;
                }
                $lines[] = [
                    'account_id'  => $account->id,
                    'account'     => $account,
                    'debit'       => round($row['debit'], 2),
                    'credit'      => round($row['credit'], 2),
                    'description' => "{$row['description']} — {$payroll->run_number}",
                ];
            }
        }

        $savingsAccounts = Account::whereIn('id', array_keys($byAccount))->get()->keyBy('id');
        foreach ($byAccount as $accountId => $amount) {
            $lines[] = [
                'account_id'  => $accountId,
                'account'     => $savingsAccounts->get($accountId),
                'debit'       => 0,
                'credit'      => round($amount, 2),
                'description' => "Net pay credited to savings — {$payroll->run_number}",
            ];
        }

        if ($staffAccount) {
            $lines[] = [
                'account_id'  => $staffAccount->product->savings_liability_account_id,
                'account'     => Account::find($staffAccount->product->savings_liability_account_id),
                'debit'       => 0,
                'credit'      => round($totalStaffSavings, 2),
                'description' => "Staff savings to {$staffAccount->account_number}" . ($staffAccount->client ? " ({$staffAccount->client->name})" : '') . " — {$payroll->run_number}",
            ];
        }

        $totalDebit  = round(array_sum(array_column($lines, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($lines, 'credit')), 2);
        if (!$issues && abs($totalDebit - $totalCredit) > 0.01) {
            $issues[] = 'Journal does not balance (debits ' . number_format($totalDebit, 2) . ' vs credits ' . number_format($totalCredit, 2) . '). Edit the run and save it again to recalculate net pay.';
        }

        return [
            'lines'        => $lines,
            'total_debit'  => $totalDebit,
            'total_credit' => $totalCredit,
            'total_net'    => round($totalNet, 2),
            'staff_savings_account' => $staffAccount,
            'issues'       => array_values(array_unique($issues)),
        ];
    }

    public function process(Request $request, PayrollRun $payroll) {
        if ($payroll->status === 'processed') {
            return back()->with('error', 'This payroll run has already been processed.');
        }

        $request->validate(['payment_date' => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()]]);
        $paymentDate = $request->payment_date;

        $payroll->load('items.employee.client', 'items.savingsAccount.product');

        $journal = $this->buildJournal($payroll);
        if ($journal['issues']) {
            throw ValidationException::withMessages(['payment_date' => 'Cannot process: ' . $journal['issues'][0]]);
        }

        $journalTx = null;
        DB::transaction(function () use ($payroll, $paymentDate, $journal, &$journalTx) {
            $totalNet = $journal['total_net'];

            // 1) Post GL first so we have transaction.id for savings_transactions.transaction_id
            if ($journal['total_debit'] > 0) {
                $journalLines = array_map(fn ($l) => [
                    'account_id'  => $l['account_id'],
                    'debit'       => $l['debit'],
                    'credit'      => $l['credit'],
                    'description' => $l['description'],
                ], $journal['lines']);

                $journalTx = $this->accounting->post(
                    $paymentDate,
                    "Payroll: {$payroll->run_number} — {$payroll->period_label}",
                    $journalLines,
                    'payroll',
                    $payroll->id
                );
            }

            // 2) Credit savings + statement lines linked to the journal (enables reversal)
            foreach ($payroll->items as $item) {
                if (!$item->savings_account_id || $item->net_salary <= 0) {
                    continue;
                }

                $savingsAccount = SavingsAccount::query()->whereKey($item->savings_account_id)->lockForUpdate()->first();
                if (!$savingsAccount) {
                    continue;
                }

                $balBefore = (float) $savingsAccount->balance;
                $balAfter  = $balBefore + $item->net_salary;

                $savingsAccount->update(['balance' => $balAfter]);

                SavingsTransaction::create([
                    'savings_account_id' => $savingsAccount->id,
                    'transaction_type'   => 'deposit',
                    'amount'             => $item->net_salary,
                    'balance_before'     => $balBefore,
                    'balance_after'      => $balAfter,
                    'transaction_date'   => $paymentDate,
                    'reference'          => $journalTx?->reference,
                    'description'        => "Salary — {$payroll->run_number} ({$payroll->period_label})",
                    'transaction_id'     => $journalTx?->id,
                    'created_by'         => auth()->id(),
                ]);
            }

            // 3) Staff savings: one deposit per employee on the Staff Savings account, linked to the journal
            if ($journal['staff_savings_account']) {
                $staffAccount = SavingsAccount::query()->whereKey($journal['staff_savings_account']->id)->lockForUpdate()->first();
                foreach ($payroll->items as $item) {
                    if ($item->staff_savings <= 0) {
                        continue;
                    }
                    $balBefore = (float) $staffAccount->balance;
                    $balAfter  = $balBefore + $item->staff_savings;
                    $staffAccount->update(['balance' => $balAfter]);

                    $name = $item->employee?->name ?? 'Employee #' . $item->employee_id;
                    SavingsTransaction::create([
                        'savings_account_id' => $staffAccount->id,
                        'transaction_type'   => 'deposit',
                        'amount'             => $item->staff_savings,
                        'balance_before'     => $balBefore,
                        'balance_after'      => $balAfter,
                        'transaction_date'   => $paymentDate,
                        'reference'          => $journalTx?->reference,
                        'description'        => "Staff savings — {$name} — {$payroll->run_number} ({$payroll->period_label})",
                        'transaction_id'     => $journalTx?->id,
                        'created_by'         => auth()->id(),
                    ]);
                }
            }

            // 4) The payment date is often back-dated (e.g. month end), so rebuild running balances
            //    in date order -- otherwise later withdrawals show balances that exclude this pay.
            $touched = $payroll->items->where('net_salary', '>', 0)->pluck('savings_account_id')->filter();
            if ($journal['staff_savings_account']) {
                $touched->push($journal['staff_savings_account']->id);
            }
            foreach (SavingsAccount::whereIn('id', $touched->unique())->get() as $account) {
                $this->savingsService->recalculateLedger($account);
            }

            $payroll->update([
                'status'       => 'processed',
                'processed_by' => auth()->id(),
                'processed_at' => now(),
                'total_gross'  => $totalNet,
            ]);
        });

        return redirect()->route('payroll.show', $payroll)->with('success',
            'Payroll processed' . ($journalTx ? " — journal {$journalTx->reference}" : '')
            . '. ' . number_format($journal['total_net'], 0) . ' net pay credited to employee savings accounts. See the journal breakdown below.');
    }

    public function destroy(PayrollRun $payroll) {
        if ($payroll->status !== 'draft') {
            return back()->with('error', 'Only draft payroll runs can be deleted.');
        }
        $payroll->items()->delete();
        $payroll->delete();
        return redirect()->route('payroll.index')->with('success', 'Draft payroll run deleted.');
    }

    private function generateRunNumber(): string {
        $year   = now()->format('Y');
        $month  = now()->format('m');
        $prefix = 'PAY-' . $year . $month . '-';
        $max = \DB::table('payroll_runs')
            ->where('run_number', 'like', $prefix . '%')
            ->max('run_number');
        $next = $max ? ((int) substr($max, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
    }
}
