<?php

declare(strict_types=1);

namespace App\Models\Supplier;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Associates a supplier with a product they can supply, along with
 * ordering constraints used by the replenishment suggestion engine.
 *
 * @property int         $id
 * @property int         $supplier_id
 * @property int         $product_id
 * @property string|null $supplier_sku       Supplier's own article number
 * @property float       $min_order_qty      Minimum units per purchase order
 * @property float       $pack_size          Units per pack; suggested qty rounded up
 * @property int         $lead_time_days     Typical calendar days until delivery
 * @property int         $purchase_price_milli  Latest known price (milli-cents/unit)
 * @property bool        $active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Supplier $supplier
 * @property-read Product  $product
 */
class SupplierProduct extends Model
{
    protected $fillable = [
        'supplier_id',
        'product_id',
        'supplier_sku',
        'min_order_qty',
        'pack_size',
        'lead_time_days',
        'purchase_price_milli',
        'active',
    ];

    protected $casts = [
        'min_order_qty'        => 'float',
        'pack_size'            => 'float',
        'lead_time_days'       => 'integer',
        'purchase_price_milli' => 'integer',
        'active'               => 'boolean',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // =========================================================================
    // Domain helpers
    // =========================================================================

    /**
     * Round a desired quantity up to the nearest whole pack.
     */
    public function roundToPackSize(float $qty): float
    {
        if ($this->pack_size <= 0) {
            return $qty;
        }
        return ceil($qty / $this->pack_size) * $this->pack_size;
    }

    /**
     * Ensure at least min_order_qty, then round to pack size.
     */
    public function suggestQty(float $needed): float
    {
        $qty = max($needed, $this->min_order_qty);
        return $this->roundToPackSize($qty);
    }
}
