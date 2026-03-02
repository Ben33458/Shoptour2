<?php

declare(strict_types=1);

namespace App\Models\Shop;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PROJ-3: A single product line in a shopping cart.
 *
 * @property int    $id
 * @property int    $cart_id
 * @property int    $product_id
 * @property int    $quantity
 * @property int    $unit_price_gross_milli   Snapshot at time of adding
 * @property int    $pfand_milli              Snapshot at time of adding
 * @property int|null $company_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Cart    $cart
 * @property-read Product $product
 */
class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'unit_price_gross_milli',
        'pfand_milli',
        'company_id',
    ];

    protected $casts = [
        'quantity'                => 'integer',
        'unit_price_gross_milli' => 'integer',
        'pfand_milli'            => 'integer',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
