<?php

declare(strict_types=1);

namespace App\Models\Pricing;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a customer pricing group.
 *
 * Price adjustment logic (applied only when no explicit customer_price or
 * customer_group_price exists for the product):
 *
 *   "none"    → final_net = base_price_net_milli
 *   "fixed"   → final_net = base_price_net_milli + price_adjustment_fixed_milli
 *   "percent" → final_net = base_price_net_milli
 *                           × (1 + price_adjustment_percent_basis_points / 1_000_000)
 *               where 1_000_000 basis points = 100 %
 *               e.g.  50_000 bp =  5 % surcharge
 *                    -100_000 bp = 10 % discount
 *
 * "Heimdienst" (home-delivery service) is represented as a regular CustomerGroup
 * with an appropriate adjustment — no special entity is required.
 *
 * @property int    $id
 * @property string $name
 * @property string $price_adjustment_type               "none"|"fixed"|"percent"
 * @property int    $price_adjustment_fixed_milli
 * @property int    $price_adjustment_percent_basis_points
 * @property bool   $is_business
 * @property bool   $is_deposit_exempt
 * @property string $price_display_mode                     "netto"|"brutto"
 * @property bool   $active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Collection<int, CustomerGroupPrice> $groupPrices
 * @property-read Collection<int, Customer>           $customers
 */
class CustomerGroup extends Model
{
    /** Allowed values for price_adjustment_type */
    public const ADJUSTMENT_NONE    = 'none';
    public const ADJUSTMENT_FIXED   = 'fixed';
    public const ADJUSTMENT_PERCENT = 'percent';

    /** Allowed values for price_display_mode */
    public const DISPLAY_NETTO  = 'netto';
    public const DISPLAY_BRUTTO = 'brutto';

    /** All available payment methods */
    public const ALL_PAYMENT_METHODS = ['stripe', 'paypal', 'sepa', 'invoice', 'cash', 'ec'];

    protected $fillable = [
        'name',
        'price_adjustment_type',
        'price_adjustment_fixed_milli',
        'price_adjustment_percent_basis_points',
        'is_business',
        'is_deposit_exempt',
        'price_display_mode',
        'active',
        'allowed_payment_methods',
    ];

    protected $casts = [
        'price_adjustment_fixed_milli'           => 'integer',
        'price_adjustment_percent_basis_points'  => 'integer',
        'is_business'                            => 'boolean',
        'is_deposit_exempt'                      => 'boolean',
        'active'                                 => 'boolean',
        'allowed_payment_methods'                => 'array',
    ];

    /**
     * Get the payment methods allowed for this group.
     * Returns all methods if the column is null (no restriction).
     *
     * @return list<string>
     */
    public function getEffectivePaymentMethods(): array
    {
        $methods = $this->allowed_payment_methods;

        if ($methods === null || $methods === []) {
            return self::ALL_PAYMENT_METHODS;
        }

        return $methods;
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Explicit price overrides defined for this group.
     */
    public function groupPrices(): HasMany
    {
        return $this->hasMany(CustomerGroupPrice::class);
    }

    /**
     * All customers belonging to this group.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
