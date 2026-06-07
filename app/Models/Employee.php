<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model {
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_number', 'client_id', 'position', 'department',
        'basic_salary', 'savings_account_id', 'status', 'notes', 'created_by',
    ];

    protected $casts = ['basic_salary' => 'float'];

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
