<?php

declare(strict_types=1);

namespace App\Models\Pricing;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An explicit price override for a specific CustomerGroup + Product combination.
 *
 * Priority: lower than CustomerPrice, higher than base_price + adjustment.
 * When a row exists the price is used as-is; the service derives the gross amount
 * from price_net_milli + tax — no separate gross column exists.
 * No further group adjustment is applied on top.
 *
 * BUG-3 fix: valid_from / valid_to are now evaluated in EloquentPricingRepository.
 * BUG-6 fix: company_id added for multi-tenant isolation.
 *
 * @property int                  $id
 * @property int|null             $company_id
 * @property int                  $customer_group_id
 * @property int                  $product_id
 * @property int                  $price_net_milli
 * @property \Carbon\Carbon|null  $valid_from
 * @property \Carbon\Carbon|null  $valid_to
 * @property \Carbon\Carbon       $created_at
 * @property \Carbon\Carbon       $updated_at
 *
 * @property-read CustomerGroup $customerGroup
 * @property-read Product       $product
 */
class CustomerGroupPrice extends Model
{
    protected $fillable = [
        'company_id',
        'customer_group_id',
        'product_id',
        'price_net_milli',
        // price_gross_milli intentionally excluded: PriceResolverService always
        // derives gross from net + tax; storing gross here would create stale data.
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'price_net_milli' => 'integer',
        'valid_from'      => 'datetime',
        'valid_to'        => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
