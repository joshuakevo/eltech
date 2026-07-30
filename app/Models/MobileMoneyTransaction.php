<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileMoneyTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'savings_account_id', 'type', 'amount', 'phone_number',
        'reference', 'provider_reference', 'status', 'description', 'failure_reason',
        'savings_transaction_id', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'amount'      => 'float',
        'approved_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function savingsAccount()
    {
        return $this->belongsTo(SavingsAccount::class);
    }

    public function savingsTransaction()
    {
        return $this->belongsTo(SavingsTransaction::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['successful', 'failed', 'cancelled'], true);
    }
}
