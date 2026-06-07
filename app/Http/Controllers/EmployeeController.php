<?php
namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Employee;
use App\Models\SavingsAccount;
use Illuminate\Http\Request;

class EmployeeController extends Controller {
    public function index() {
        $employees = Employee::with('client', 'savingsAccount')->latest()->paginate(25);
        return view('employees.index', compact('employees'));
    }

    public function create() {
        // Only clients who have at least one active savings account
        $clients = Client::where('status', 'active')
            ->whereHas('savingsAccounts', fn($q) => $q->where('status', 'active'))
            ->orderBy('name')
            ->get();

        // All active savings accounts grouped by client_id for JS filtering
        $savingsAccounts = SavingsAccount::with('product')
            ->where('status', 'active')
            ->get()
            ->groupBy('client_id');

        return view('employees.create', compact('clients', 'savingsAccounts'));
    }

    public function store(Request $request) {
        $data = $request->validate([
            'client_id'          => 'required|exists:clients,id',
            'position'           => 'nullable|string|max:100',
            'department'         => 'nullable|string|max:100',
            'basic_salary'       => 'required|numeric|min:0',
            'savings_account_id' => 'required|exists:savings_accounts,id',
            'status'             => 'required|in:active,inactive',
            'notes'              => 'nullable|string',
        ]);

        // Ensure savings account belongs to the selected client
        $sa = SavingsAccount::where('id', $data['savings_account_id'])
            ->where('client_id', $data['client_id'])->first();
        if (!$sa) {
            return back()->withErrors(['savings_account_id' => 'The savings account does not belong to the selected client.'])->withInput();
        }

        // Prevent duplicate employee for same client
        if (Employee::where('client_id', $data['client_id'])->exists()) {
            return back()->withErrors(['client_id' => 'This client is already registered as an employee.'])->withInput();
        }

        $data['employee_number'] = $this->generateEmployeeNumber();
        $data['created_by']      = auth()->id();
        Employee::create($data);
        return redirect()->route('employees.index')->with('success', 'Employee created successfully.');
    }

    public function show(Employee $employee) {
        $employee->load('client', 'savingsAccount.product', 'payrollItems.payrollRun');
        return view('employees.show', compact('employee'));
    }

    public function edit(Employee $employee) {
        $savingsAccounts = SavingsAccount::with('product')
            ->where('client_id', $employee->client_id)
            ->where('status', 'active')
            ->get();
        return view('employees.edit', compact('employee', 'savingsAccounts'));
    }

    public function update(Request $request, Employee $employee) {
        $data = $request->validate([
            'position'           => 'nullable|string|max:100',
            'department'         => 'nullable|string|max:100',
            'basic_salary'       => 'required|numeric|min:0',
            'savings_account_id' => 'required|exists:savings_accounts,id',
            'status'             => 'required|in:active,inactive',
            'notes'              => 'nullable|string',
        ]);

        $sa = SavingsAccount::where('id', $data['savings_account_id'])
            ->where('client_id', $employee->client_id)->first();
        if (!$sa) {
            return back()->withErrors(['savings_account_id' => 'The savings account does not belong to this employee\'s client.'])->withInput();
        }

        $employee->update($data);
        return redirect()->route('employees.show', $employee)->with('success', 'Employee updated.');
    }

    private function generateEmployeeNumber(): string {
        $last = Employee::withTrashed()->count() + 1;
        return 'EMP-' . str_pad($last, 5, '0', STR_PAD_LEFT);
    }
}
