<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'label', 'type'];

    public static function get(string $key, $default = null)
    {
        // Only real values are cached -- caching the default would hide a setting that is
        // added later (e.g. by a migration) until the cache expires.
        $value = Cache::remember("setting_{$key}", 3600, function () use ($key) {
            return static::where('key', $key)->value('value');
        });

        return $value ?? $default;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting_{$key}");
    }

    public static function grouped(): array
    {
        return static::all()->groupBy('group')->toArray();
    }
}
