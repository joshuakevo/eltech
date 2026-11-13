<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Money collected on a written-off loan (bad debts recovered) — LoanWriteOffService::recover. */
class LoanWriteOffRecovery extends Model
{
    protected $table = 'loan_writeoff_recoveries';

    protected $fillable = [
        'loan_writeoff_id', 'loan_id', 'recovery_date', 'amount', 'source', 'payment_account_id', 'savings_account_id',
        'savings_transaction_id', 'transaction_id', 'notes', 'created_by',
    ];

    protected $casts = ['recovery_date' => 'date', 'amount' => 'float'];

    public function writeOff()
    {
        return $this->belongsTo(LoanWriteOff::class, 'loan_writeoff_id');
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function paymentAccount()
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    public function savingsAccount()
    {
        return $this->belongsTo(SavingsAccount::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
