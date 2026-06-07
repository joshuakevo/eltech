<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id', 'account_id', 'client_id', 'debit', 'credit', 'description',
    ];

    protected $casts = [
        'debit' => 'float',
        'credit' => 'float',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
