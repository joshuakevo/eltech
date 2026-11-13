<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\MemberClosure;
use App\Models\SystemSetting;
use App\Services\MemberClosureService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** "Close Account" / "Reopen account" on the client page (permission: withdraw savings) and its defaults (manage settings). */
class MemberClosureController extends Controller
{
    public function __construct(protected MemberClosureService $service) {}

    public function store(Request $request, Client $client)
    {
        $data = $request->validate([
            'closure_date'      => 'required|date|before_or_equal:today',
            'closure_fee'       => 'required|numeric|min:0',
            'payout_account_id' => 'nullable|integer',
            'reason'            => 'nullable|string|max:500',
        ]);
        try {
            $closure = $this->service->close($client, $data);
        } catch (ValidationException $e) {
            return redirect()->route('clients.show', $client)->with('error', collect($e->errors())->flatten()->implode(' '));
        }

        return redirect()->route('clients.show', $client)->with('success', 'Account closed: closure fee ' . number_format($closure->closure_fee)
            . ', paid out ' . number_format($closure->payout) . ($closure->written_off > 0 ? ', overdrawn ' . number_format($closure->written_off) . ' written off' : '') . '.');
    }

    public function reopen(Client $client, MemberClosure $closure)
    {
        abort_unless((int) $closure->client_id === (int) $client->id, 404);
        try {
            $this->service->reopen($closure);
        } catch (ValidationException $e) {
            return redirect()->route('clients.show', $client)->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('clients.show', $client)->with('success', 'Account reopened: the closure was reversed and the savings balances restored.');
    }

    public function settings()
    {
        return view('settings.account-closure', [
            'closureFee'      => $this->service->defaultClosureFee(),
            'closureAccount'  => SystemSetting::get('account_closure_fee_account', '4104'),
            'writeOffAccount' => SystemSetting::get('overdrawn_writeoff_account', '5122'),
            'incomeAccounts'  => Account::where('is_active', true)->whereIn('account_type', ['revenue', 'income'])->orderBy('account_code')->get(['account_code', 'account_name']),
            'expenseAccounts' => Account::where('is_active', true)->where('account_type', 'expense')->orderBy('account_code')->get(['account_code', 'account_name']),
            'closures'        => MemberClosure::with('client', 'createdBy')->latest('id')->limit(30)->get(),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'account_closure_fee'         => 'required|numeric|min:0',
            'account_closure_fee_account' => 'required|exists:accounts,account_code',
            'overdrawn_writeoff_account'  => 'required|exists:accounts,account_code',
        ]);
        $data['account_closure_fee'] = round((float) $data['account_closure_fee']);
        foreach ($data as $key => $value) {
            SystemSetting::set($key, (string) $value);
        }
        AuditLog::record('account_closure_settings', 'Account closure: fee ' . number_format($data['account_closure_fee']) . " → {$data['account_closure_fee_account']}, overdrawn write-off → {$data['overdrawn_writeoff_account']}", 'settings');

        return redirect()->route('settings.account-closure')->with('success', 'Account closure settings saved.');
    }
}
