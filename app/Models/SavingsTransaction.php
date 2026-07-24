<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavingsTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'savings_account_id', 'transaction_type', 'amount', 'charge_amount', 'institution_charge', 'balance_before',
        'balance_after', 'transaction_date', 'reference', 'description', 'payment_source_account_id', 'transaction_id', 'created_by',
    ];

    protected $casts = [
        'amount'             => 'float',
        'charge_amount'      => 'float',
        'institution_charge' => 'float',
        'balance_before'     => 'float',
        'balance_after'      => 'float',
        'transaction_date'   => 'date',
    ];

    public function savingsAccount()
    {
        return $this->belongsTo(SavingsAccount::class);
    }

    public function journalTransaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
