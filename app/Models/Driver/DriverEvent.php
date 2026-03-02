<?php

declare(strict_types=1);

namespace App\Models\Driver;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only driver event log.
 *
 * Records every action that occurs during a driver's delivery run.
 * Idempotent by (device_id, client_event_id) — duplicate syncs are safe.
 *
 * apply_status:
 *   pending  – received but not yet processed
 *   applied  – domain logic ran successfully
 *   rejected – validation or domain error (see apply_error)
 *
 * @property int         $id
 * @property int|null    $employee_id
 * @property string      $device_id
 * @property string      $client_event_id
 * @property string      $event_type
 * @property int|null    $tour_id
 * @property int|null    $tour_stop_id
 * @property int|null    $order_id
 * @property int|null    $order_item_id
 * @property array|null  $payload_json
 * @property \Carbon\Carbon $received_at
 * @property \Carbon\Carbon|null $applied_at
 * @property string      $apply_status
 * @property string|null $apply_error
 */
class DriverEvent extends Model
{
    // Event type constants — matches the driver PWA event vocabulary
    public const TYPE_ARRIVED              = 'arrived';
    public const TYPE_FINISHED             = 'finished';
    public const TYPE_ITEM_DELIVERED       = 'item_delivered';
    public const TYPE_ITEM_NOT_DELIVERED   = 'item_not_delivered';
    public const TYPE_PAYMENT              = 'payment';
    public const TYPE_EMPTIES_ADJUSTMENT   = 'empties_adjustment';
    public const TYPE_BREAKAGE_ADJUSTMENT  = 'breakage_adjustment';
    public const TYPE_NOTE                 = 'note';
    public const TYPE_UPLOAD_REQUESTED     = 'upload_requested';
    /**
     * References a completed DriverUpload by payload.upload_id.
     * Sent after a successful POST /api/driver/upload so the upload
     * becomes part of the driver event log.
     */
    public const TYPE_UPLOAD               = 'upload';

    // apply_status values
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPLIED  = 'applied';
    public const STATUS_REJECTED = 'rejected';

    // No updated_at — this is append-only
    public const UPDATED_AT = null;

    public $timestamps = false; // We manage received_at manually

    protected $fillable = [
        'employee_id',
        'device_id',
        'client_event_id',
        'event_type',
        'tour_id',
        'tour_stop_id',
        'order_id',
        'order_item_id',
        'payload_json',
        'received_at',
        'applied_at',
        'apply_status',
        'apply_error',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'received_at'  => 'datetime',
        'applied_at'   => 'datetime',
        'employee_id'  => 'integer',
        'tour_id'      => 'integer',
        'tour_stop_id' => 'integer',
        'order_id'     => 'integer',
        'order_item_id' => 'integer',
    ];
}
