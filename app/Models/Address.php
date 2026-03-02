<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Pricing\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WP-21 – A delivery or billing address belonging to a Customer.
 *
 * Each customer can have multiple addresses of type "delivery" or "billing".
 * The is_default flag marks the preferred address per type.
 *
 * @property int         $id
 * @property int         $customer_id
 * @property string      $type          "delivery" | "billing"
 * @property bool        $is_default
 * @property string|null $label         e.g. "Büro", "Lager"
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $company
 * @property string      $street
 * @property string|null $house_number
 * @property string      $zip
 * @property string      $city
 * @property string      $country_code  ISO 3166-1 alpha-2, default "DE"
 * @property string|null $phone
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Customer $customer
 */
class Address extends Model
{
    /** Drop-off location options */
    public const DROP_OFF_KELLER     = 'keller';
    public const DROP_OFF_EINFAHRT   = 'einfahrt';
    public const DROP_OFF_EG         = 'eg';
    public const DROP_OFF_GARAGE     = 'garage';
    public const DROP_OFF_OG1        = 'og1';
    public const DROP_OFF_SONSTIGES  = 'sonstiges';

    public const DROP_OFF_LABELS = [
        self::DROP_OFF_KELLER    => 'Keller',
        self::DROP_OFF_EINFAHRT  => 'Einfahrt',
        self::DROP_OFF_EG        => 'Erdgeschoss',
        self::DROP_OFF_GARAGE    => 'Garage',
        self::DROP_OFF_OG1       => '1. OG',
        self::DROP_OFF_SONSTIGES => 'Sonstiges',
    ];

    protected $fillable = [
        'customer_id',
        'type',
        'is_default',
        'label',
        'first_name',
        'last_name',
        'company',
        'street',
        'house_number',
        'zip',
        'city',
        'country_code',
        'phone',
        'drop_off_location',
        'drop_off_location_custom',
        'leave_at_door',
    ];

    protected $casts = [
        'is_default'    => 'boolean',
        'leave_at_door' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns a single-line string representation of the address.
     */
    public function oneLiner(): string
    {
        $parts = array_filter([
            trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')),
            $this->company,
            trim(($this->street ?? '') . ' ' . ($this->house_number ?? '')),
            trim(($this->zip ?? '') . ' ' . ($this->city ?? '')),
        ]);

        return implode(', ', $parts);
    }
}
