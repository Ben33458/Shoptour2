<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * WP-20: CRUD controller for Brands. Supports inline-edit PATCH.
 */
class AdminBrandController extends Controller
{
    public function index(): View
    {
        $brands = Brand::withCount('products')->orderBy('name')->get();
        return view('admin.brands.index', compact('brands'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:brands,name'],
        ]);
        Brand::create(['name' => $request->input('name')]);
        return back()->with('success', 'Marke angelegt.');
    }

    public function update(Request $request, Brand $brand): JsonResponse|RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:brands,name,' . $brand->id],
        ]);
        $brand->update(['name' => $request->input('name')]);
        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', 'Marke gespeichert.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->products()->exists()) {
            return back()->with('error', 'Marke kann nicht gelöscht werden – noch Produkte zugeordnet.');
        }
        $brand->delete();
        return back()->with('success', 'Marke gelöscht.');
    }
}
