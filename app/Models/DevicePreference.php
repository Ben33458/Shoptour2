<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevicePreference extends Model
{
    protected $fillable = ['token_hash', 'device_type', 'last_seen_at'];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public static function findByToken(string $plainToken): ?self
    {
        $hash = hash('sha256', $plainToken);
        return static::where('token_hash', $hash)->first();
    }

    public static function setType(string $plainToken, string $type): self
    {
        $hash = hash('sha256', $plainToken);
        return static::updateOrCreate(
            ['token_hash' => $hash],
            ['device_type' => $type, 'last_seen_at' => now()]
        );
    }
}
