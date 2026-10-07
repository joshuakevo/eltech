<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanRun extends Model {
    protected $fillable = [
        'batch', 'loan_id', 'run_date', 'due_date', 'loan_schedule_id', 'interest_charge_id', 'repayment_id',
        'repayment_transaction_id', 'savings_transaction_id', 'withdrawal_transaction_id', 'savings_account_id',
        'accrued', 'recovered', 'snapshot', 'status', 'created_by', 'undone_at', 'undone_by',
    ];

    protected $casts = [
        'run_date'  => 'date',
        'due_date'  => 'date',
        'accrued'   => 'float',
        'recovered' => 'float',
        'snapshot'  => 'array',
        'undone_at' => 'datetime',
    ];

    public function loan() { return $this->belongsTo(Loan::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
