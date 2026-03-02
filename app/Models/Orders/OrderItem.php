<?php

declare(strict_types=1);

namespace App\Models\Orders;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductLmivVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line item inside an Order.
 *
 * All price and tax data is a frozen snapshot taken at order-creation time.
 * The values NEVER change even if the underlying product or pricing rules change.
 *
 * Tax rate snapshot strategy:
 *   tax_rate_id holds the products.tax_rate_id FK value frozen at order time;
 *   null when the Tax module is inactive or the product has no tax_rate_id.
 *   tax_rate_basis_points holds the effective rate in basis-points frozen at
 *   order time (e.g. 1_900 = 19 % German standard VAT, 700 = 7 %, scale: 10_000 = 100 %).
 *   Resolved from tax_rates via PricingRepository at order creation;
 *   throws RuntimeException if unresolvable — no silent default.
 *
 * Deposit (Pfand) snapshot strategy:
 *   pfand_set_id holds the pfand_set_id from the product's Gebinde frozen at
 *   order time; null when the product has no deposit obligation or the customer
 *   is deposit-exempt.
 *   unit_deposit_milli is the recursive brutto sum of all PfandItems reachable
 *   from that PfandSet tree; 0 when no deposit applies.
 *
 * @property int      $id
 * @property int      $order_id
 * @property int      $product_id
 * @property int      $unit_price_net_milli
 * @property int      $unit_price_gross_milli
 * @property string   $price_source
 * @property int|null $tax_rate_id            FK ref to tax_rates.id at order time (null = inactive)
 * @property int      $tax_rate_basis_points  Effective tax rate in basis-points (10_000 = 100 %)
 * @property int|null $pfand_set_id                  FK ref to pfand_sets.id at order time (null = none)
 * @property int      $unit_deposit_milli            Deposit per unit (milli-cents); 0 = no deposit
 * @property int      $deposit_tax_rate_basis_points BUG-4: VAT rate for deposit in bp; 0 = exempt / legacy
 * @property int|null $lmiv_version_id               WP-15: FK to product_lmiv_versions; null = no LMIV / legacy row
 * @property int      $qty
 * @property bool     $is_backorder
 * @property string   $product_name_snapshot
 * @property string   $artikelnummer_snapshot
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Order                                   $order
 * @property-read Product|null                            $product
 * @property-read Collection<int, OrderItemComponent>     $components
 * @property-read ProductLmivVersion|null                 $lmivVersion
 */
class OrderItem extends Model
{
    // -------------------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------------------

    /**
     * WP-15: When a new OrderItem is created, snapshot the currently active
     * LMIV version of the product (if the product has one).
     *
     * The lmiv_version_id can also be set explicitly by the caller; the boot
     * hook only fills it in when it would otherwise be null.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(static function (OrderItem $item): void {
            if ($item->lmiv_version_id !== null) {
                return; // already set by caller
            }

            if (! $item->product_id) {
                return;
            }

            $product = Product::find($item->product_id);
            if (! $product) {
                return;
            }

            // Resolve via direct base item or via self
            $targetId = $product->is_base_item
                ? $product->getKey()
                : $product->base_item_product_id;

            if (! $targetId) {
                return;
            }

            $activeVersion = ProductLmivVersion::where('product_id', $targetId)
                ->where('status', ProductLmivVersion::STATUS_ACTIVE)
                ->first();

            if ($activeVersion) {
                $item->lmiv_version_id = $activeVersion->id;
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'unit_price_net_milli',
        'unit_price_gross_milli',
        'price_source',
        'tax_rate_id',
        'tax_rate_basis_points',
        'pfand_set_id',
        'unit_deposit_milli',
        'deposit_tax_rate_basis_points',
        'qty',
        'is_backorder',
        'product_name_snapshot',
        'artikelnummer_snapshot',
        'lmiv_version_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'unit_price_net_milli'  => 'integer',
        'unit_price_gross_milli' => 'integer',
        'tax_rate_id'           => 'integer',
        'tax_rate_basis_points' => 'integer',
        'pfand_set_id'                   => 'integer',
        'unit_deposit_milli'             => 'integer',
        'deposit_tax_rate_basis_points'  => 'integer',
        'lmiv_version_id'                => 'integer',
        'qty'                   => 'integer',
        'is_backorder'          => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The parent order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The product (live reference; may return null if product was hard-deleted).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * WP-15: The LMIV version that was active when this order item was created.
     * Null for legacy rows or products without LMIV data.
     *
     * @return BelongsTo<ProductLmivVersion, self>
     */
    public function lmivVersion(): BelongsTo
    {
        return $this->belongsTo(ProductLmivVersion::class, 'lmiv_version_id');
    }

    /**
     * Resolved bundle components (populated only when the product was a bundle).
     *
     * @return HasMany<OrderItemComponent>
     */
    public function components(): HasMany
    {
        return $this->hasMany(OrderItemComponent::class);
    }

    // -------------------------------------------------------------------------
    // Computed helpers
    // -------------------------------------------------------------------------

    /**
     * Total net amount for this line: unit_price_net_milli × qty (milli-cents).
     */
    public function lineTotalNetMilli(): int
    {
        return $this->unit_price_net_milli * $this->qty;
    }

    /**
     * Total gross amount for this line: unit_price_gross_milli × qty (milli-cents).
     */
    public function lineTotalGrossMilli(): int
    {
        return $this->unit_price_gross_milli * $this->qty;
    }

    /**
     * Total deposit for this line: unit_deposit_milli × qty (milli-cents).
     */
    public function lineTotalPfandMilli(): int
    {
        return $this->unit_deposit_milli * $this->qty;
    }
}
