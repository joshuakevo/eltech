<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanCorrection extends Model {
    protected $fillable = [
        'loan_id', 'as_at_date', 'old_principal', 'old_interest', 'principal_at_date', 'interest_at_date', 'installment_amount',
        'new_principal', 'new_interest', 'principal_adjustment', 'offset_account_id', 'transaction_id',
        'reason', 'snapshot', 'status', 'created_by', 'reversed_at', 'reversed_by',
    ];

    protected $casts = [
        'as_at_date'           => 'date',
        'old_principal'        => 'float',
        'old_interest'         => 'float',
        'principal_at_date'    => 'float',
        'interest_at_date'     => 'float',
        'installment_amount'   => 'float',
        'new_principal'        => 'float',
        'new_interest'         => 'float',
        'principal_adjustment' => 'float',
        'snapshot'             => 'array',
        'reversed_at'          => 'datetime',
    ];

    public function loan() { return $this->belongsTo(Loan::class); }
    public function transaction() { return $this->belongsTo(Transaction::class); }
    public function offsetAccount() { return $this->belongsTo(Account::class, 'offset_account_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
