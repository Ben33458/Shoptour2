<?php

declare(strict_types=1);

namespace App\Models\Delivery;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A repeating delivery tour template.
 *
 * Defines when and how often a driver visits a set of postal codes.
 * Concrete runs are modelled as Tour instances.
 *
 * min_gebinde_qty:
 *   A customer may only select this tour if their order contains at least
 *   this many Gebinde units. 0 = always selectable.
 *
 * min_order_value_milli (BUG-11):
 *   Minimum gross cart total (milli-cents) for this tour. 0 = no minimum.
 *   Checkout shows a warning and blocks submission when below threshold.
 *
 * @property int    $id
 * @property string $name
 * @property string $frequency             weekly|bi-weekly|monthly
 * @property string $day_of_week           Monday|Tuesday|...|Sunday
 * @property int    $min_gebinde_qty
 * @property int    $min_order_value_milli BUG-11: min gross total in milli-cents; 0 = no minimum
 * @property bool   $active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Collection<int, DeliveryArea>       $deliveryAreas
 * @property-read Collection<int, CustomerTourOrder>  $customerTourOrders
 * @property-read Collection<int, Tour>               $tours
 */
class RegularDeliveryTour extends Model
{
    // Allowed values for frequency
    public const FREQUENCY_WEEKLY    = 'weekly';
    public const FREQUENCY_BIWEEKLY  = 'bi-weekly';
    public const FREQUENCY_MONTHLY   = 'monthly';

    // Allowed values for day_of_week (ISO full names)
    public const DAYS = [
        'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'frequency',
        'day_of_week',
        'min_gebinde_qty',
        'min_order_value_milli',
        'active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'min_gebinde_qty'       => 'integer',
        'min_order_value_milli' => 'integer',
        'active'                => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * All postal code areas served by this tour.
     *
     * @return HasMany<DeliveryArea>
     */
    public function deliveryAreas(): HasMany
    {
        return $this->hasMany(DeliveryArea::class);
    }

    /**
     * Per-customer stop order definitions for this tour.
     *
     * @return HasMany<CustomerTourOrder>
     */
    public function customerTourOrders(): HasMany
    {
        return $this->hasMany(CustomerTourOrder::class);
    }

    /**
     * Concrete tour runs generated from this template.
     *
     * @return HasMany<Tour>
     */
    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }
}
