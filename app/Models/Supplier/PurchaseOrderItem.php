<?php

declare(strict_types=1);

namespace App\Models\Supplier;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line item on a purchase order.
 *
 * received_qty is filled during goods receipt via kolabri:po:receive.
 * When null the item has not yet been processed at the goods-receiving desk.
 *
 * @property int        $id
 * @property int        $purchase_order_id
 * @property int        $product_id
 * @property float      $qty               Ordered quantity
 * @property int        $unit_purchase_milli  Price per unit in milli-cents
 * @property int        $line_total_milli     qty × unit_purchase_milli
 * @property float|null $received_qty         Filled on receipt; null = pending
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read PurchaseOrder $purchaseOrder
 * @property-read Product       $product
 */
class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'qty',
        'unit_purchase_milli',
        'line_total_milli',
        'received_qty',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'qty'                  => 'float',
        'unit_purchase_milli'  => 'integer',
        'line_total_milli'     => 'integer',
        'received_qty'         => 'float',
        'sort_order'           => 'integer',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
