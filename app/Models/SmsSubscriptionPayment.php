<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsSubscriptionPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_number', 'amount', 'reference', 'provider_reference', 'status',
        'period_start', 'period_end', 'initiated_by', 'failure_reason',
    ];

    protected $casts = [
        'amount'       => 'float',
        'period_start' => 'date',
        'period_end'   => 'date',
    ];

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['successful', 'failed', 'cancelled'], true);
    }
}
