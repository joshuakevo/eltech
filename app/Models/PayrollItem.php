<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model {
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'savings_account_id', 'pay_type',
        'basic_salary', 'allowances', 'paye', 'nssf_employee', 'nssf_employer',
        'lunch', 'transport', 'staff_savings', 'deductions', 'net_salary',
    ];

    protected $casts = [
        'basic_salary'  => 'float',
        'allowances'    => 'float',
        'paye'          => 'float',
        'nssf_employee' => 'float',
        'nssf_employer' => 'float',
        'lunch'         => 'float',
        'transport'     => 'float',
        'staff_savings' => 'float',
        'deductions'    => 'float',
        'net_salary'    => 'float',
    ];

    public const NSSF_EMPLOYEE_RATE = 0.05;
    public const NSSF_EMPLOYER_RATE = 0.10;
    public const DEFAULT_LUNCH      = 3500;
    public const DEFAULT_STAFF_SAVINGS = 50000;

    /**
     * Uganda monthly PAYE (resident individuals, employment income), marginal bands:
     *   0 – 335,000: nil
     *   335,001 – 410,000: 20% of excess over 335,000
     *   410,001 – 485,000: 15,000 + 25% of excess over 410,000
     *   485,001 – 10,000,000: 33,750 + 30% of excess over 485,000
     *   above 10,000,000: 40% (30% + 10% surtax) on the excess over 10,000,000
     */
    public static function calculatePaye(float $gross): float
    {
        if ($gross <= 335000) {
            return 0;
        }
        if ($gross <= 410000) {
            return round(($gross - 335000) * 0.20, 2);
        }
        if ($gross <= 485000) {
            return round(15000 + ($gross - 410000) * 0.25, 2);
        }

        $paye = 33750 + ($gross - 485000) * 0.30;
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
