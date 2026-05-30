<?php

declare(strict_types=1);

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A logistics appointment (delivery, pickup, etc.) for an EventOccurrence.
 *
 * @property int         $id
 * @property int         $event_occurrence_id
 * @property string      $type
 * @property string      $status
 * @property string      $scheduled_date
 * @property string|null $time_from
 * @property string|null $time_to
 * @property string      $time_flexibility
 * @property string|null $address_snapshot
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property int|null    $tour_id
 * @property int|null    $vehicle_type_id
 * @property int|null    $estimated_trips
 * @property int|null    $actual_trips
 * @property bool|null   $helpers_available
 * @property string|null $access_notes
 * @property string|null $driver_notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EventLogisticsAppointment extends Model
{
    protected $table = 'event_logistics_appointments';

    protected $fillable = [
        'event_occurrence_id',
        'type',
        'status',
        'scheduled_date',
        'time_from',
        'time_to',
        'time_flexibility',
        'address_snapshot',
        'contact_name',
        'contact_phone',
        'tour_id',
        'vehicle_type_id',
        'estimated_trips',
        'actual_trips',
        'helpers_available',
        'access_notes',
        'driver_notes',
    ];

    protected $casts = [
        'scheduled_date'   => 'date',
        'helpers_available'=> 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }
}
