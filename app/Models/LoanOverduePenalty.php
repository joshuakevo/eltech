<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One overdue-period penalty on a loan past maturity (amount sits on the last installment's penalty_charged). */
class LoanOverduePenalty extends Model
{
    protected $fillable = ['loan_id', 'loan_schedule_id', 'period_date', 'amount', 'paid_in_period', 'source', 'created_by'];

    protected $casts = ['period_date' => 'date', 'amount' => 'float', 'paid_in_period' => 'float'];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
