<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanInterestCharge extends Model {
    protected $fillable = [
        'loan_id', 'loan_schedule_id', 'from_date', 'to_date', 'days', 'principal', 'rate',
        'amount', 'carried_before', 'carried_after', 'created_by',
    ];

    protected $casts = [
        'from_date'      => 'date',
        'to_date'        => 'date',
        'principal'      => 'float',
        'rate'           => 'float',
        'amount'         => 'float',
        'carried_before' => 'float',
        'carried_after'  => 'float',
    ];

    public function loan() { return $this->belongsTo(Loan::class); }
    public function schedule() { return $this->belongsTo(LoanSchedule::class, 'loan_schedule_id'); }
}
