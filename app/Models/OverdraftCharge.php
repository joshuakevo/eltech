<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An overdraft charge added to a member's negative savings balance (OverdraftService). */
class OverdraftCharge extends Model
{
    protected $fillable = [
        'overdraft_run_id', 'savings_account_id', 'client_id', 'charge_date', 'overdrawn_balance', 'rate', 'amount', 'recognised_amount',
        'withdrawal_savings_transaction_id', 'savings_transaction_id', 'transaction_id', 'created_by',
    ];

    protected $casts = [
        'charge_date' => 'date', 'overdrawn_balance' => 'float', 'rate' => 'float', 'amount' => 'float', 'recognised_amount' => 'float',
    ];

    public function savingsAccount()
    {
        return $this->belongsTo(SavingsAccount::class);
    }

    public function run()
    {
        return $this->belongsTo(OverdraftRun::class, 'overdraft_run_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function unrecognised(): float
    {
        return round(max(0, $this->amount - $this->recognised_amount), 2);
    }
}
