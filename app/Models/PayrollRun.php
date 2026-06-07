<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model {
    protected $fillable = [
        'run_number', 'period_month', 'period_year', 'description',
        'total_gross', 'status', 'processed_by', 'processed_at', 'created_by',
    ];

    protected $casts = [
        'total_gross'    => 'float',
        'processed_at'   => 'datetime',
    ];

    public function items() {
        return $this->hasMany(PayrollItem::class)->with('employee');
    }
    public function processedBy() {
        return $this->belongsTo(User::class, 'processed_by');
    }
    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getPeriodLabelAttribute(): string {
        return date('F Y', mktime(0, 0, 0, $this->period_month, 1, $this->period_year));
    }
}
