<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'event', 'module', 'description',
        'ip_address', 'url', 'method', 'user_agent', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $event, string $description, ?string $module = null): void
    {
        try {
            static::create([
                'user_id'     => auth()->id(),
                'event'       => $event,
                'module'      => $module,
                'description' => $description,
                'ip_address'  => request()->ip(),
                'url'         => request()->fullUrl(),
                'method'      => request()->method(),
                'user_agent'  => request()->userAgent(),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // Never let audit logging break the main request
        }
    }
}
