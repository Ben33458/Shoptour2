<?php

declare(strict_types=1);

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A weather snapshot (forecast or actual) for an EventOccurrence.
 *
 * @property int         $id
 * @property int         $event_occurrence_id
 * @property string      $weather_type
 * @property string|null $source
 * @property \Carbon\Carbon $captured_at
 * @property \Carbon\Carbon|null $forecast_for_at
 * @property float|null  $temperature_min
 * @property float|null  $temperature_max
 * @property float|null  $temperature_avg
 * @property float|null  $precipitation_mm
 * @property float|null  $wind_speed
 * @property string|null $weather_description
 * @property array|null  $raw_payload
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EventWeatherSnapshot extends Model
{
    protected $table = 'event_weather_snapshots';

    protected $fillable = [
        'event_occurrence_id',
        'weather_type',
        'source',
        'captured_at',
        'forecast_for_at',
        'temperature_min',
        'temperature_max',
        'temperature_avg',
        'precipitation_mm',
        'wind_speed',
        'weather_description',
        'raw_payload',
    ];

    protected $casts = [
        'captured_at'     => 'datetime',
        'forecast_for_at' => 'datetime',
        'raw_payload'     => 'array',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }
}
