<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A Locked-Up loan moved into the active book (product, rate, schedule) — undoable while untouched. */
class LoanActivation extends Model
{
    protected $fillable = [
        'loan_id', 'from_product_id', 'to_product_id', 'start_date', 'interest_rate', 'interest_method', 'term_months',
        'installment_amount', 'principal', 'interest', 'transaction_id', 'correction_id', 'snapshot', 'reason',
        'status', 'reversed_at', 'reversed_by', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date', 'snapshot' => 'array', 'reversed_at' => 'datetime',
        'principal' => 'float', 'interest' => 'float', 'installment_amount' => 'float', 'interest_rate' => 'float',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function toProduct()
    {
        return $this->belongsTo(LoanProduct::class, 'to_product_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
