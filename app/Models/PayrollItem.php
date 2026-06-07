<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model {
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'savings_account_id',
        'basic_salary', 'allowances', 'deductions', 'net_salary',
    ];

    protected $casts = [
        'basic_salary' => 'float',
        'allowances'   => 'float',
        'deductions'   => 'float',
        'net_salary'   => 'float',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function payrollRun() { return $this->belongsTo(PayrollRun::class); }
    public function savingsAccount() { return $this->belongsTo(SavingsAccount::class); }
}
