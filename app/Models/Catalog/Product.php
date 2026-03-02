<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockMovement;
use App\Models\Pricing\TaxRate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Represents a product in the Kolabri Getränke catalog.
 *
 * A product can be either a regular single product or a bundle (is_bundle = true).
 * Bundles are composed of child products via the product_components pivot table.
 * Bundles may themselves contain other bundles (nested bundles), forming a tree.
 * The resolveBundleComponentsRecursive() method flattens this tree safely.
 *
 * WP-15 – Base item / LMIV:
 * A product with is_base_item = true is the "canonical" article that holds LMIV
 * (Lebensmittelinformationen) data via ProductLmivVersion records.
 * Regular / EAN-variant products can point to their base item via
 * base_item_product_id.  The LMIV version that was active at the time of sale
 * is snapshotted on OrderItem.lmiv_version_id.
 *
 * All monetary values (base_price_net_milli, base_price_gross_milli) are stored
 * as milli-cents (integer × 1/1000 cent) to avoid floating-point rounding errors.
 * Divide by 1_000_000 to get the EUR amount, or by 100_000 to get cent amount.
 *
 * Availability modes (availability_mode):
 *   "available"    – orderable immediately
 *   "preorder"     – can be pre-ordered; uses preorder_lead_days / preorder_note
 *   "out_of_stock" – temporarily unavailable
 *   "discontinued" – no longer sold
 *
 * @property int         $id
 * @property int         $brand_id
 * @property int         $product_line_id
 * @property int         $category_id
 * @property int|null    $warengruppe_id
 * @property int         $gebinde_id
 * @property int         $tax_rate_id
 * @property string      $artikelnummer
 * @property string      $slug
 * @property string      $produktname
 * @property int         $base_price_net_milli
 * @property int         $base_price_gross_milli
 * @property bool        $is_bundle
 * @property string      $availability_mode
 * @property int|null    $preorder_lead_days
 * @property string|null $preorder_note
 * @property string|null $sales_unit_note
 * @property bool        $active
 * @property bool        $show_in_shop
 * @property bool        $is_base_item
 * @property int|null    $base_item_product_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read TaxRate                                                            $taxRate
 * @property-read Brand                                                              $brand
 * @property-read ProductLine                                                        $productLine
 * @property-read Category                                                           $category
 * @property-read Warengruppe|null                                                   $warengruppe
 * @property-read Gebinde                                                            $gebinde
 * @property-read Collection<int, ProductBarcode>                                    $barcodes
 * @property-read Collection<int, ProductComponent>                                  $componentLines
 * @property-read Collection<int, ProductComponent>                                  $parentComponentLines
 * @property-read Collection<int, ProductStock>                                      $stocks
 * @property-read Collection<int, StockMovement>                                     $stockMovements
 * @property-read Collection<int, ProductLmivVersion>                                $lmivVersions
 * @property-read ProductLmivVersion|null                                            $activeLmivVersion
 * @property-read Product|null                                                       $baseItem
 * @property-read Collection<int, Product>                                           $derivedProducts
 */
class Product extends Model
{
    /** Allowed values for availability_mode */
    public const AVAILABILITY_AVAILABLE    = 'available';
    public const AVAILABILITY_PREORDER     = 'preorder';
    public const AVAILABILITY_OUT_OF_STOCK = 'out_of_stock';
    public const AVAILABILITY_DISCONTINUED = 'discontinued';
    /**
     * Stock-based availability: OrderService checks live warehouse stock via
     * StockService::getAvailableQuantity() and sets is_backorder = true on the
     * order item when the requested qty exceeds what is on hand.
     * The order still proceeds — negative stock (backorder) is allowed.
     */
    public const AVAILABILITY_STOCK_BASED  = 'stock_based';

    /**
     * Resolve route bindings by slug instead of ID (PROJ-2).
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'brand_id',
        'product_line_id',
        'category_id',
        'gebinde_id',
        'warengruppe_id',
        'tax_rate_id',
        'artikelnummer',
        'slug',
        'produktname',
        'base_price_net_milli',
        'base_price_gross_milli',
        'is_bundle',
        'availability_mode',
        'preorder_lead_days',
        'preorder_note',
        'sales_unit_note',
        'active',
        'show_in_shop',
        'is_base_item',
        'base_item_product_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_bundle'              => 'boolean',
        'active'                 => 'boolean',
        'show_in_shop'           => 'boolean',
        'is_base_item'           => 'boolean',
        'base_price_net_milli'   => 'integer',
        'base_price_gross_milli' => 'integer',
        'preorder_lead_days'     => 'integer',
        'tax_rate_id'            => 'integer',
        'base_item_product_id'   => 'integer',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * The VAT / tax rate that applies to this product.
     *
     * This is the single source of truth for the tax rate used in all price
     * calculations. Always eager-load this relation when passing a Product
     * to PriceResolverService (e.g. Product::with('taxRate')->find($id)).
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * The brand this product is sold under.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * The product line this product belongs to.
     */
    public function productLine(): BelongsTo
    {
        return $this->belongsTo(ProductLine::class);
    }

    /**
     * The catalog category this product is filed under.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The Warengruppe (product group) this product optionally belongs to.
     */
    public function warengruppe(): BelongsTo
    {
        return $this->belongsTo(Warengruppe::class, 'warengruppe_id');
    }

    /**
     * The packaging unit (Gebinde) used for this product.
     */
    public function gebinde(): BelongsTo
    {
        return $this->belongsTo(Gebinde::class);
    }

    /**
     * All barcodes (EAN, ITF-14, etc.) associated with this product.
     */
    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    /**
     * The ProductComponent pivot rows where this product is the PARENT (bundle).
     * Each row links this bundle to one child product with a quantity.
     *
     * Use components() for the direct child Product collection.
     */
    public function componentLines(): HasMany
    {
        return $this->hasMany(ProductComponent::class, 'parent_product_id');
    }

    /**
     * The ProductComponent pivot rows where this product is a CHILD.
     * Useful for finding which bundles contain this product.
     */
    public function parentComponentLines(): HasMany
    {
        return $this->hasMany(ProductComponent::class, 'child_product_id');
    }

    /**
     * Current stock snapshots for this product across all warehouses.
     *
     * @return HasMany<ProductStock>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    /**
     * Full movement journal for this product across all warehouses.
     *
     * @return HasMany<StockMovement>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // ── WP-15 LMIV & Base-item relationships ─────────────────────────────────

    /**
     * All LMIV versions for this (base-item) product, ordered by version number.
     *
     * @return HasMany<ProductLmivVersion>
     */
    public function lmivVersions(): HasMany
    {
        return $this->hasMany(ProductLmivVersion::class, 'product_id')
                    ->orderBy('version_number');
    }

    /**
     * The currently active LMIV version for this product (status = active).
     * Returns null when no active version exists (new product, never published, etc.)
     *
     * @return HasOne<ProductLmivVersion>
     */
    public function activeLmivVersion(): HasOne
    {
        return $this->hasOne(ProductLmivVersion::class, 'product_id')
                    ->where('status', ProductLmivVersion::STATUS_ACTIVE);
    }

    /**
     * The base item this product is derived from.
     * Null when this IS the base item or when not linked to any base item.
     *
     * @return BelongsTo<Product, self>
     */
    public function baseItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_item_product_id', 'id');
    }

    /**
     * Products that list this product as their base item.
     *
     * @return HasMany<Product>
     */
    public function derivedProducts(): HasMany
    {
        return $this->hasMany(self::class, 'base_item_product_id', 'id');
    }

    // ── WP-21 Product images ──────────────────────────────────────────────────

    /**
     * All gallery images ordered by sort_order ascending.
     *
     * @return HasMany<ProductImage>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * The main (first) product image.
     *
     * @return HasOne<ProductImage>
     */
    public function mainImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->orderBy('sort_order');
    }

    // =========================================================================
    // Domain Helpers
    // =========================================================================

    /**
     * Returns true when this product carries LMIV data (is_base_item = true).
     */
    public function isBaseItem(): bool
    {
        return $this->is_base_item;
    }

    /**
     * Returns true when the product's availability depends on warehouse stock levels.
     */
    public function isStockBased(): bool
    {
        return $this->availability_mode === self::AVAILABILITY_STOCK_BASED;
    }

    /**
     * Returns the total current stock quantity across all warehouses.
     */
    public function currentStock(): int
    {
        return (int) $this->stocks->sum('quantity');
    }

    /**
     * Returns true when this product is a bundle composed of child products.
     *
     * A bundle's price is typically derived from its components. Bundles may
     * also carry their own override price set via base_price_net_milli.
     */
    public function isBundle(): bool
    {
        return $this->is_bundle;
    }

    /**
     * Returns the direct child ProductComponent lines of this bundle.
     *
     * This is the one-level-deep composition. For a fully flattened view
     * across nested bundles use resolveBundleComponentsRecursive().
     *
     * Returns an empty collection when this product is not a bundle.
     *
     * @return Collection<int, ProductComponent>
     */
    public function components(): Collection
    {
        if (! $this->isBundle()) {
            return new Collection();
        }

        return $this->componentLines()->with('childProduct')->get();
    }

    /**
     * Recursively resolves all leaf-level products within this bundle,
     * accumulating and multiplying quantities through nested bundle levels.
     *
     * Algorithm:
     *   For each direct component of this product:
     *     - If the component is itself a bundle, recurse into it and multiply
     *       the returned leaf quantities by this level's qty.
     *     - If the component is a leaf product, add it to the flat result map.
     *   After full traversal, return an array of [product_id => ['product', 'qty']].
     *
     * Cycle protection:
     *   A $visited set tracks product IDs already traversed in the current
     *   call stack. If a product ID is encountered that is already in $visited,
     *   the recursion stops for that branch and a warning is logged. This
     *   prevents infinite loops caused by circular bundle definitions.
     *
     * Example:
     *   "Mixkasten" (bundle)
     *     → 2× "Paulaner Weizen 6er-Träger" (bundle)
     *         → 6× "Paulaner Weizen 0,5l Flasche" (leaf)
     *     → 1× "Bitburger Premium 0,33l Dose" (leaf)
     *
     *   Result: [
     *     {product: Paulaner Weizen 0,5l Flasche, qty: 12},  // 2 × 6
     *     {product: Bitburger Premium 0,33l Dose, qty: 1},
     *   ]
     *
     * @param  array<int, true>  $visited  Product IDs already visited (internal cycle guard)
     * @return array<int, array{product: Product, qty: int}>  Keyed by product_id
     */
    public function resolveBundleComponentsRecursive(array $visited = []): array
    {
        if (! $this->isBundle()) {
            return [];
        }

        // Guard: mark this product as visited before descending
        if (isset($visited[$this->id])) {
            // Circular reference detected — log and abort this branch
            \Illuminate\Support\Facades\Log::warning(
                'Circular bundle reference detected',
                ['product_id' => $this->id, 'artikelnummer' => $this->artikelnummer]
            );
            return [];
        }

        $visited[$this->id] = true;

        /** @var array<int, array{product: Product, qty: int}> $flat */
        $flat = [];

        $componentLines = $this->componentLines()->with('childProduct')->get();

        foreach ($componentLines as $line) {
            $child    = $line->childProduct;
            $lineQty  = $line->qty;

            if ($child->isBundle()) {
                // Recurse into the nested bundle, passing along the visited set
                $nested = $child->resolveBundleComponentsRecursive($visited);

                foreach ($nested as $childId => $entry) {
                    if (isset($flat[$childId])) {
                        // Accumulate quantity for a product that appears in multiple sub-bundles
                        $flat[$childId]['qty'] += $entry['qty'] * $lineQty;
                    } else {
                        $flat[$childId] = [
                            'product' => $entry['product'],
                            'qty'     => $entry['qty'] * $lineQty,
                        ];
                    }
                }
            } else {
                // Leaf product — add or accumulate in the flat map
                if (isset($flat[$child->id])) {
                    $flat[$child->id]['qty'] += $lineQty;
                } else {
                    $flat[$child->id] = [
                        'product' => $child,
                        'qty'     => $lineQty,
                    ];
                }
            }
        }

        return $flat;
    }
}
