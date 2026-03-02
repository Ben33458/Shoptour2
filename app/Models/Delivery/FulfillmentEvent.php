<?php

declare(strict_types=1);

namespace App\Models\Delivery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit event for a TourStop.
 *
 * Every meaningful action taken during or after delivery is recorded here.
 * Rows are NEVER updated or deleted.
 *
 * Event types:
 *   arrived            – driver scanned / tapped "arrived at customer"
 *   finished           – driver closed stop; triggers stock booking
 *   item_delivered     – qty delivered for a specific OrderItem
 *   item_not_delivered – qty NOT delivered with a reason code
 *   payment_recorded   – cash/card payment noted at the door
 *   empties_adjusted   – empty-returns correction (qty can be negative)
 *   breakage_adjusted  – breakage write-off
 *   note               – free-text note with no inventory effect
 *
 * payload_json schema examples:
 *   item_delivered:     {"order_item_id": 5, "qty": 3}
 *   item_not_delivered: {"order_item_id": 5, "qty": 1, "reason": "damaged", "note": "dented can"}
 *   empties_adjusted:   {"product_id": 12, "qty_delta": -6, "note": "6 broken bottles returned"}
 *   breakage_adjusted:  {"product_id": 12, "qty_delta": -2, "note": "2 broken during transport"}
 *
 * @property int         $id
 * @property int         $tour_stop_id
 * @property string      $event_type
 * @property array|null  $payload_json
 * @property int|null    $created_by_user_id
 * @property \Carbon\Carbon $created_at
 *
 * @property-read TourStop $tourStop
 */
class FulfillmentEvent extends Model
{
    // Append-only: no updated_at
    public const UPDATED_AT = null;

    // -------------------------------------------------------------------------
    // Event type constants
    // -------------------------------------------------------------------------
    public const TYPE_ARRIVED            = 'arrived';
    public const TYPE_FINISHED           = 'finished';
    public const TYPE_ITEM_DELIVERED     = 'item_delivered';
    public const TYPE_ITEM_NOT_DELIVERED = 'item_not_delivered';
    public const TYPE_PAYMENT_RECORDED   = 'payment_recorded';
    public const TYPE_EMPTIES_ADJUSTED   = 'empties_adjusted';
    public const TYPE_BREAKAGE_ADJUSTED  = 'breakage_adjusted';
    public const TYPE_NOTE               = 'note';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tour_stop_id',
        'event_type',
        'payload_json',
        'created_by_user_id',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'payload_json'       => 'array',
        'created_by_user_id' => 'integer',
        'created_at'         => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function tourStop(): BelongsTo
    {
        return $this->belongsTo(TourStop::class);
    }
}
