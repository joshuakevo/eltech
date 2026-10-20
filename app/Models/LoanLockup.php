<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A request to move a normal loan to Locked-Up Loans (four-eyes: initiated, then two approvals). */
class LoanLockup extends Model
{
    /** Sidebar badges (layouts/app) are cached for a minute — refresh them when this changes. */
    protected static function booted(): void
    {
        static::saved(fn () => \Illuminate\Support\Facades\Cache::forget('nav_counts'));
        static::deleted(fn () => \Illuminate\Support\Facades\Cache::forget('nav_counts'));
    }

    protected $fillable = [
        'loan_id', 'lockup_date', 'reason', 'status', 'requested_by', 'approvals', 'rejected_by', 'rejected_at',
        'rejection_reason', 'executed_at', 'principal', 'interest', 'from_product_id', 'transaction_id', 'snapshot',
        'reversed_at', 'reversed_by',
    ];

    protected $casts = [
        'lockup_date' => 'date', 'approvals' => 'array', 'snapshot' => 'array',
        'rejected_at' => 'datetime', 'executed_at' => 'datetime', 'reversed_at' => 'datetime',
        'principal' => 'float', 'interest' => 'float',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
