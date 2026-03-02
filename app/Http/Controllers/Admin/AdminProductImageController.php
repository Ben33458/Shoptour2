<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * WP-21 – Manage product image gallery in the admin area.
 *
 * Routes (registered under admin prefix + middleware):
 *   POST   /admin/products/{product}/images              → store()
 *   DELETE /admin/products/{product}/images/{image}      → destroy()
 *   POST   /admin/products/{product}/images/{image}/sort → sort()
 */
class AdminProductImageController extends Controller
{
    /**
     * POST /admin/products/{product}/images
     * Upload one or more images for the given product.
     */
    public function store(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'images'   => ['required', 'array', 'min:1'],
            'images.*' => ['mimes:jpeg,jpg,png,webp,gif', 'max:5120'],  // 5 MB per file
        ]);

        $nextSort = (int) $product->images()->max('sort_order') + 1;

        foreach ($request->file('images') as $file) {
            $filename = Str::uuid() . '.' . $file->extension();
            $path     = $file->storeAs("products/{$product->id}", $filename, 'public');

            $product->images()->create([
                'path'       => $path,
                'sort_order' => $nextSort++,
                'alt_text'   => null,
            ]);
        }

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', 'Bilder hochgeladen.');
    }

    /**
     * DELETE /admin/products/{product}/images/{image}
     * Delete a single product image and its file.
     */
    public function destroy(Product $product, ProductImage $image): RedirectResponse
    {
        abort_if($image->product_id !== $product->id, 403);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', 'Bild gelöscht.');
    }

    /**
     * POST /admin/products/{product}/images/{image}/sort
     * Move an image's sort_order up or down by one position.
     * Accepts: direction = 'up' | 'down'
     */
    public function sort(Request $request, Product $product, ProductImage $image): RedirectResponse
    {
        abort_if($image->product_id !== $product->id, 403);

        $request->validate(['direction' => ['required', 'in:up,down']]);

        $direction = $request->input('direction');

        // Find the neighbour to swap sort_order with
        $neighbour = $product->images()
            ->when($direction === 'up',
                fn ($q) => $q->where('sort_order', '<', $image->sort_order)->orderByDesc('sort_order'),
                fn ($q) => $q->where('sort_order', '>', $image->sort_order)->orderBy('sort_order'),
            )
            ->first();

        if ($neighbour) {
            [$image->sort_order, $neighbour->sort_order] = [$neighbour->sort_order, $image->sort_order];
            $image->save();
            $neighbour->save();
        }

        return redirect()->route('admin.products.edit', $product);
    }
}
