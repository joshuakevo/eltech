<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanProvision extends Model
{
    use HasFactory;

    protected $fillable = [
        'provision_type', 'as_at_date', 'rate', 'total_outstanding', 'required_provision',
        'previous_provision', 'adjustment', 'loan_count', 'transaction_id', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'as_at_date'         => 'date',
        'rate'               => 'float',
        'total_outstanding'  => 'float',
        'required_provision' => 'float',
        'previous_provision' => 'float',
        'adjustment'         => 'float',
        'loan_count'         => 'integer',
    ];

    public function lines()
    {
        return $this->hasMany(LoanProvisionLine::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
