<?php

declare(strict_types=1);

namespace App\Models\Delivery;

use App\Models\Orders\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Running delivery summary for one OrderItem at a specific TourStop.
 *
 * This is the mutable "scoreboard" row — updated by FulfillmentService as the
 * driver records deliveries. It is complemented by the append-only
 * FulfillmentEvent log which provides full history.
 *
 * Design:
 *   - No created_at (row is upserted on first delivery record).
 *   - updated_at reflects the last FulfillmentService write.
 *   - delivered_qty + not_delivered_qty may be less than order_item.qty
 *     when a partial delivery is still in progress.
 *
 * @property int         $id
 * @property int         $tour_stop_id
 * @property int         $order_item_id
 * @property int         $delivered_qty
 * @property int         $not_delivered_qty
 * @property string|null $not_delivered_reason
 * @property string|null $note
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read TourStop  $tourStop
 * @property-read OrderItem $orderItem
 */
class OrderItemFulfillment extends Model
{
    // No created_at on this table
    public const CREATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tour_stop_id',
        'order_item_id',
        'delivered_qty',
        'not_delivered_qty',
        'not_delivered_reason',
        'note',
        'updated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'delivered_qty'     => 'integer',
        'not_delivered_qty' => 'integer',
        'updated_at'        => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function tourStop(): BelongsTo
    {
        return $this->belongsTo(TourStop::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
