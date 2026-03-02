<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\Orders\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only closeout adjustment on an order.
 *
 * @property int         $id
 * @property int         $order_id
 * @property string      $adjustment_type   leergut|bruch
 * @property int|null    $product_id
 * @property int|null    $gebinde_id
 * @property string|null $reference_label
 * @property int         $qty
 * @property int         $amount_milli       milli-cents (negative = credit)
 * @property string|null $note
 * @property int|null    $created_by_user_id
 * @property \Carbon\Carbon $created_at
 */
class OrderAdjustment extends Model
{
    public const TYPE_LEERGUT = 'leergut';
    public const TYPE_BRUCH   = 'bruch';

    public const TYPES = [self::TYPE_LEERGUT, self::TYPE_BRUCH];

    // Append-only: no updated_at
    public $timestamps = false;
    protected $dateFormat = 'Y-m-d H:i:s';

    protected $fillable = [
        'order_id',
        'adjustment_type',
        'product_id',
        'gebinde_id',
        'reference_label',
        'qty',
        'amount_milli',
        'note',
        'created_by_user_id',
        'created_at',
    ];

    protected $casts = [
        'qty'          => 'integer',
        'amount_milli' => 'integer',
        'created_at'   => 'datetime',
    ];

    // Force useCurrent timestamp handling
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
