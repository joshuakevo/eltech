<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'interest_rate', 'interest_method', 'repayment_frequency', 'term_months', 'penalty_rate',
        'min_amount', 'max_amount', 'receivable_account_id', 'interest_income_account_id',
        'penalty_income_account_id', 'disbursement_account_id', 'is_active',
    ];

    protected $casts = [
        'interest_rate' => 'float',
        'penalty_rate'  => 'float',
        'is_active'     => 'boolean',
    ];

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function receivableAccount()
    {
        return $this->belongsTo(Account::class, 'receivable_account_id');
    }

    public function interestIncomeAccount()
    {
        return $this->belongsTo(Account::class, 'interest_income_account_id');
    }

    public function penaltyIncomeAccount()
    {
        return $this->belongsTo(Account::class, 'penalty_income_account_id');
    }

    public function disbursementAccount()
    {
        return $this->belongsTo(Account::class, 'disbursement_account_id');
    }
}
