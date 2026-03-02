<?php

declare(strict_types=1);

namespace App\Models\Orders;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A frozen snapshot of one resolved leaf component inside a bundle OrderItem.
 *
 * When an order item is for a bundle product, OrderService resolves the full
 * component tree via Product::resolveBundleComponentsRecursive() and stores
 * one row here per unique leaf product.
 *
 * qty_per_bundle reflects the fully-flattened multiplied quantity
 * (i.e. already accounts for nested bundle levels).
 *
 * qty_total = order_item.qty × qty_per_bundle
 *
 * These records are purely for historic reference and stock-movement logging.
 * They are NOT re-computed after order creation.
 *
 * @property int    $id
 * @property int    $order_item_id
 * @property int    $component_product_id
 * @property string $component_product_name_snapshot
 * @property string $component_artikelnummer_snapshot
 * @property int    $qty_per_bundle
 * @property int    $qty_total
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read OrderItem    $orderItem
 * @property-read Product|null $componentProduct
 */
class OrderItemComponent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_item_id',
        'component_product_id',
        'component_product_name_snapshot',
        'component_artikelnummer_snapshot',
        'qty_per_bundle',
        'qty_total',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'qty_per_bundle' => 'integer',
        'qty_total'      => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The bundle order item this component snapshot belongs to.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * The live product record for this component (may be null if hard-deleted).
     */
    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
