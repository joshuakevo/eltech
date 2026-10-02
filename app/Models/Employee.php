<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model {
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_number', 'client_id', 'position', 'department', 'pay_type',
        'basic_salary', 'savings_account_id', 'status', 'notes', 'created_by',
    ];

    protected $casts = ['basic_salary' => 'float'];

    /** Pay type => label. Decides which expense account payroll debits. */
    public const PAY_TYPES = [
        'salary'     => 'Salary',
        'commission' => 'Agency Commission',
    ];

    /** Pay type => expense GL account code debited when payroll is processed. */
    public const PAY_TYPE_EXPENSE_ACCOUNTS = [
        'salary'     => '5003', // Staff Salaries
        'commission' => '5103', // Agency Commissions
    ];

    public static function payTypeLabel(?string $type): string {
        return self::PAY_TYPES[$type ?? 'salary'] ?? ucfirst((string) $type);
    }

    public function getNameAttribute(): string {
        return $this->client?->name ?? '—';
    }

    public function client() {
        return $this->belongsTo(Client::class);
    }
    public function savingsAccount() {
        return $this->belongsTo(SavingsAccount::class);
    }
    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function payrollItems() {
        return $this->hasMany(PayrollItem::class);
    }
}
