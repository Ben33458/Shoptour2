<?php

declare(strict_types=1);

namespace App\Models\Delivery;

use App\Models\Pricing\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Defines the stop sequence number for a specific customer on a specific tour.
 *
 * stop_order_number:
 *   Determines the order in which the driver visits customers.
 *   Conventionally 10, 20, 30 … to allow gaps for future insertions.
 *   TourPlannerService sorts by this value ascending when building tour_stops.
 *   Customers without a row for the tour fall back to sorting by last name.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $regular_delivery_tour_id
 * @property int $stop_order_number
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Customer            $customer
 * @property-read RegularDeliveryTour $regularDeliveryTour
 */
class CustomerTourOrder extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'regular_delivery_tour_id',
        'stop_order_number',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'stop_order_number' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function regularDeliveryTour(): BelongsTo
    {
        return $this->belongsTo(RegularDeliveryTour::class);
    }
}
