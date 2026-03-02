<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductBarcode;
use App\Services\Pricing\PricingRepositoryInterface;
use App\Services\Pricing\PriceResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/pos/products
 *
 * Searches the product catalogue for the POS terminal.
 * Supports two modes:
 *
 *   ?barcode=4006381333931  – exact barcode lookup (EAN-13, etc.)
 *   ?q=Paulaner             – partial name / artikelnummer text search
 *
 * Response shape (single product or array of products):
 * {
 *   "data": [
 *     {
 *       "id": 1,
 *       "artikelnummer": "PL-WEIZEN-050",
 *       "produktname": "Paulaner Weizen 0,5l",
 *       "price_gross_milli": 1190000,
 *       "price_net_milli": 1000000,
 *       "tax_rate_basis_points": 190000,
 *       "barcode": "4006381333931"    // only when matched via barcode
 *     }
 *   ]
 * }
 *
 * Walk-in customers use the default customer group configured in app_settings.
 */
class PosProductController extends Controller
{
    public function __construct(
        private readonly PriceResolverService      $priceResolver,
        private readonly PricingRepositoryInterface $repo,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $barcode = $request->query('barcode');
        $query   = $request->query('q');

        if ($barcode) {
            return $this->lookupByBarcode((string) $barcode);
        }

        if ($query) {
            return $this->searchByText((string) $query);
        }

        return response()->json(['error' => 'Provide ?barcode= or ?q= parameter.'], 422);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function lookupByBarcode(string $barcode): JsonResponse
    {
        /** @var ProductBarcode|null $barcodeRow */
        $barcodeRow = ProductBarcode::where('barcode', $barcode)
            ->with(['product', 'product.activeLmivVersion'])
            ->first();

        if (! $barcodeRow) {
            return response()->json(['data' => [], 'message' => 'Barcode not found.'], 404);
        }

        $product = $barcodeRow->product;
        if (! $product || ! $product->active) {
            return response()->json(['data' => [], 'message' => 'Product not active.'], 404);
        }

        $priceData = $this->resolveWalkInPrice($product);

        return response()->json([
            'data' => [[
                'id'                    => $product->id,
                'artikelnummer'         => $product->artikelnummer,
                'produktname'           => $product->produktname,
                'price_gross_milli'     => $priceData['gross'],
                'price_net_milli'       => $priceData['net'],
                'tax_rate_basis_points' => $priceData['tax_bp'],
                'barcode'               => $barcode,
                'lmiv_summary'          => $this->buildLmivSummary($product),
            ]],
        ]);
    }

    private function searchByText(string $q): JsonResponse
    {
        $term = '%' . $q . '%';

        $products = Product::where('active', true)
            ->where(function ($builder) use ($term): void {
                $builder->where('produktname', 'like', $term)
                    ->orWhere('artikelnummer', 'like', $term);
            })
            ->limit(20)
            ->get();

        $data = $products->map(function (Product $product): array {
            $priceData = $this->resolveWalkInPrice($product);

            return [
                'id'                    => $product->id,
                'artikelnummer'         => $product->artikelnummer,
                'produktname'           => $product->produktname,
                'price_gross_milli'     => $priceData['gross'],
                'price_net_milli'       => $priceData['net'],
                'tax_rate_basis_points' => $priceData['tax_bp'],
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * WP-15: Build a small LMIV summary for the barcode lookup response.
     * Returns null when no active LMIV version exists.
     *
     * @return array{version: int, ean: string|null, alkoholgehalt: float|null, allergene: string|null}|null
     */
    private function buildLmivSummary(Product $product): ?array
    {
        // Resolve the LMIV from the product itself OR its base item
        $lmivProduct = $product->is_base_item ? $product : $product->baseItem;
        $version     = $lmivProduct?->activeLmivVersion ?? $product->activeLmivVersion;

        if (! $version) {
            return null;
        }

        return [
            'version'      => $version->version_number,
            'ean'          => $version->ean,
            'alkoholgehalt' => isset($version->data_json['alkoholgehalt'])
                ? (float) $version->data_json['alkoholgehalt']
                : null,
            'allergene'    => $version->data_json['allergene'] ?? null,
        ];
    }

    /**
     * Resolve the price for a walk-in (default customer group) customer.
     *
     * @return array{gross: int, net: int, tax_bp: int}
     */
    private function resolveWalkInPrice(Product $product): array
    {
        try {
            $result = $this->priceResolver->resolveForGuest($product);
            $taxBp  = $this->repo->getTaxRateBasisPointsForProduct($product->id);

            return [
                'gross'  => $result->grossMilli,
                'net'    => $result->netMilli,
                'tax_bp' => $taxBp,
            ];
        } catch (\RuntimeException) {
            // Fallback when no app_setting / tax rate configured (e.g. fresh install)
            return [
                'gross'  => $product->base_price_gross_milli,
                'net'    => $product->base_price_net_milli,
                'tax_bp' => 0,
            ];
        }
    }
}
