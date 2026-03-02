<?php

declare(strict_types=1);

namespace App\Models\Delivery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps a postal code to a regular delivery tour.
 *
 * A postal code may appear on multiple tours (when several tours serve the
 * same area on different days). The unique constraint is on
 * (postal_code, regular_delivery_tour_id) — not on postal_code alone.
 *
 * @property int         $id
 * @property string      $postal_code
 * @property string      $city_name
 * @property string|null $district_name
 * @property int         $regular_delivery_tour_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read RegularDeliveryTour $regularDeliveryTour
 */
class DeliveryArea extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'postal_code',
        'city_name',
        'district_name',
        'regular_delivery_tour_id',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The tour that serves this postal code area.
     */
    public function regularDeliveryTour(): BelongsTo
    {
        return $this->belongsTo(RegularDeliveryTour::class);
    }
}
