<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Catalog\Product;
use App\Models\Pricing\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PROJ-20 — Customer-specific assortment / Stammsortiment entry.
 *
 * @property int      $id
 * @property int      $customer_id
 * @property int      $product_id
 * @property int      $sort_order
 * @property int      $target_stock_units   Sollbestand in Gebinden
 * @property int      $actual_stock_units   Istbestand in Gebinden
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 *
 * @property-read Customer $customer
 * @property-read Product  $product
 */
class CustomerFavorite extends Model
{
    protected $fillable = [
        'customer_id',
        'product_id',
        'sort_order',
        'target_stock_units',
        'actual_stock_units',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'sort_order'         => 'integer',
        'target_stock_units' => 'integer',
        'actual_stock_units' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * How many units to order: max(0, target - actual).
     */
    public function orderQty(): int
    {
        return max(0, $this->target_stock_units - $this->actual_stock_units);
    }

    /**
     * Whether the product is currently orderable (active, visible, not discontinued/out-of-stock).
     */
    public function isOrderable(): bool
    {
        $p = $this->product;
        if (! $p) {
            return false;
        }
        return $p->active
            && $p->show_in_shop
            && $p->availability_mode !== \App\Models\Catalog\Product::AVAILABILITY_DISCONTINUED
            && $p->availability_mode !== \App\Models\Catalog\Product::AVAILABILITY_OUT_OF_STOCK;
    }
}
