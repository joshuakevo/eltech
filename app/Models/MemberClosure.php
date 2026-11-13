<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A member's account closure (MemberClosureService) — closure fee taken, rest paid out, overdrafts written off, savings accounts closed. */
class MemberClosure extends Model
{
    protected $fillable = [
        'client_id', 'closure_date', 'total_balance', 'closure_fee', 'payout', 'written_off', 'payout_account_id',
        'transaction_id', 'snapshot', 'reason', 'status', 'reversed_at', 'reversed_by', 'created_by',
    ];

    protected $casts = [
        'closure_date' => 'date', 'snapshot' => 'array', 'reversed_at' => 'datetime',
        'total_balance' => 'float', 'closure_fee' => 'float', 'payout' => 'float', 'written_off' => 'float',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function payoutAccount()
    {
        return $this->belongsTo(Account::class, 'payout_account_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
