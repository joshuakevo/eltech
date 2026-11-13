<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** More money lent on a running loan, restarting it on new terms — undoable while untouched (LoanTopUpService). */
class LoanTopUp extends Model
{
    protected $table = 'loan_topups';

    protected $fillable = [
        'loan_id', 'topup_date', 'amount', 'balance_before', 'interest_owed', 'penalty_carried', 'new_principal',
        'interest_rate', 'interest_method', 'term_months', 'installment_amount', 'payout', 'payout_account_id',
        'savings_account_id', 'savings_transaction_id', 'transaction_id', 'correction_id', 'snapshot', 'reason',
        'status', 'reversed_at', 'reversed_by', 'created_by',
        'application_fee', 'application_fee_method', 'management_fee_rate', 'management_fee', 'management_fee_method',
        'insurance_fee_rate', 'insurance_fee', 'insurance_fee_method', 'net_payout', 'fee_transaction_id', 'fee_savings_transaction_id',
    ];

    protected $casts = [
        'topup_date' => 'date', 'snapshot' => 'array', 'reversed_at' => 'datetime',
        'amount' => 'float', 'balance_before' => 'float', 'interest_owed' => 'float', 'penalty_carried' => 'float',
        'new_principal' => 'float', 'interest_rate' => 'float', 'installment_amount' => 'float',
        'application_fee' => 'float', 'management_fee_rate' => 'float', 'management_fee' => 'float',
        'insurance_fee_rate' => 'float', 'insurance_fee' => 'float', 'net_payout' => 'float',
    ];

    public function totalFees(): float
    {
        return round($this->application_fee + $this->management_fee + $this->insurance_fee, 2);
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function savingsAccount()
    {
        return $this->belongsTo(SavingsAccount::class);
    }

    public function payoutAccount()
    {
        return $this->belongsTo(Account::class, 'payout_account_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
