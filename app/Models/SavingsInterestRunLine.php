<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingsInterestRunLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'savings_interest_run_id', 'savings_account_id', 'client_id', 'period_start', 'period_end', 'days',
        'closing_balance', 'average_balance', 'rate_label', 'interest', 'savings_transaction_id', 'previous_last_interest_date',
    ];

    protected $casts = [
        'period_start'    => 'date',
        'period_end'      => 'date',
        'closing_balance' => 'float',
        'average_balance' => 'float',
        'interest'        => 'float',
    ];

    public function account()
    {
        return $this->belongsTo(SavingsAccount::class, 'savings_account_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
