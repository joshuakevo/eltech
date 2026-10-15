<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedDepositProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'interest_rate', 'rate_basis', 'accrual_method', 'asset_class', 'term_months', 'min_amount', 'max_amount',
        'deposit_liability_account_id', 'interest_expense_account_id', 'is_active',
    ];

    /** Rates are entered/shown per month (stored as % p.a. — always ×12). */
    public function isMonthlyRate(): bool
    {
        return ($this->rate_basis ?? 'annual') === 'monthly';
    }

    /** Interest booked on each monthly anniversary of the value date (not spread by days). */
    public function isAnniversary(): bool
    {
        return ($this->accrual_method ?? 'prorata') === 'anniversary';
    }

    /** A stored % p.a. rate in this product's units, e.g. "2% per month" or "24% p.a.". */
    public function rateLabel(float $annualRate): string
    {
        $v = $this->isMonthlyRate() ? $annualRate / 12 : $annualRate;
        return rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.') . '%' . ($this->isMonthlyRate() ? ' per month' : ' p.a.');
    }

    /** A rate typed in this product's units → stored % p.a. */
    public function toAnnualRate(float $entered): float
    {
        return $this->isMonthlyRate() ? round($entered * 12, 4) : $entered;
    }

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
