<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingsInterestRun extends Model
{
    protected $fillable = ['savings_product_id', 'period_end', 'total_interest', 'accounts_count', 'transaction_id', 'created_by'];

    protected $casts = [
        'period_end'     => 'date',
        'total_interest' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(SavingsProduct::class, 'savings_product_id');
    }

    public function lines()
    {
        return $this->hasMany(SavingsInterestRunLine::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
