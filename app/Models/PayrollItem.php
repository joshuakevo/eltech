<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model {
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'savings_account_id',
        'basic_salary', 'allowances', 'paye', 'nssf_employee', 'nssf_employer',
        'deductions', 'net_salary',
    ];

    protected $casts = [
        'basic_salary'  => 'float',
        'allowances'    => 'float',
        'paye'          => 'float',
        'nssf_employee' => 'float',
        'nssf_employer' => 'float',
        'deductions'    => 'float',
        'net_salary'    => 'float',
    ];

    public const NSSF_EMPLOYEE_RATE = 0.05;
    public const NSSF_EMPLOYER_RATE = 0.10;

    /**
     * Uganda monthly PAYE (resident individuals, employment income):
     *   0 – 235,000: nil
     *   235,001 – 335,000: 10% of excess over 235,000
     *   335,001 – 410,000: 10,000 + 20% of excess over 335,000
     *   above 410,000: 25,000 + 30% of excess over 410,000
     *   above 10,000,000: additional 10% surcharge on the excess over 10,000,000
     */
    public static function calculatePaye(float $gross): float
    {
        if ($gross <= 235000) {
            return 0;
        }
        if ($gross <= 335000) {
            return round(($gross - 235000) * 0.10, 2);
        }
        if ($gross <= 410000) {
            return round(10000 + ($gross - 335000) * 0.20, 2);
        }

        $paye = 25000 + ($gross - 410000) * 0.30;
        if ($gross > 10000000) {
            $paye += ($gross - 10000000) * 0.10;
        }

        return round($paye, 2);
    }

    public static function calculateNssfEmployee(float $gross): float
    {
        return round($gross * self::NSSF_EMPLOYEE_RATE, 2);
    }

    public static function calculateNssfEmployer(float $gross): float
    {
        return round($gross * self::NSSF_EMPLOYER_RATE, 2);
    }

    public function employee() { return $this->belongsTo(Employee::class); }
    public function payrollRun() { return $this->belongsTo(PayrollRun::class); }
    public function savingsAccount() { return $this->belongsTo(SavingsAccount::class); }
}
