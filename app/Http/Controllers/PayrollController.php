<?php
namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\SavingsAccount;
use App\Models\PayrollRun;
use App\Models\SavingsTransaction;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller {
    public function __construct(protected AccountingService $accounting) {}

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
            'items.*.deductions'     => 'nullable|numeric|min:0',
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
            $deduct       = (float) ($item['deductions'] ?? 0);

            // PAYE / NSSF default to the statutory calculation on gross, but can be
            // overridden per employee (e.g. someone not charged PAYE or NSSF).
            // A blank cell means "use the calculated amount".
            $paye         = $this->amountOrDefault($item['paye'] ?? null, PayrollItem::calculatePaye($gross));
            $nssfEmployee = $this->amountOrDefault($item['nssf_employee'] ?? null, PayrollItem::calculateNssfEmployee($gross));
            $nssfEmployer = $this->amountOrDefault($item['nssf_employer'] ?? null, PayrollItem::calculateNssfEmployer($gross));

            // Lunch and staff savings are deducted from net; transport is paid on top (not taxed).
            $net       = $gross - $paye - $nssfEmployee - $deduct - $lunch - $staffSavings + $transport;
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
                'deductions'         => $deduct,
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
        return view('payroll.show', compact('payroll'));
    }

    public function process(Request $request, PayrollRun $payroll) {
        if ($payroll->status === 'processed') {
            return back()->with('error', 'This payroll run has already been processed.');
        }

        $request->validate(['payment_date' => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()]]);
        $paymentDate = $request->payment_date;

        $payroll->load('items.employee.client', 'items.savingsAccount.product');

        // Each pay type debits its own expense account: Salary -> 5003, Agency Commission -> 5103.
        $expenseAccountIds = [];
        foreach ($payroll->items->pluck('pay_type')->map(fn ($t) => $t ?: 'salary')->unique() as $payType) {
            $code = Employee::PAY_TYPE_EXPENSE_ACCOUNTS[$payType] ?? Employee::PAY_TYPE_EXPENSE_ACCOUNTS['salary'];
            $accountId = Account::where('account_code', $code)->value('id');
            if (!$accountId) {
                $label = Employee::payTypeLabel($payType);
                throw ValidationException::withMessages([
                    'payment_date' => "Chart of accounts is missing the {$label} expense account (code {$code}). Add it under Chart of Accounts.",
                ]);
            }
            $expenseAccountIds[$payType] = $accountId;
        }

        foreach ($payroll->items as $item) {
            if ($item->net_salary <= 0) {
                continue;
            }
            if (!$item->savings_account_id) {
                $name = $item->employee?->name ?? 'Employee #' . $item->employee_id;
                throw ValidationException::withMessages([
                    'payment_date' => "Cannot process: {$name} has no payroll savings account linked. Edit the employee and assign an active savings account.",
                ]);
            }
            $acc = $item->savingsAccount;
            if (!$acc || $acc->status !== 'active') {
                $name = $item->employee?->name ?? 'Employee #' . $item->employee_id;
                throw ValidationException::withMessages([
                    'payment_date' => "Cannot process: {$name}'s linked savings account is missing or not active.",
                ]);
            }
            $product = $acc->product;
            if (!$product || !$product->savings_liability_account_id) {
                $pn = $product?->name ?? '(missing product)';
                throw ValidationException::withMessages([
                    'payment_date' => "Cannot process: savings product “{$pn}” has no liability GL account. Configure it under Savings Products.",
                ]);
            }
        }

        DB::transaction(function () use ($payroll, $paymentDate, $expenseAccountIds) {
            $totalNet    = 0;
            $debitLines  = [];
            $creditLines = [];

            // 1) Totals + journal lines (no sub-ledger writes yet)
            foreach ($payroll->items as $item) {
                if (!$item->savings_account_id || $item->net_salary <= 0) {
                    continue;
                }

                $savingsProduct = $item->savingsAccount->product;
                $totalNet += $item->net_salary;

                $payType = $item->pay_type ?: 'salary';
                $debitLines[$payType] = ($debitLines[$payType] ?? 0) + $item->net_salary;

                $liabilityAccId = $savingsProduct->savings_liability_account_id;
                if (!isset($creditLines[$liabilityAccId])) {
                    $creditLines[$liabilityAccId] = 0;
                }
                $creditLines[$liabilityAccId] += $item->net_salary;
            }

            // 2) Post GL first so we have transaction.id for savings_transactions.transaction_id
            $journalTx = null;
            if ($totalNet > 0) {
                $journalLines = [];
                foreach ($debitLines as $payType => $amount) {
                    $journalLines[] = [
                        'account_id'  => $expenseAccountIds[$payType],
                        'debit'       => $amount,
                        'credit'      => 0,
                        'description' => Employee::payTypeLabel($payType) . " expense — {$payroll->run_number}",
                    ];
                }
                foreach ($creditLines as $accountId => $amount) {
                    $journalLines[] = [
                        'account_id'  => $accountId,
                        'debit'       => 0,
                        'credit'      => $amount,
                        'description' => "Pay credited to savings — {$payroll->run_number}",
                    ];
                }

                $journalTx = $this->accounting->post(
                    $paymentDate,
                    "Payroll: {$payroll->run_number} — {$payroll->period_label}",
                    $journalLines,
                    'payroll',
                    $payroll->id
                );
            }

            // 3) Credit savings + statement lines linked to the journal (enables reversal)
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

            $payroll->update([
                'status'       => 'processed',
                'processed_by' => auth()->id(),
                'processed_at' => now(),
                'total_gross'  => $totalNet,
            ]);
        });

        return redirect()->route('payroll.show', $payroll)->with('success', 'Payroll processed. Salaries credited to employee savings accounts.');
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
