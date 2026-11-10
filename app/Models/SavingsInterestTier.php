<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavingsInterestTier extends Model
{
    use HasFactory;

    protected $fillable = ['min_balance', 'max_balance', 'rate', 'base_interest'];

    protected $casts = [
        'min_balance' => 'float',
        'max_balance' => 'float',
        'rate'        => 'float',
        'base_interest' => 'float',
    ];
}
