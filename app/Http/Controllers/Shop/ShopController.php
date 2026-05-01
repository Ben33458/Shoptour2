<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Gebinde;
use App\Models\Catalog\Product;
use App\Models\Catalog\Warengruppe;
use App\Models\CustomerFavorite;
use App\Models\Pricing\AppSetting;
use App\Models\Pricing\CustomerGroup;
use App\Services\Orders\PfandCalculator;
use App\Services\Pricing\PriceResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * PROJ-2 — Public-facing shop: product listing and detail pages.
 *
 * Supports filtering by category, brand, gebinde, warengruppe,
 * full-text search (name + artikelnummer + barcode), and caching.
 *
 * Prices are resolved via PriceResolverService:
 *   - Guests: default customer group (AppSetting.default_customer_group_id)
 *   - Logged-in customers: their own customer group + individual prices
 */
class ShopController extends Controller
{
    public function __construct(
        private readonly PriceResolverService $priceResolver,
        private readonly PfandCalculator      $pfandCalculator,
    ) {}

    // =========================================================================
    // GET /produkte  — product listing with filters, search, caching
    // =========================================================================

    public function index(Request $request): View
    {
        // ── Collect filter parameters ────────────────────────────────────────
        $categoryId    = $request->integer('kategorie') ?: null;
        $brandId       = $request->integer('brand') ?: null;
        $gebindeId     = $request->integer('gebinde') ?: null;
        $warengruppeId = $request->integer('warengruppe') ?: null;
        $search        = $request->string('suche')->trim()->value();
        $sort          = $request->string('sort')->value() ?: 'name';

        // ── Resolve customer / customer group for pricing + cache key ────────
        $customer      = $this->resolveCustomer();
        $customerGroup = $this->resolveCustomerGroup($customer);

        // ── Resolve display preferences ──────────────────────────────────────
        $displaySettings = $this->resolveDisplaySettings($customer, $request);
        $groupId       = $customerGroup?->id ?? 0;

        // ── Cache key ────────────────────────────────────────────────────────
        $filters = compact('categoryId', 'brandId', 'gebindeId', 'warengruppeId', 'search', 'sort');
        $filterHash = md5(serialize($filters));
        $page       = $request->integer('page', 1);

        $cachePrefix = $customer ? "products.cg{$groupId}" : 'products.guest';
        $cacheKey    = "{$cachePrefix}.{$filterHash}.page{$page}";

        // ── Cached product query ─────────────────────────────────────────────
        $itemsPerPage = $displaySettings['items_per_page'];

        $products = Cache::remember($cacheKey, 300, function () use (
            $categoryId, $brandId, $gebindeId, $warengruppeId, $search, $sort, $page, $itemsPerPage
        ) {
            $query = Product::with([
                'brand',
                'productLine',
                'category',
                'gebinde',
                'warengruppe',
                'mainImage',
                'stocks',
            ])
                ->where('active', true)
                ->where('show_in_shop', true)
                ->whereNotIn('availability_mode', [Product::AVAILABILITY_DISCONTINUED]);

            // ── Filters ──────────────────────────────────────────────────────
            if ($categoryId) {
                // Include child categories (one level deep)
                $categoryIds = Category::where('id', $categoryId)
                    ->orWhere('parent_id', $categoryId)
                    ->pluck('id');
                $query->whereIn('category_id', $categoryIds);
            }

            if ($brandId) {
                $query->where('brand_id', $brandId);
            }

            if ($gebindeId) {
                $query->where('gebinde_id', $gebindeId);
            }

            if ($warengruppeId) {
                $query->where('warengruppe_id', $warengruppeId);
            }

            // ── Full-text search (name + artikelnummer + barcode) ─────────────
            if ($search !== '') {
                $query->where(function ($q) use ($search): void {
                    $q->where('produktname', 'like', "%{$search}%")
                      ->orWhere('artikelnummer', 'like', "%{$search}%")
                      ->orWhereHas('barcodes', function ($bq) use ($search): void {
                          $bq->where('barcode', 'like', "%{$search}%");
                      });
                });
            }

            // ── Sorting ──────────────────────────────────────────────────────
            match ($sort) {
                'preis'     => $query->orderBy('base_price_gross_milli'),
                'preis-desc' => $query->orderByDesc('base_price_gross_milli'),
                'relevanz'  => $search !== '' ? $query->orderByRaw("CASE WHEN produktname LIKE ? THEN 0 ELSE 1 END", ["%{$search}%"])->orderBy('produktname') : $query->orderBy('produktname'),
                default     => $query->orderBy('produktname'),
            };

            return $query->paginate($itemsPerPage, ['*'], 'page', $page)->withQueryString();
        });

        // ── Load filter sidebar data ─────────────────────────────────────────
        $categories    = Category::whereNull('parent_id')->with('children')->orderBy('name')->get();
        $brands        = Brand::orderBy('name')->get();
        $gebindeList   = Gebinde::where('active', true)->orderBy('name')->get();
        $warengruppen  = Warengruppe::where('active', true)->withCount('products')->orderBy('name')->get();

        // ── Compute prices and pfand per product ─────────────────────────────
        $priceData       = [];
        $priceDisplayMode = $customer?->price_display_mode
            ?: ($customerGroup?->price_display_mode ?? CustomerGroup::DISPLAY_BRUTTO);
        $isBusiness       = $customerGroup?->is_business ?? false;

        foreach ($products as $product) {
            try {
                $price = $customer
                    ? $this->priceResolver->resolveForCustomer($product, $customer)
                    : $this->priceResolver->resolveForGuest($product);
                $pfand = $product->gebinde
                    ? $this->pfandCalculator->totalForGebinde($product->gebinde)
                    : 0;
            } catch (\Throwable) {
                $price = null;
                $pfand = 0;
            }

            // Stock-based availability check
            $stockAvailable = true;
            if ($product->availability_mode === Product::AVAILABILITY_STOCK_BASED) {
                $currentStock = $product->stocks->sum('quantity') ?? 0;
                if ($currentStock <= 0) {
                    $stockAvailable = false;
                }
            }

            $priceData[$product->id] = [
                'price'           => $price,
                'pfand'           => $pfand,
                'stock_available' => $stockAvailable,
            ];
        }

        // Favorite product IDs for the heart button (authenticated customers only)
        $favoriteProductIds = [];
        if ($customer) {
            $favoriteProductIds = CustomerFavorite::where('customer_id', $customer->id)
                ->pluck('product_id')
                ->flip()
                ->all();
        }

        return view('shop.index', compact(
            'products',
            'categories',
            'brands',
            'gebindeList',
            'warengruppen',
            'priceData',
            'priceDisplayMode',
            'isBusiness',
            'categoryId',
            'brandId',
            'gebindeId',
            'warengruppeId',
            'search',
            'sort',
            'favoriteProductIds',
            'displaySettings',
        ));
    }

    // =========================================================================
    // GET /produkte/{product}  — product detail page (slug-based)
    // =========================================================================

    public function show(Product $product): View
    {
        if (! $product->active || ! $product->show_in_shop || $product->availability_mode === Product::AVAILABILITY_DISCONTINUED) {
            abort(404);
        }

        $product->load([
            'images',
            'brand',
            'productLine',
            'category.parent',
            'gebinde.pfandSet.components.pfandItem',
            'gebinde.pfandSet.components.childPfandSet',
            'taxRate',
            'activeLmivVersion',
            'baseItem.activeLmivVersion',
            'barcodes',
            'stocks',
        ]);

        // Load bundle components if applicable
        $bundleComponents = [];
        if ($product->isBundle()) {
            $bundleComponents = $product->resolveBundleComponentsRecursive();
        }

        $customer      = $this->resolveCustomer();
        $customerGroup = $this->resolveCustomerGroup($customer);

        try {
            $price = $customer
                ? $this->priceResolver->resolveForCustomer($product, $customer)
                : $this->priceResolver->resolveForGuest($product);
        } catch (\Throwable) {
            $price = null;
        }

        $pfand = $product->gebinde
            ? $this->pfandCalculator->totalForGebinde($product->gebinde)
            : 0;

        $priceDisplayMode = $customer?->price_display_mode
            ?: ($customerGroup?->price_display_mode ?? CustomerGroup::DISPLAY_BRUTTO);
        $isBusiness       = $customerGroup?->is_business ?? false;

        // Stock-based availability check
        $stockAvailable = true;
        if ($product->availability_mode === Product::AVAILABILITY_STOCK_BASED) {
            $stockAvailable = $product->stocks->sum('quantity') > 0;
        }
        if ($product->availability_mode === Product::AVAILABILITY_OUT_OF_STOCK) {
            $stockAvailable = false;
        }

        // SEO: Schema.org JSON-LD (pass real stock status)
        $schemaOrg = $this->buildSchemaOrg($product, $price, $pfand, $stockAvailable);

        $isFavorite = false;
        if ($customer) {
            $isFavorite = CustomerFavorite::where('customer_id', $customer->id)
                ->where('product_id', $product->id)
                ->exists();
        }

        return view('shop.product', compact(
            'product',
            'price',
            'pfand',
            'priceDisplayMode',
            'isBusiness',
            'bundleComponents',
            'schemaOrg',
            'stockAvailable',
            'isFavorite',
        ));
    }

    // =========================================================================
    // POST /ansicht  — update display preferences (guests: session; customers: DB)
    // =========================================================================

    public function updateDisplayPreferences(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $availableViews = json_decode(
            AppSetting::get('shop.display.available_views', '["grid_large","grid_compact","list_images","list_no_images","table"]'),
            true
        ) ?: ['grid_large'];

        $validated = $request->validate([
            'view_mode'      => ['required', 'string', 'in:' . implode(',', $availableViews)],
            'items_per_page' => ['required', 'integer', 'in:24,48,96'],
        ]);

        $customer = $this->resolveCustomer();

        if ($customer) {
            $prefs = $customer->display_preferences ?? [];
            $customer->update([
                'display_preferences' => array_merge($prefs, [
                    'view_mode'      => $validated['view_mode'],
                    'items_per_page' => (int) $validated['items_per_page'],
                ]),
            ]);
        } else {
            $prefs = session('shop_display_prefs', []);
            session(['shop_display_prefs' => array_merge($prefs, [
                'view_mode'      => $validated['view_mode'],
                'items_per_page' => (int) $validated['items_per_page'],
            ])]);
        }

        return redirect()->back();
    }

    // =========================================================================
    // GET /p/{id}  — 301 redirect from numeric ID to slug
    // =========================================================================

    public function redirectById(int $id): RedirectResponse
    {
        $product = Product::findOrFail($id);

        return redirect()->route('shop.product', $product->slug, 301);
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Resolve the effective display settings for this request.
     * Priority: customer preferences > session (guest) > admin defaults.
     *
     * @return array{view_mode:string, items_per_page:int, show_article_number:bool, show_deposit_separately:bool, description_mode:string, hide_unavailable:bool, show_stammsortiment_badge:bool, show_new_badge:bool, available_views:list<string>}
     */
    private function resolveDisplaySettings(?\App\Models\Pricing\Customer $customer, \Illuminate\Http\Request $request): array
    {
        $availableViews   = json_decode(AppSetting::get('shop.display.available_views', '["grid_large","grid_compact","list_images","list_no_images","table"]'), true) ?: ['grid_large'];
        $defaultView      = AppSetting::get('shop.display.default_view', 'grid_large');
        $defaultPerPage   = (int) AppSetting::get('shop.display.default_items_per_page', '24');

        // Customer or session preference
        $prefs = $customer?->display_preferences ?? session('shop_display_prefs', []);

        $viewMode   = $prefs['view_mode'] ?? $defaultView;
        $perPage    = (int) ($prefs['items_per_page'] ?? $defaultPerPage);

        // Guard: ensure chosen view is still enabled
        if (! in_array($viewMode, $availableViews, true)) {
            $viewMode = $defaultView;
        }
        if (! in_array($perPage, [24, 48, 96], true)) {
            $perPage = $defaultPerPage;
        }

        return [
            'view_mode'               => $viewMode,
            'items_per_page'          => $perPage,
            'available_views'         => $availableViews,
            'show_article_number'     => AppSetting::get('shop.display.show_article_number', '0') === '1',
            'show_deposit_separately' => AppSetting::get('shop.display.show_deposit_separately', '1') === '1',
            'description_mode'        => AppSetting::get('shop.display.description_mode', 'short'),
            'hide_unavailable'        => AppSetting::get('shop.display.hide_unavailable', '0') === '1',
            'show_stammsortiment_badge' => AppSetting::get('shop.display.show_stammsortiment_badge', '1') === '1',
            'show_new_badge'          => AppSetting::get('shop.display.show_new_badge', '1') === '1',
        ];
    }

    /**
     * Returns the Customer model for the logged-in user, or null for guests.
     */
    private function resolveCustomer(): ?\App\Models\Pricing\Customer
    {
        if (! Auth::check()) {
            return null;
        }
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->isSubUser()) {
            return $user->subUser?->parentCustomer;
        }

        return $user->customer;
    }

    /**
     * Resolve the CustomerGroup for pricing display mode.
     * For guests: load the default customer group from AppSettings.
     * For customers: load their assigned group.
     */
    private function resolveCustomerGroup(?\App\Models\Pricing\Customer $customer): ?CustomerGroup
    {
        if ($customer) {
            return $customer->customerGroup;
        }

        // Guest: load default group
        try {
            $groupId = AppSetting::getInt('default_customer_group_id');

            return CustomerGroup::find($groupId);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Build Schema.org Product JSON-LD for SEO.
     */
    private function buildSchemaOrg(Product $product, ?object $price, int $pfand, bool $stockAvailable = true): array
    {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $product->produktname,
            'sku'         => $product->artikelnummer,
            'description' => $product->sales_unit_note ?? $product->produktname,
        ];

        if ($product->brand) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name'  => $product->brand->name,
            ];
        }

        if ($product->mainImage) {
            $schema['image'] = asset('storage/' . $product->mainImage->path);
        }

        if ($price) {
            $schema['offers'] = [
                '@type'         => 'Offer',
                'priceCurrency' => 'EUR',
                'price'         => number_format($price->grossMilli / 1_000_000, 2, '.', ''),
                'availability'  => match (true) {
                    $product->availability_mode === Product::AVAILABILITY_PREORDER     => 'https://schema.org/PreOrder',
                    $product->availability_mode === Product::AVAILABILITY_OUT_OF_STOCK => 'https://schema.org/OutOfStock',
                    ! $stockAvailable                                                  => 'https://schema.org/OutOfStock',
                    default                                                            => 'https://schema.org/InStock',
                },
            ];
        }

        return $schema;
    }
}
