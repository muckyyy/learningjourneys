<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['setting', 'value'];

    /**
     * Sentinel stored in cache when no DB record exists.
     * Allows Cache::rememberForever() to distinguish "cached null" from "not yet cached".
     */
    private const NOT_FOUND = "\x00__SETTING_NOT_FOUND__\x00";

    /* ──────────────────────────────────────────────
     |  Helpers
     |─────────────────────────────────────────────── */

    /**
     * Get a setting value by key, with an optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever("settings.{$key}", function () use ($key) {
            $record = static::where('setting', $key)->first();

            return $record !== null ? $record->value : self::NOT_FOUND;
        });

        return $value === self::NOT_FOUND ? $default : $value;
    }

    /**
     * Load many settings in one query. Returns [key => value] for existing rows.
     */
    public static function getMany(array $keys): array
    {
        $results = [];

        // Collect keys that are not yet in cache
        $missing = [];
        foreach ($keys as $key) {
            $cached = Cache::get("settings.{$key}");
            if ($cached === self::NOT_FOUND) {
                // Cached as "does not exist" — skip
                continue;
            }
            if ($cached !== null) {
                $results[$key] = $cached;
            } else {
                $missing[] = $key;
            }
        }

        if (! empty($missing)) {
            $rows = static::whereIn('setting', $missing)->pluck('value', 'setting');

            foreach ($missing as $key) {
                if ($rows->has($key)) {
                    $results[$key] = $rows[$key];
                    Cache::forever("settings.{$key}", $rows[$key]);
                } else {
                    Cache::forever("settings.{$key}", self::NOT_FOUND);
                }
            }
        }

        return $results;
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
