<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavingsProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'minimum_balance', 'withdrawal_fee', 'interest_rate', 'interest_method', 'interest_frequency',
        'savings_liability_account_id', 'interest_expense_account_id', 'is_active',
    ];

    protected $casts = [
        'minimum_balance' => 'float',
        'withdrawal_fee'  => 'float',
        'interest_rate'   => 'float',
        'is_active'       => 'boolean',
    ];

    public function savingsAccounts()
    {
        return $this->hasMany(SavingsAccount::class, 'product_id');
    }

    public function liabilityAccount()
    {
        return $this->belongsTo(Account::class, 'savings_liability_account_id');
    }

    public function interestExpenseAccount()
    {
        return $this->belongsTo(Account::class, 'interest_expense_account_id');
    }
}
