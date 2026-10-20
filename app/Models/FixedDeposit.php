<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedDeposit extends Model
{
    use HasFactory, SoftDeletes;

    /** Sidebar badges (layouts/app) are cached for a minute — refresh them when this changes. */
    protected static function booted(): void
    {
        static::saved(fn () => \Illuminate\Support\Facades\Cache::forget('nav_counts'));
        static::deleted(fn () => \Illuminate\Support\Facades\Cache::forget('nav_counts'));
    }

    protected $fillable = [
        'client_id', 'savings_account_id', 'product_id', 'deposit_number', 'principal', 'interest_rate',
        'compounding', 'asset_class', 'certificate_reference',
        'term_months', 'start_date', 'maturity_date', 'interest_amount', 'maturity_amount',
        'accrued_interest', 'status', 'closed_date', 'payment_source_account_id', 'created_by',
    ];

    protected $casts = [
        'principal'       => 'float',
        'interest_rate'   => 'float',
        'interest_amount' => 'float',
        'maturity_amount' => 'float',
        'accrued_interest'=> 'float',
        'start_date'      => 'date',
        'maturity_date'   => 'date',
        'closed_date'     => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function savingsAccount()
    {
        return $this->belongsTo(SavingsAccount::class, 'savings_account_id');
    }

    public function product()
    {
        return $this->belongsTo(FixedDepositProduct::class, 'product_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isMatured(): bool
    {
        return $this->maturity_date->isPast() || $this->maturity_date->isToday();
    }

    /**
     * Interest month by month: one row per monthly anniversary of the value date
     * (interest_amount ÷ term; the last month takes the rounding), with running totals.
     * 'earned' = the anniversary has been reached (on or before $asOf).
     */
    public function monthlySchedule(?\Carbon\Carbon $asOf = null): array
    {
        $asOf  = ($asOf ?? today())->copy()->startOfDay();
        $n     = max(1, (int) $this->term_months);
        $each  = round((float) $this->interest_amount / $n, 2);
        $r     = (float) $this->interest_rate / 1200;   // monthly rate
        $compound = $this->isCompounding();
        $rows  = [];
        $total = 0.0;
        for ($k = 1; $k <= $n; $k++) {
            $date = $this->start_date->copy()->addMonthsNoOverflow($k);
            if ($k === $n) {
                $date = $this->maturity_date->copy();
            }
            // Compounding: month k earns on principal + interest already earned, P(1+r)^(k-1) × r
            $monthAmount = $compound ? round((float) $this->principal * pow(1 + $r, $k) - (float) $this->principal - $total, 2) : $each;
            $amount = $k === $n ? round((float) $this->interest_amount - $total, 2) : $monthAmount;
            $total  = round($total + $amount, 2);
            $rows[] = ['month' => $k, 'date' => $date, 'interest' => $amount, 'cumulative' => $total,
                'value' => round((float) $this->principal + $total, 2), 'earned' => $date->lte($asOf)];
        }
        return $rows;
    }

    public function isCompounding(): bool
    {
        return ($this->compounding ?? 'simple') === 'monthly';
    }

    /** Total interest for a placement: simple P × R × T, or compounded monthly P × ((1 + R/12)^months − 1). */
    public static function interestFor(float $principal, float $annualRate, int $months, string $compounding = 'simple'): float
    {
        if ($compounding === 'monthly') {
            return round($principal * (pow(1 + $annualRate / 1200, $months) - 1), 2);
        }
        return round($principal * ($annualRate / 100) * ($months / 12), 2);
    }

    public function daysToMaturity(): int
    {
        return max(0, now()->diffInDays($this->maturity_date, false));
    }
}
