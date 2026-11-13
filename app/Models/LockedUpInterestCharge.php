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

    /** Statement narration, e.g. "Interest charged 01 Sep 2026 – 30 Sep 2026 (30 days @ 18% p.a.)". */
    public function narration(): string
    {
        $text = 'Interest charged';
        if ($this->from_date) {
            $text .= ' ' . $this->from_date->format('d M Y') . ' – ' . $this->charge_date->format('d M Y');
            if ($this->days && $this->rate) {
                $text .= ' (' . $this->days . ' days @ ' . rtrim(rtrim(number_format($this->rate, 4), '0'), '.') . '% p.a.)';
            }
        }

        return $text;
    }
}
