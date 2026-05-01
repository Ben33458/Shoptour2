<?php

declare(strict_types=1);

namespace App\Models\Delivery;

use App\Models\Orders\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One customer stop on a concrete Tour.
 *
 * Each stop corresponds to exactly one Order. The driver works through stops
 * in ascending stop_index order.
 *
 * Status lifecycle:
 *   open → arrived → finished
 *       ↘ skipped (e.g. customer not home, order cancelled last minute)
 *
 * Stock is booked by FulfillmentService when the stop is marked finished.
 *
 * @property int              $id
 * @property int              $tour_id
 * @property int              $order_id
 * @property int              $stop_index
 * @property string           $status
 * @property \Carbon\Carbon|null $arrived_at
 * @property \Carbon\Carbon|null $finished_at
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 *
 * @property-read Tour                                    $tour
 * @property-read Order                                   $order
 * @property-read Collection<int, FulfillmentEvent>       $events
 * @property-read Collection<int, OrderItemFulfillment>   $itemFulfillments
 */
class TourStop extends Model
{
    public const STATUS_OPEN     = 'open';
    public const STATUS_ARRIVED  = 'arrived';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_SKIPPED  = 'skipped';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tour_id',
        'order_id',
        'stop_index',
        'status',
        'arrived_at',
        'finished_at',
        'departed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'stop_index'  => 'integer',
        'arrived_at'  => 'datetime',
        'finished_at' => 'datetime',
        'departed_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Append-only audit events for this stop.
     *
     * @return HasMany<FulfillmentEvent>
     */
    public function events(): HasMany
    {
        return $this->hasMany(FulfillmentEvent::class);
    }

    /**
     * Per-item delivery summaries for this stop.
     *
     * @return HasMany<OrderItemFulfillment>
     */
    public function itemFulfillments(): HasMany
    {
        return $this->hasMany(OrderItemFulfillment::class);
    }
}
