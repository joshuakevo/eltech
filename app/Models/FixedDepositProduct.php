<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedDepositProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'interest_rate', 'term_months', 'min_amount', 'max_amount',
        'deposit_liability_account_id', 'interest_expense_account_id', 'is_active',
    ];

    protected $casts = [
        'interest_rate' => 'float',
        'is_active'     => 'boolean',
    ];

    public function fixedDeposits()
    {
        return $this->hasMany(FixedDeposit::class, 'product_id');
    }

    public function liabilityAccount()
    {
        return $this->belongsTo(Account::class, 'deposit_liability_account_id');
    }

    public function interestExpenseAccount()
    {
        return $this->belongsTo(Account::class, 'interest_expense_account_id');
    }
}
