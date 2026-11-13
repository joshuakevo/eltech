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

    /**
     * Overdraft fees: money paid into an account with accrued overdraft charges recognises them as income
     * (up to the amount paid); deleting that payment reverses the recognition. See OverdraftService.
     */
    protected static function booted(): void
    {
        static::created(function (SavingsTransaction $row) {
            if (static::overdraftTablesReady() && \App\Services\OverdraftService::isPayment($row)
                && \App\Models\OverdraftCharge::where('savings_account_id', $row->savings_account_id)->whereColumn('recognised_amount', '<', 'amount')->exists()) {
                app(\App\Services\OverdraftService::class)->recogniseForPayment($row);
            }
        });
        static::deleted(function (SavingsTransaction $row) {
            if (static::overdraftTablesReady() && \App\Models\OverdraftFeeRecognition::where('savings_transaction_id', $row->id)->exists()) {
                app(\App\Services\OverdraftService::class)->paymentRemoved($row);
            }
        });
    }

    private static ?bool $overdraftReady = null;

    private static function overdraftTablesReady(): bool
    {
        return static::$overdraftReady ??= \Illuminate\Support\Facades\Schema::hasTable('overdraft_fee_recognitions');
    }

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
