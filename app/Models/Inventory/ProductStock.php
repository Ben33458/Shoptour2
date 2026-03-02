<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Current stock snapshot for one product in one warehouse.
 *
 * quantity may be negative (oversold / backorder).
 * reserved_quantity is always >= 0 and represents quantity committed
 * to open orders that has not yet been physically dispatched.
 *
 * available = quantity - reserved_quantity
 *
 * This row is upserted atomically inside a DB transaction by StockService.
 * Never update it directly — always go through StockService.
 *
 * @property int    $id
 * @property int    $product_id
 * @property int    $warehouse_id
 * @property float  $quantity           Decimal 14,3 — may be negative
 * @property float  $reserved_quantity  Decimal 14,3 — always >= 0
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Product   $product
 * @property-read Warehouse $warehouse
 */
class ProductStock extends Model
{
    /**
     * No created_at on this table — only updated_at.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'reserved_quantity',
        'reorder_point',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'quantity'          => 'float',
        'reserved_quantity' => 'float',
        'updated_at'        => 'datetime',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * @return BelongsTo<Product, ProductStock>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Warehouse, ProductStock>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // =========================================================================
    // Domain helpers
    // =========================================================================

    /**
     * Quantity immediately available for new orders.
     *
     * May be negative if more stock has been reserved than is physically present.
     */
    public function availableQuantity(): float
    {
        return $this->quantity - $this->reserved_quantity;
    }
}
