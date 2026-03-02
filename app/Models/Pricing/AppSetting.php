<?php

declare(strict_types=1);

namespace App\Models\Pricing;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value store for application-wide settings.
 *
 * Notable keys used by the Pricing Engine:
 *   "default_customer_group_id" – the CustomerGroup applied to guest sessions
 *                                  and any customer without an explicit group.
 *
 * @property int         $id
 * @property string      $setting_key
 * @property string|null $setting_value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AppSetting extends Model
{
    protected $fillable = [
        'setting_key',
        'setting_value',
    ];

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    /**
     * Retrieve a setting value by key.
     * Returns $default when the key does not exist.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::where('setting_key', $key)->first();

        return $row?->setting_value ?? $default;
    }

    /**
     * Persist (upsert) a setting value by key.
     */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value],
        );
    }

    /**
     * Retrieve a setting value cast to int.
     *
     * @throws \RuntimeException when the key is missing and no default is given.
     */
    public static function getInt(string $key, int $default = null): int
    {
        $value = static::get($key);

        if ($value === null) {
            if ($default !== null) {
                return $default;
            }
            throw new \RuntimeException("AppSetting '{$key}' is not configured.");
        }

        return (int) $value;
    }
}
