<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        return view('shop.page', compact('page'));
    }
}
