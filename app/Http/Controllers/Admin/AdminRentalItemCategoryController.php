<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental\RentalItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminRentalItemCategoryController extends Controller
{
    public function index(): View
    {
        $categories = RentalItemCategory::orderBy('sort_order')->orderBy('name')->get();
        return view('admin.rental.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.rental.categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer|min:0',
            'active'      => 'boolean',
        ]);
        $data['slug']   = Str::slug($data['name']);
        $data['active'] = $request->boolean('active', true);
        RentalItemCategory::create($data);
        return redirect()->route('admin.rental.categories.index')->with('success', 'Kategorie erstellt.');
    }

    public function edit(RentalItemCategory $category): View
    {
        return view('admin.rental.categories.edit', compact('category'));
    }

    public function update(Request $request, RentalItemCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer|min:0',
            'active'      => 'boolean',
        ]);
        $data['active'] = $request->boolean('active');
        $category->update($data);
        return redirect()->route('admin.rental.categories.index')->with('success', 'Kategorie aktualisiert.');
    }

    public function destroy(RentalItemCategory $category): RedirectResponse
    {
        if ($category->items()->exists()) {
            return back()->with('error', 'Kategorie enthält noch Mietartikel und kann nicht gelöscht werden.');
        }
        $category->delete();
        return redirect()->route('admin.rental.categories.index')->with('success', 'Kategorie gelöscht.');
    }
}
