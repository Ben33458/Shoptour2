<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * WP-20: CRUD controller for Categories. Supports inline-edit PATCH.
 */
class AdminCategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::with('parent')->withCount('products')->orderBy('name')->get();
        return view('admin.categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'      => ['required', 'string', 'max:150', 'unique:categories,name'],
            'parent_id' => ['nullable', 'exists:categories,id'],
        ]);
        Category::create([
            'name'      => $request->input('name'),
            'parent_id' => $request->input('parent_id') ?: null,
        ]);
        return back()->with('success', 'Kategorie angelegt.');
    }

    public function update(Request $request, Category $category): JsonResponse|RedirectResponse
    {
        $request->validate([
            'name'      => ['sometimes', 'required', 'string', 'max:150', 'unique:categories,name,' . $category->id],
            'parent_id' => ['sometimes', 'nullable', 'exists:categories,id'],
        ]);
        $data = $request->only(['name', 'parent_id']);
        if (array_key_exists('parent_id', $data)) {
            $data['parent_id'] = $data['parent_id'] ?: null;
        }
        $category->update($data);
        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', 'Kategorie gespeichert.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            return back()->with('error', 'Kategorie kann nicht gelöscht werden – noch Produkte oder Unterkategorien zugeordnet.');
        }
        $category->delete();
        return back()->with('success', 'Kategorie gelöscht.');
    }
}
