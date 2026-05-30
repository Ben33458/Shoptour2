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
 * WP-20: CRUD controller for Brands. Supports inline-edit PATCH and full edit form.
 */
class AdminBrandController extends Controller
{
    public function index(): View
    {
        $brands = Brand::withCount('products')->orderBy('name')->get();
        return view('admin.brands.index', compact('brands'));
    }

    public function edit(Brand $brand): View
    {
        return view('admin.brands.edit', compact('brand'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'    => ['required', 'string', 'max:150', 'unique:brands,name'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'plz'     => ['nullable', 'string', 'max:10'],
            'ort'     => ['nullable', 'string', 'max:100'],
            'land'    => ['nullable', 'string', 'max:100'],
            'email'   => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);
        Brand::create($request->only(['name', 'adresse', 'plz', 'ort', 'land', 'email', 'website']));
        return back()->with('success', 'Marke angelegt.');
    }

    public function update(Request $request, Brand $brand): JsonResponse|RedirectResponse
    {
        $request->validate([
            'name'    => ['required', 'string', 'max:150', 'unique:brands,name,' . $brand->id],
            'adresse' => ['nullable', 'string', 'max:255'],
            'plz'     => ['nullable', 'string', 'max:10'],
            'ort'     => ['nullable', 'string', 'max:100'],
            'land'    => ['nullable', 'string', 'max:100'],
            'email'   => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);
        $brand->update($request->only(['name', 'adresse', 'plz', 'ort', 'land', 'email', 'website']));
        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }
        return redirect()->route('admin.brands.index')->with('success', 'Hersteller gespeichert.');
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
