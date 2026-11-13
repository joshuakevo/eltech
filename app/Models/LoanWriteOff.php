<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A Locked-Up loan written off (LoanWriteOffService): principal to bad loan write-off, interest against accrued interest. */
class LoanWriteOff extends Model
{
    protected $table = 'loan_writeoffs';

    protected $fillable = [
        'loan_id', 'writeoff_date', 'principal', 'interest', 'penalty', 'transaction_id', 'reason', 'snapshot',
        'status', 'created_by', 'reversed_at', 'reversed_by',
    ];

    protected $casts = [
        'writeoff_date' => 'date', 'snapshot' => 'array', 'reversed_at' => 'datetime',
        'principal' => 'float', 'interest' => 'float', 'penalty' => 'float',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function recoveries()
    {
        return $this->hasMany(LoanWriteOffRecovery::class, 'loan_writeoff_id')->orderBy('recovery_date')->orderBy('id');
    }

    /** Total written off (principal + interest + penalty b/f) — the most that can be recovered. */
    public function total(): float
    {
        return round($this->principal + $this->interest + $this->penalty, 2);
    }

    public function recovered(): float
    {
        return round((float) $this->recoveries()->sum('amount'), 2);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
