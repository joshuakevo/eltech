<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A month-end overdraft charge run (OverdraftService::run). */
class OverdraftRun extends Model
{
    protected $fillable = ['run_date', 'rate', 'minimum', 'accounts', 'total', 'status', 'reversed_at', 'reversed_by', 'created_by'];

    protected $casts = ['run_date' => 'date', 'rate' => 'float', 'minimum' => 'float', 'total' => 'float', 'reversed_at' => 'datetime'];

    public function charges()
    {
        return $this->hasMany(OverdraftCharge::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
