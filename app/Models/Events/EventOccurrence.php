<?php

declare(strict_types=1);

namespace App\Models\Events;

use App\Models\Pricing\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A single occurrence of an event — one year's Kerb, one specific Hochzeit, etc.
 *
 * Monetary fields: all *_milli are bigInteger milli-cents (1 EUR = 1_000_000).
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property int|null    $event_series_id
 * @property int|null    $customer_id
 * @property int|null    $billing_customer_id
 * @property string      $title
 * @property string      $event_type
 * @property bool        $is_recurring
 * @property int|null    $event_year
 * @property \Carbon\Carbon|null $event_start_at
 * @property \Carbon\Carbon|null $event_end_at
 * @property int|null    $calendar_week
 * @property string|null $location_name
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $postal_code
 * @property string|null $city
 * @property string      $country
 * @property int|null    $expected_guests
 * @property int|null    $actual_guests
 * @property string|null $indoor_outdoor_type
 * @property string      $request_channel
 * @property string      $offer_status
 * @property string      $event_status
 * @property string|null $source_system
 * @property string|null $source_table
 * @property string|null $source_record_id
 * @property float       $import_confidence
 * @property bool        $needs_review
 * @property string|null $internal_notes
 * @property string|null $customer_visible_notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EventOccurrence extends Model
{
    protected $table = 'event_occurrences';

    protected $fillable = [
        'company_id',
        'event_series_id',
        'customer_id',
        'billing_customer_id',
        'title',
        'event_type',
        'is_recurring',
        'event_year',
        'event_start_at',
        'event_end_at',
        'calendar_week',
        'location_name',
        'address_line1',
        'address_line2',
        'postal_code',
        'city',
        'country',
        'expected_guests',
        'actual_guests',
        'indoor_outdoor_type',
        'request_channel',
        'offer_status',
        'event_status',
        'source_system',
        'source_table',
        'source_record_id',
        'import_confidence',
        'needs_review',
        'internal_notes',
        'customer_visible_notes',
    ];

    protected $casts = [
        'is_recurring'      => 'boolean',
        'event_start_at'    => 'datetime',
        'event_end_at'      => 'datetime',
        'import_confidence' => 'decimal:2',
        'needs_review'      => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function series(): BelongsTo
    {
        return $this->belongsTo(EventSeries::class, 'event_series_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function billingCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'billing_customer_id');
    }

    public function offerVersions(): HasMany
    {
        return $this->hasMany(EventOfferVersion::class, 'event_occurrence_id')->orderBy('version_number');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(EventContact::class, 'event_occurrence_id');
    }

    public function logisticsAppointments(): HasMany
    {
        return $this->hasMany(EventLogisticsAppointment::class, 'event_occurrence_id')->orderBy('scheduled_date');
    }

    public function rentalReservations(): HasMany
    {
        return $this->hasMany(EventRentalReservation::class, 'event_occurrence_id');
    }

    public function weatherSnapshots(): HasMany
    {
        return $this->hasMany(EventWeatherSnapshot::class, 'event_occurrence_id');
    }

    public function postCalculation(): HasOne
    {
        return $this->hasOne(EventPostCalculation::class, 'event_occurrence_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(EventTask::class, 'event_occurrence_id');
    }

    public function importLinks(): HasMany
    {
        return $this->hasMany(EventImportLink::class, 'entity_id')
            ->where('entity_type', self::class);
    }

    /**
     * The latest non-rejected/cancelled/expired offer version.
     */
    public function activeOffer(): HasOne
    {
        return $this->hasOne(EventOfferVersion::class, 'event_occurrence_id')
            ->whereNotIn('status', ['rejected', 'cancelled', 'expired'])
            ->latestOfMany('version_number');
    }

    /**
     * The most recently created offer version.
     */
    public function currentOffer(): HasOne
    {
        return $this->hasOne(EventOfferVersion::class, 'event_occurrence_id')
            ->latestOfMany('version_number');
    }
}
