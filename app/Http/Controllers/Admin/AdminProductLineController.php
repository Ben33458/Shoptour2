<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Gebinde;
use App\Models\Catalog\PfandSet;
use App\Models\Catalog\ProductLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * WP-20/WP-22: CRUD controller for ProductLines.
 * Supports inline-edit PATCH.
 * ProductLine now carries optional gebinde_id + pfand_set_id.
 */
class AdminProductLineController extends Controller
{
    public function index(): View
    {
        $productLines = ProductLine::with(['brand', 'gebinde', 'pfandSet'])
            ->withCount('products')
            ->orderBy('name')
            ->get();

        $brands      = Brand::orderBy('name')->get();
        $gebindeList = Gebinde::where('active', true)->orderBy('name')->get();
        $pfandSets   = PfandSet::where('active', true)->orderBy('name')->get();

        return view('admin.product-lines.index', compact('productLines', 'brands', 'gebindeList', 'pfandSets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'brand_id'     => ['required', 'exists:brands,id'],
            'gebinde_id'   => ['nullable', 'exists:gebinde,id'],
            'pfand_set_id' => ['nullable', 'exists:pfand_sets,id'],
        ]);

        ProductLine::create([
            'name'         => $request->input('name'),
            'brand_id'     => $request->input('brand_id'),
            'gebinde_id'   => $request->input('gebinde_id') ?: null,
            'pfand_set_id' => $request->input('pfand_set_id') ?: null,
        ]);

        return back()->with('success', 'Produktlinie angelegt.');
    }

    public function update(Request $request, ProductLine $productLine): JsonResponse|RedirectResponse
    {
        $request->validate([
            'name'         => ['sometimes', 'required', 'string', 'max:150'],
            'brand_id'     => ['sometimes', 'required', 'exists:brands,id'],
            'gebinde_id'   => ['sometimes', 'nullable', 'exists:gebinde,id'],
            'pfand_set_id' => ['sometimes', 'nullable', 'exists:pfand_sets,id'],
        ]);

        $data = $request->only(['name', 'brand_id', 'gebinde_id', 'pfand_set_id']);
        if (array_key_exists('gebinde_id', $data))   $data['gebinde_id']   = $data['gebinde_id']   ?: null;
        if (array_key_exists('pfand_set_id', $data)) $data['pfand_set_id'] = $data['pfand_set_id'] ?: null;

        $productLine->update($data);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Produktlinie gespeichert.');
    }

    public function destroy(ProductLine $productLine): RedirectResponse
    {
        if ($productLine->products()->exists()) {
            return back()->with('error', 'Produktlinie kann nicht gelöscht werden – noch Produkte zugeordnet.');
        }

        $productLine->delete();

        return back()->with('success', 'Produktlinie gelöscht.');
    }
}
