<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Accrued overdraft fees moved to income when the member paid money in (OverdraftService). */
class OverdraftFeeRecognition extends Model
{
    protected $fillable = ['savings_account_id', 'savings_transaction_id', 'recognised_on', 'amount', 'allocations', 'transaction_id'];

    protected $casts = ['recognised_on' => 'date', 'amount' => 'float', 'allocations' => 'array'];

    public function savingsAccount()
    {
        return $this->belongsTo(SavingsAccount::class);
    }
}
