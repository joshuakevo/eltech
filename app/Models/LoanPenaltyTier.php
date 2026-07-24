<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanPenaltyTier extends Model
{
    use HasFactory;

    protected $fillable = ['min_installment', 'max_installment', 'penalty_amount'];

    protected $casts = [
        'min_installment' => 'float',
        'max_installment' => 'float',
        'penalty_amount'  => 'float',
    ];
}
