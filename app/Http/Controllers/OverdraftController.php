<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\OverdraftCharge;
use App\Models\SystemSetting;
use App\Services\OverdraftService;
use Illuminate\Http\Request;

/** Settings → Overdraft Charges: rate, minimum, accounts, and the charges / income so far (permission: manage settings). */
class OverdraftController extends Controller
{
    public function index(Request $request, OverdraftService $service)
    {
        $charges = OverdraftCharge::with('savingsAccount.client', 'createdBy')
            ->when($request->status === 'unpaid', fn ($q) => $q->whereColumn('recognised_amount', '<', 'amount'))
            ->latest('charge_date')->latest('id')->paginate(50)->withQueryString();

        $totals = [
            'charged'    => (float) OverdraftCharge::sum('amount'),
            'recognised' => (float) OverdraftCharge::sum('recognised_amount'),
        ];
        $accounts = Account::where('is_active', true)->whereIn('account_type', ['liability', 'revenue', 'income', 'asset'])->orderBy('account_code')->get(['account_code', 'account_name', 'account_type']);

        return view('settings.overdraft', [
            'charges' => $charges, 'totals' => $totals, 'accounts' => $accounts,
            'rate' => $service->rate(), 'minimum' => $service->minimum(),
            'accruedCode' => SystemSetting::get('overdraft_accrued_account', ''),
            'incomeCode' => SystemSetting::get('overdraft_income_account', '4111'),
            'accruedOk' => (bool) $service->accruedAccountId(), 'incomeOk' => (bool) $service->incomeAccountId(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'overdraft_charge_rate'     => 'required|numeric|min:0|max:100',
            'overdraft_charge_minimum'  => 'required|numeric|min:0',
            'overdraft_accrued_account' => 'required|exists:accounts,account_code',
            'overdraft_income_account'  => 'required|exists:accounts,account_code|different:overdraft_accrued_account',
        ]);
        $before = collect(array_keys($data))->mapWithKeys(fn ($k) => [$k => SystemSetting::get($k)])->all();
        foreach ($data as $key => $value) {
            SystemSetting::set($key, (string) $value);
        }
        AuditLog::record('overdraft_settings', 'Overdraft charge settings: rate ' . $before['overdraft_charge_rate'] . '% → ' . $data['overdraft_charge_rate']
            . '%, minimum ' . $before['overdraft_charge_minimum'] . ' → ' . $data['overdraft_charge_minimum']
            . ", accrued {$data['overdraft_accrued_account']}, income {$data['overdraft_income_account']}", 'settings');

        return redirect()->route('settings.overdraft')->with('success', 'Overdraft charge settings saved. New withdrawals use them straight away.');
    }
}
