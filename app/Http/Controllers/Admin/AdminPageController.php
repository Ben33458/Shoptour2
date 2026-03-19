<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminPageController extends Controller
{
    public function index(): View
    {
        $pages = Page::orderBy('sort_order')->orderBy('title')->get()
            ->groupBy('menu');

        return view('admin.pages.index', compact('pages'));
    }

    public function create(): View
    {
        return view('admin.pages.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'      => ['required', 'string', 'max:255'],
            'slug'       => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', 'unique:pages,slug'],
            'menu'       => ['required', 'in:main,footer,none'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'content'    => ['required', 'string'],
        ]);

        $validated['slug']       ??= Str::slug($validated['title']);
        $validated['sort_order'] ??= 0;
        $validated['active']       = $request->boolean('active', true);

        $page = Page::create($validated);

        return redirect()->route('admin.pages.index')
            ->with('success', "Seite \"{$page->title}\" angelegt.");
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.edit', compact('page'));
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $validated = $request->validate([
            'title'      => ['required', 'string', 'max:255'],
            'menu'       => ['required', 'in:main,footer,none'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'content'    => ['required', 'string'],
        ]);

        $validated['sort_order'] ??= 0;
        $validated['active']       = $request->boolean('active', true);

        $page->update($validated);

        return redirect()->route('admin.pages.index')
            ->with('success', "Seite \"{$page->title}\" gespeichert.");
    }

    public function destroy(Page $page): RedirectResponse
    {
        $title = $page->title;
        $page->delete();

        return redirect()->route('admin.pages.index')
            ->with('success', "Seite \"{$title}\" gelöscht.");
    }
}
