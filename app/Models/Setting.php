<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['setting', 'value'];

    /* ──────────────────────────────────────────────
     |  Helpers
     |─────────────────────────────────────────────── */

    /**
     * Get a setting value by key, with an optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("settings.{$key}", function () use ($key, $default) {
            $record = static::where('setting', $key)->first();
            return $record ? $record->value : $default;
        });
    }

    /**
     * Set (create or update) a setting value.
     */
    public static function set(string $key, mixed $value): static
    {
        $record = static::updateOrCreate(
            ['setting' => $key],
            ['value'   => $value],
        );

        Cache::forget("settings.{$key}");

        return $record;
    }

    /**
     * Remove a setting by key.
     */
    public static function remove(string $key): void
    {
        static::where('setting', $key)->delete();
        Cache::forget("settings.{$key}");
    }
}
