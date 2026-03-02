<?php

declare(strict_types=1);

namespace App\Models\Driver;

use Illuminate\Database\Eloquent\Model;

/**
 * Upload job for a driver proof-of-delivery or delivery note photo.
 *
 * Created server-side when an upload_requested driver_event is processed.
 * The actual file is uploaded later via POST /api/driver/upload.
 *
 * Idempotent by (device_id, client_upload_id).
 *
 * status:
 *   pending  – job created, file not yet received
 *   uploaded – file stored at file_path
 *   failed   – upload attempt failed
 *
 * @property int         $id
 * @property int|null    $employee_id
 * @property string      $device_id
 * @property string      $client_upload_id
 * @property int|null    $tour_stop_id
 * @property int|null    $order_id
 * @property string      $upload_type
 * @property string|null $file_path
 * @property string|null $mime_type
 * @property string|null $original_name
 * @property int|null    $file_size
 * @property string      $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DriverUpload extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_FAILED   = 'failed';

    public const TYPE_PROOF_OF_DELIVERY = 'proof_of_delivery';
    public const TYPE_DELIVERY_NOTE     = 'delivery_note';
    public const TYPE_OTHER             = 'other';

    protected $fillable = [
        'employee_id',
        'device_id',
        'client_upload_id',
        'tour_stop_id',
        'order_id',
        'upload_type',
        'file_path',
        'mime_type',
        'original_name',
        'file_size',
        'status',
    ];

    protected $casts = [
        'employee_id'  => 'integer',
        'tour_stop_id' => 'integer',
        'order_id'     => 'integer',
        'file_size'    => 'integer',
    ];
}
