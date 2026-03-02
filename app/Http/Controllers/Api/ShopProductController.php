<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use Illuminate\Http\JsonResponse;

/**
 * WP-15 – Shop / storefront product detail endpoint.
 *
 * GET /api/products/{id}
 *
 * Returns full product information including the complete active LMIV version
 * (all data_json fields) for display in the customer-facing shop.
 *
 * Response shape:
 * {
 *   "data": {
 *     "id": 1,
 *     "artikelnummer": "PL-WEIZEN-050",
 *     "produktname": "Paulaner Weizen 0,5l",
 *     "active": true,
 *     "lmiv": {
 *       "version": 3,
 *       "ean": "4006381333931",
 *       "effective_from": "2024-01-15T00:00:00Z",
 *       "data": { … all data_json fields … }
 *     }
 *   }
 * }
 *
 * Returns lmiv = null when no active LMIV version exists or the product has
 * no base item link.
 */
class ShopProductController extends Controller
{
    /**
     * GET /api/products/{id}
     */
    public function show(int $id): JsonResponse
    {
        /** @var Product|null $product */
        $product = Product::with(['activeLmivVersion', 'baseItem.activeLmivVersion'])
            ->find($id);

        if (! $product) {
            return response()->json(['error' => 'Product not found.'], 404);
        }

        if (! $product->active) {
            return response()->json(['error' => 'Product not active.'], 404);
        }

        // Resolve LMIV: from self (if base item) or from the linked base item
        $version = null;
        if ($product->is_base_item) {
            $version = $product->activeLmivVersion;
        } elseif ($product->base_item_product_id && $product->baseItem) {
            $version = $product->baseItem->activeLmivVersion;
        }

        $lmiv = null;
        if ($version) {
            $lmiv = [
                'version'        => $version->version_number,
                'ean'            => $version->ean,
                'effective_from' => $version->effective_from?->toIso8601String(),
                'data'           => $version->data_json ?? [],
            ];
        }

        return response()->json([
            'data' => [
                'id'           => $product->id,
                'artikelnummer' => $product->artikelnummer,
                'produktname'  => $product->produktname,
                'active'       => $product->active,
                'is_base_item' => $product->is_base_item,
                'lmiv'         => $lmiv,
            ],
        ]);
    }
}
