<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_code', 'account_name', 'account_type', 'parent_id', 'is_active', 'is_payment_source', 'description',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'is_payment_source' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function transactionLines()
    {
        return $this->hasMany(TransactionLine::class);
    }

    public function getBalanceAttribute(): float
    {
        $debits = $this->transactionLines()->sum('debit');
        $credits = $this->transactionLines()->sum('credit');

        // Assets and Expenses: debit increases balance
        // Liabilities, Equity, Revenue: credit increases balance
        if (in_array($this->account_type, ['asset', 'expense'])) {
            return $debits - $credits;
        }
        return $credits - $debits;
    }

    public function isDebitNormal(): bool
    {
        return in_array($this->account_type, ['asset', 'expense']);
    }
}
