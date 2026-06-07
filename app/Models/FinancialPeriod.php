<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FinancialPeriod extends Model
{
    protected $fillable = [
        'year', 'month', 'status', 'notes',
        'closed_by', 'closed_at', 'reopened_by', 'reopened_at',
    ];

    protected $casts = [
        'closed_at'   => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public function closedBy()  { return $this->belongsTo(User::class, 'closed_by'); }
    public function reopenedBy(){ return $this->belongsTo(User::class, 'reopened_by'); }

    /** Get or create a period record for a given date. Non-existent periods are treated as open. */
    public static function forDate(string|\Carbon\Carbon $date): self
    {
        $d = Carbon::parse($date);
        return static::firstOrCreate(
            ['year' => $d->year, 'month' => $d->month],
            ['status' => 'open']
        );
    }

    /** Returns true if the period containing the given date is open for posting. */
    public static function isOpen(string|\Carbon\Carbon $date): bool
    {
        $d = Carbon::parse($date);
        $period = static::where('year', $d->year)->where('month', $d->month)->first();
        return !$period || $period->status === 'open';
    }

    public function isClosed(): bool { return $this->status === 'closed'; }

    public function label(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }
}
