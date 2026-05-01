<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Gebinde;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductLine;
use App\Models\Pricing\TaxRate;
use App\Services\Catalog\LmivVersioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * WP-15 + WP-19 – Admin product management.
 *
 * Routes:
 *   GET  /admin/products                 index()          – paginated product list
 *   GET  /admin/products/create          create()         – new product form      (WP-19)
 *   POST /admin/products                 store()          – save new product      (WP-19)
 *   GET  /admin/products/{product}       show()           – base-item detail + LMIV versions
 *   GET  /admin/products/{product}/edit  edit()           – edit product form     (WP-19)
 *   PUT  /admin/products/{product}       update()         – save product changes  (WP-19)
 *   POST /admin/products/{product}/mark-base-item  markAsBaseItem()
 */
class AdminProductController extends Controller
{
    public function __construct(
        private readonly LmivVersioningService $lmivService,
    ) {}

    // =========================================================================
    // Existing methods (WP-15)
    // =========================================================================

    /**
     * GET /admin/products
     */
    public function index(Request $request): View
    {
        $search   = $request->string('search')->trim()->toString();
        $onlyBase = $request->boolean('only_base');

        $query = Product::with('activeLmivVersion')
            ->orderBy('artikelnummer');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('artikelnummer', 'like', "%{$search}%")
                  ->orWhere('produktname', 'like', "%{$search}%")
                  ->orWhereHas('barcodes', function ($bq) use ($search): void {
                      $bq->where('barcode', 'like', "%{$search}%");
                  });
            });
        }

        if ($onlyBase) {
            $query->where('is_base_item', true);
        }

        $products = $query->paginate(50)->withQueryString();

        return view('admin.products.index', compact('products', 'search', 'onlyBase'));
    }

    /**
     * GET /admin/products/{product}
     * Base-item detail page: shows version history + links to LMIV editor.
     */
    public function show(Product $product): View
    {
        $product->load(['brand', 'productLine', 'category.parent', 'warengruppe', 'gebinde', 'taxRate', 'barcodes', 'baseItem']);
        $lmivVersions = $product->lmivVersions()->with('createdBy')->get();

        return view('admin.products.base-item', compact('product', 'lmivVersions'));
    }

    /**
     * POST /admin/products/{product}/mark-base-item
     * Toggle whether this product is a base item (and optionally link derived products).
     */
    public function markAsBaseItem(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'is_base_item' => ['required', 'boolean'],
        ]);

        $product->update(['is_base_item' => $validated['is_base_item']]);

        // If we're marking it as a base item, ensure it has an active LMIV version
        if ($validated['is_base_item']) {
            $this->lmivService->ensureActiveVersion($product, $request->user()?->id);
        }

        $label = $validated['is_base_item'] ? 'als Basis-Artikel markiert' : 'Basis-Artikel-Markierung entfernt';

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', "Produkt {$product->artikelnummer} wurde {$label}.");
    }

    // =========================================================================
    // New methods (WP-19): create/store/edit/update
    // =========================================================================

    /**
     * GET /admin/products/create
     */
    public function create(): View
    {
        [$brands, $productLines, $categories, $gebindeList, $taxRates] = $this->lookupData();
        $defaultTaxRateId = TaxRate::where('rate_basis_points', 1900)->value('id');

        return view('admin.products.create', compact('brands', 'productLines', 'categories', 'gebindeList', 'taxRates', 'defaultTaxRateId'));
    }

    /**
     * POST /admin/products
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->validationRules());

        $product = Product::create($this->prepareData($validated, $request));

        return redirect()->route('admin.products.show', $product)
            ->with('success', 'Produkt angelegt: ' . $product->artikelnummer);
    }

    /**
     * GET /admin/products/{product}/edit
     */
    public function edit(Product $product): View
    {
        $product->load('images');  // WP-21: needed for image gallery section
        [$brands, $productLines, $categories, $gebindeList, $taxRates] = $this->lookupData();

        return view('admin.products.edit', compact('product', 'brands', 'productLines', 'categories', 'gebindeList', 'taxRates'));
    }

    /**
     * PUT /admin/products/{product}   – full form save
     * PATCH /admin/products/{product} – inline-edit single-field save (JSON)
     */
    public function update(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        // ── Inline-edit PATCH from products/index (single-field update) ──
        if ($request->wantsJson()) {
            $data = [];

            if ($request->has('produktname')) {
                $request->validate(['produktname' => ['required', 'string', 'max:255']]);
                $data['produktname'] = $request->input('produktname');
            }

            if ($request->has('base_price_net_eur')) {
                $request->validate(['base_price_net_eur' => ['required', 'numeric', 'min:0']]);
                $netMilli   = eur_to_milli((float) $request->input('base_price_net_eur'));
                $taxRate    = $product->taxRate;
                $factor     = $taxRate ? (1 + $taxRate->rate_basis_points / 10_000) : 1.19;
                $data['base_price_net_milli']   = $netMilli;
                $data['base_price_gross_milli'] = (int) round($netMilli * $factor);
            }

            if ($request->has('availability_mode')) {
                $request->validate(['availability_mode' => ['required', Rule::in([
                    Product::AVAILABILITY_AVAILABLE,
                    Product::AVAILABILITY_PREORDER,
                    Product::AVAILABILITY_OUT_OF_STOCK,
                    Product::AVAILABILITY_DISCONTINUED,
                    Product::AVAILABILITY_STOCK_BASED,
                ])]]);
                $data['availability_mode'] = $request->input('availability_mode');
            }

            if ($request->has('active')) {
                $data['active'] = (bool) $request->input('active');
            }

            if (! empty($data)) {
                $product->update($data);
            }

            return response()->json(['ok' => true]);
        }

        // ── Full-form PUT ──
        $validated = $request->validate($this->validationRules($product->id));
        $product->update($this->prepareData($validated, $request, $product));

        return redirect()->route('admin.products.show', $product)
            ->with('success', 'Produkt gespeichert.');
    }

    // =========================================================================
    // Basis-Artikel creation (from existing Gebinde product)
    // =========================================================================

    /**
     * GET /admin/products/{product}/create-basis-artikel
     */
    public function createBasisArtikel(Product $source): View
    {
        [$brands, $productLines, $categories, $gebindeList, $taxRates] = $this->lookupData();

        // Strip "24x", "6x " etc. from product name
        $suggestedName = trim(preg_replace('/\b\d+\s*[xX]\s*/', '', $source->produktname) ?? '');

        // Price suggestion: net price ÷ gebinde_units
        $units = max(1, $source->gebinde_units ?? 1);
        $suggestedNetMilli = (int) round($source->base_price_net_milli / $units);

        // Unit volume in liters with comma decimal, no trailing zeros
        $suggestedUnitVolumeL = '';
        if ($source->unit_volume_ml) {
            $suggestedUnitVolumeL = rtrim(
                rtrim(number_format($source->unit_volume_ml / 1000, 3, ',', ''), '0'),
                ','
            );
        }

        $defaultTaxRateId = $source->tax_rate_id;

        return view('admin.products.create-basis-artikel', compact(
            'source', 'brands', 'productLines', 'categories',
            'gebindeList', 'taxRates',
            'suggestedName', 'suggestedNetMilli', 'suggestedUnitVolumeL', 'defaultTaxRateId',
        ));
    }

    /**
     * POST /admin/products/{product}/store-basis-artikel
     */
    public function storeBasisArtikel(Request $request, Product $source): RedirectResponse
    {
        $validated = $request->validate($this->validationRules());

        $basisArtikel = null;

        DB::transaction(function () use ($validated, $request, $source, &$basisArtikel): void {
            $basisArtikel = Product::create(array_merge(
                $this->prepareData($validated, $request),
                ['is_base_item' => true]
            ));

            $this->lmivService->ensureActiveVersion($basisArtikel, $request->user()?->id);

            $source->update(['base_item_product_id' => $basisArtikel->id]);
        });

        return redirect()->route('admin.products.show', $basisArtikel)
            ->with('success', 'Basis-Artikel angelegt: ' . $basisArtikel->artikelnummer);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Load all lookup tables needed for the product form. */
    private function lookupData(): array
    {
        return [
            Brand::orderBy('name')->get(),
            ProductLine::with('brand')->orderBy('name')->get(),
            Category::orderBy('name')->get(),
            Gebinde::where('active', true)->orderBy('name')->get(),
            TaxRate::where('active', true)->orderBy('rate_basis_points')->get(),
        ];
    }

    /** Validation rules shared by store() and update(). */
    private function validationRules(?int $ignoreId = null): array
    {
        return [
            'artikelnummer'        => ['required', 'string', 'max:50',
                Rule::unique('products', 'artikelnummer')->ignore($ignoreId)],
            'produktname'          => ['required', 'string', 'max:255'],
            'brand_id'             => ['nullable', 'exists:brands,id'],
            'product_line_id'      => ['nullable', 'exists:product_lines,id'],
            'category_id'          => ['nullable', 'exists:categories,id'],
            'gebinde_id'           => ['nullable', 'exists:gebinde,id'],
            'tax_rate_id'          => ['nullable', 'exists:tax_rates,id'],
            'base_price_net_eur'   => ['nullable', 'numeric', 'min:0'],
            'base_price_gross_eur' => ['nullable', 'numeric', 'min:0'],
            'availability_mode'    => ['required', Rule::in([
                Product::AVAILABILITY_AVAILABLE,
                Product::AVAILABILITY_PREORDER,
                Product::AVAILABILITY_OUT_OF_STOCK,
                Product::AVAILABILITY_DISCONTINUED,
                Product::AVAILABILITY_STOCK_BASED,
            ])],
            'active'             => ['nullable', 'boolean'],
            'gebinde_units'      => ['nullable', 'integer', 'min:1', 'max:9999'],
            'unit_volume_l'      => ['nullable', 'regex:/^\d+([.,]\d+)?$/'],
            'volume_l'           => ['nullable', 'regex:/^\d+([.,]\d+)?$/'],
            'alkoholgehalt_vol_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /** Convert validated form data to model attributes. */
    private function prepareData(array $validated, Request $request, ?Product $existing = null): array
    {
        $taxRate = isset($validated['tax_rate_id'])
            ? TaxRate::find($validated['tax_rate_id'])
            : null;
        $factor = $taxRate ? (1 + $taxRate->rate_basis_points / 10_000) : 1.19;

        // Accept either netto or brutto; calculate the missing one
        if (! empty($validated['base_price_gross_eur']) && empty($validated['base_price_net_eur'])) {
            $grossMilli = eur_to_milli((float) $validated['base_price_gross_eur']);
            $netMilli   = (int) round($grossMilli / $factor);
        } else {
            $netMilli   = eur_to_milli((float) ($validated['base_price_net_eur'] ?? 0));
            $grossMilli = (int) round($netMilli * $factor);
        }

        // On update keep existing slug; on create generate a unique one
        if ($existing) {
            $slug = $existing->slug;
        } else {
            $baseSlug = Str::slug($validated['produktname']) ?: 'produkt';
            $slug = $baseSlug;
            $i = 2;
            while (Product::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $i++;
            }
        }

        return [
            'artikelnummer'         => $validated['artikelnummer'],
            'slug'                  => $slug,
            'produktname'           => $validated['produktname'],
            'brand_id'              => $validated['brand_id'] ?? null,
            'product_line_id'       => $validated['product_line_id'] ?? null,
            'category_id'           => $validated['category_id'] ?? null,
            'gebinde_id'            => $validated['gebinde_id'] ?? null,
            'tax_rate_id'           => $validated['tax_rate_id'] ?? null,
            'base_price_net_milli'  => $netMilli,
            'base_price_gross_milli'=> $grossMilli,
            'availability_mode'     => $validated['availability_mode'],
            'active'                => $request->boolean('active'),
            'gebinde_units'         => isset($validated['gebinde_units']) ? (int) $validated['gebinde_units'] : null,
            // Konvertiere L-Eingaben → ml (Komma als Dezimaltrennzeichen wird akzeptiert)
            'unit_volume_ml'        => isset($validated['unit_volume_l']) && $validated['unit_volume_l'] !== ''
                                           ? (int) round((float) str_replace(',', '.', $validated['unit_volume_l']) * 1000)
                                           : null,
            // Auto-berechne volume_ml aus gebinde_units × unit_volume_ml; sonst manueller volume_l-Wert
            'volume_ml'             => (function () use ($validated): ?int {
                                           $units  = isset($validated['gebinde_units']) ? (int) $validated['gebinde_units'] : null;
                                           $unitMl = isset($validated['unit_volume_l']) && $validated['unit_volume_l'] !== ''
                                               ? (int) round((float) str_replace(',', '.', $validated['unit_volume_l']) * 1000)
                                               : null;
                                           if ($units && $unitMl) return $units * $unitMl;
                                           if (isset($validated['volume_l']) && $validated['volume_l'] !== '') {
                                               return (int) round((float) str_replace(',', '.', $validated['volume_l']) * 1000);
                                           }
                                           return null;
                                       })(),
            'alkoholgehalt_vol_percent' => $validated['alkoholgehalt_vol_percent'] ?? null,
        ];
    }
}
