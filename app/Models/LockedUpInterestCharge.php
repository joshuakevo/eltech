<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Interest charged by hand on a Locked-Up loan (see LockedUpInterestService). */
class LockedUpInterestCharge extends Model
{
    protected $fillable = [
        'loan_id', 'charge_date', 'from_date', 'days', 'principal', 'rate', 'amount', 'notes',
        'interest_before', 'repayment_ids', 'created_by',
    ];

    protected $casts = [
        'charge_date'     => 'date',
        'from_date'       => 'date',
        'principal'       => 'float',
        'rate'            => 'float',
        'amount'          => 'float',
        'interest_before' => 'float',
        'repayment_ids'   => 'array',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Statement narration: just "Interest Charged" (the charge date is in its own column). */
    public function narration(): string
    {
        return 'Interest Charged';
    }
}
