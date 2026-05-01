@extends('shop.layout')

@section('title', 'Produkte')

@php
$viewMode      = $displaySettings['view_mode'];
$showArtNr     = $displaySettings['show_article_number'];
$showPfand     = $displaySettings['show_deposit_separately'];
$descMode      = $displaySettings['description_mode'];
$showFavBadge  = $displaySettings['show_stammsortiment_badge'];
$showNewBadge  = $displaySettings['show_new_badge'];
$availViews    = $displaySettings['available_views'];

$viewLabels = [
    'grid_large'     => ['icon' => '⊞', 'title' => 'Grid (groß)'],
    'grid_compact'   => ['icon' => '⊟', 'title' => 'Grid (kompakt)'],
    'list_images'    => ['icon' => '▤',  'title' => 'Liste mit Bild'],
    'list_no_images' => ['icon' => '☰',  'title' => 'Textliste'],
    'table'          => ['icon' => '⊞',  'title' => 'Tabelle'],
];
@endphp

@section('content')
<div class="flex flex-wrap gap-8" x-data="{ filterOpen: false }">

    {{-- === Mobile: Warengruppen-Leiste + Filter-Button (immer sichtbar) ===== --}}
    <div class="lg:hidden w-full mb-2">
        {{-- Horizontale Warengruppen-Chips --}}
        @if($warengruppen->isNotEmpty())
        <div class="flex gap-2 overflow-x-auto pb-2 mb-3 scrollbar-hide" style="-webkit-overflow-scrolling:touch;scrollbar-width:none">
            <a href="{{ route('shop.products', array_filter(['suche' => $search, 'sort' => $sort])) }}"
               class="flex-shrink-0 px-3 py-1.5 rounded-full text-sm font-medium border transition-colors whitespace-nowrap
                      {{ !$warengruppeId && !$categoryId ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-gray-600 border-gray-200 hover:border-amber-400' }}">
                Alle
            </a>
            @foreach($warengruppen as $wg)
                @if($wg->products_count > 0)
                <a href="{{ route('shop.products', array_filter(['warengruppe' => $wg->id, 'suche' => $search, 'sort' => $sort])) }}"
                   class="flex-shrink-0 px-3 py-1.5 rounded-full text-sm font-medium border transition-colors whitespace-nowrap
                          {{ $warengruppeId == $wg->id ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-gray-600 border-gray-200 hover:border-amber-400' }}">
                    {{ $wg->name }}
                </a>
                @endif
            @endforeach
        </div>
        @endif

        {{-- Filter-Button (Kategorien / Marken) --}}
        <button @click="filterOpen = true"
                class="w-full flex items-center justify-between bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-700 hover:border-amber-400 transition-colors">
            <span class="flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                <span>
                    @if($categoryId)
                        {{ $categories->flatMap(fn($c) => $c->children->prepend($c))->firstWhere('id', $categoryId)?->name ?? 'Kategorie' }}
                    @else
                        Kategorien &amp; Filter
                    @endif
                </span>
            </span>
            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>

    {{-- === Mobile backdrop ================================================== --}}
    <div x-show="filterOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="filterOpen = false"
         class="fixed inset-0 bg-black/40 z-40 lg:hidden"
         style="display: none;"></div>

    {{-- === Sidebar: Filters ================================================= --}}
    <aside :class="filterOpen ? 'fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-xl overflow-y-auto p-4 block' : 'hidden lg:block'"
           class="w-56 shrink-0 space-y-6"
           @resize.window="if (window.innerWidth >= 1024) filterOpen = false">

        <div class="flex items-center justify-between lg:hidden mb-4" x-show="filterOpen" x-cloak>
            <h2 class="text-lg font-bold text-gray-900">Filter</h2>
            <button @click="filterOpen = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Category filter --}}
        <div>
            <h2 class="font-semibold text-sm text-gray-500 uppercase tracking-wide mb-3">Kategorien</h2>
            <nav class="space-y-1">
                <a href="{{ route('shop.products', array_filter(['suche' => $search, 'brand' => $brandId, 'gebinde' => $gebindeId, 'sort' => $sort])) }}"
                   class="block px-3 py-1.5 rounded-lg text-sm {{ !$categoryId ? 'bg-amber-100 text-amber-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                    Alle Kategorien
                </a>
                @foreach($categories as $cat)
                    <a href="{{ route('shop.products', array_filter(['kategorie' => $cat->id, 'suche' => $search, 'brand' => $brandId, 'gebinde' => $gebindeId, 'sort' => $sort])) }}"
                       class="block px-3 py-1.5 rounded-lg text-sm {{ $categoryId == $cat->id ? 'bg-amber-100 text-amber-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                        {{ $cat->name }}
                    </a>
                    @if($cat->children->isNotEmpty())
                        @foreach($cat->children as $child)
                            <a href="{{ route('shop.products', array_filter(['kategorie' => $child->id, 'suche' => $search, 'brand' => $brandId, 'gebinde' => $gebindeId, 'sort' => $sort])) }}"
                               class="block px-3 py-1.5 pl-6 rounded-lg text-sm {{ $categoryId == $child->id ? 'bg-amber-100 text-amber-700 font-medium' : 'text-gray-400 hover:bg-gray-100' }}">
                                {{ $child->name }}
                            </a>
                        @endforeach
                    @endif
                @endforeach
            </nav>
        </div>

        {{-- Warengruppen filter --}}
        @if($warengruppen->isNotEmpty())
        <div>
            <h2 class="font-semibold text-sm text-gray-500 uppercase tracking-wide mb-3">Warengruppen</h2>
            <nav class="space-y-1">
                <a href="{{ route('shop.products', array_filter(['suche' => $search, 'brand' => $brandId, 'gebinde' => $gebindeId, 'sort' => $sort])) }}"
                   class="block px-3 py-1.5 rounded-lg text-sm {{ !$warengruppeId ? 'bg-amber-100 text-amber-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                    Alle Warengruppen
                </a>
                @foreach($warengruppen as $wg)
                    @if($wg->products_count > 0)
                    <a href="{{ route('shop.products', array_filter(['warengruppe' => $wg->id, 'suche' => $search, 'brand' => $brandId, 'gebinde' => $gebindeId, 'sort' => $sort])) }}"
                       class="block px-3 py-1.5 rounded-lg text-sm {{ $warengruppeId == $wg->id ? 'bg-amber-100 text-amber-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                        {{ $wg->name }}
                        <span class="text-xs text-gray-400 ml-1">{{ $wg->products_count }}</span>
                    </a>
                    @endif
                @endforeach
            </nav>
        </div>
        @endif

        {{-- Brand filter --}}
        @if($brands->isNotEmpty())
        <div>
            <h2 class="font-semibold text-sm text-gray-500 uppercase tracking-wide mb-3">Marken</h2>
            <nav class="space-y-1">
                <a href="{{ route('shop.products', array_filter(['kategorie' => $categoryId, 'suche' => $search, 'gebinde' => $gebindeId, 'sort' => $sort])) }}"
                   class="block px-3 py-1.5 rounded-lg text-sm {{ !$brandId ? 'bg-amber-100 text-amber-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                    Alle Marken
                </a>
                @foreach($brands as $brand)
                    <a href="{{ route('shop.products', array_filter(['kategorie' => $categoryId, 'brand' => $brand->id, 'suche' => $search, 'gebinde' => $gebindeId, 'sort' => $sort])) }}"
                       class="block px-3 py-1.5 rounded-lg text-sm {{ $brandId == $brand->id ? 'bg-amber-100 text-amber-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                        {{ $brand->name }}
                    </a>
                @endforeach
            </nav>
        </div>
        @endif

    </aside>

    {{-- === Main content ===================================================== --}}
    <div class="flex-1 min-w-0">

        {{-- Header row --}}
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <h1 class="text-2xl font-bold text-gray-900">
                @if($search) Suchergebnisse für &bdquo;{{ $search }}&ldquo; @else Getränke @endif
            </h1>
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-sm text-gray-400">{{ $products->total() }} Produkte</span>

                {{-- Sort dropdown --}}
                <select onchange="window.location.href=this.value" class="text-sm border border-gray-200 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400">
                    <option value="{{ route('shop.products', array_filter(['kategorie' => $categoryId, 'brand' => $brandId, 'gebinde' => $gebindeId, 'warengruppe' => $warengruppeId, 'suche' => $search, 'sort' => 'name'])) }}" {{ $sort === 'name' ? 'selected' : '' }}>Name A-Z</option>
                    <option value="{{ route('shop.products', array_filter(['kategorie' => $categoryId, 'brand' => $brandId, 'gebinde' => $gebindeId, 'warengruppe' => $warengruppeId, 'suche' => $search, 'sort' => 'preis'])) }}" {{ $sort === 'preis' ? 'selected' : '' }}>Preis aufsteigend</option>
                    <option value="{{ route('shop.products', array_filter(['kategorie' => $categoryId, 'brand' => $brandId, 'gebinde' => $gebindeId, 'warengruppe' => $warengruppeId, 'suche' => $search, 'sort' => 'preis-desc'])) }}" {{ $sort === 'preis-desc' ? 'selected' : '' }}>Preis absteigend</option>
                    @if($search)
                    <option value="{{ route('shop.products', array_filter(['kategorie' => $categoryId, 'brand' => $brandId, 'gebinde' => $gebindeId, 'warengruppe' => $warengruppeId, 'suche' => $search, 'sort' => 'relevanz'])) }}" {{ $sort === 'relevanz' ? 'selected' : '' }}>Relevanz</option>
                    @endif
                </select>

                {{-- View mode switcher (only shown if > 1 available view) --}}
                @if(count($availViews) > 1)
                <div class="flex items-center gap-1 border border-gray-200 rounded-xl p-1">
                    @foreach($availViews as $vm)
                    @php $vl = $viewLabels[$vm] ?? ['icon' => '⊞', 'title' => $vm]; @endphp
                    <form method="POST" action="{{ route('shop.display_preferences.update') }}" style="display:inline">
                        @csrf
                        <input type="hidden" name="view_mode" value="{{ $vm }}">
                        <input type="hidden" name="items_per_page" value="{{ $displaySettings['items_per_page'] }}">
                        <button type="submit"
                                title="{{ $vl['title'] }}"
                                class="px-2.5 py-1.5 rounded-lg text-sm transition-colors
                                       {{ $viewMode === $vm
                                          ? 'bg-amber-500 text-white'
                                          : 'text-gray-500 hover:bg-gray-100' }}">
                            {{ $vl['icon'] }}
                        </button>
                    </form>
                    @endforeach
                </div>
                @endif

            </div>
        </div>

        {{-- Mobile search --}}
        <form action="{{ route('shop.products') }}" method="GET" class="sm:hidden mb-4">
            <div class="relative">
                <input type="text" name="suche" value="{{ $search }}" placeholder="Produkte suchen…"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                <button type="submit" class="absolute right-3 top-2.5 text-gray-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
                </button>
            </div>
        </form>

        {{-- Active filter badges --}}
        @if($categoryId || $brandId || $gebindeId || $warengruppeId || $search)
            <div class="flex flex-wrap gap-2 mb-4">
                @if($search)
                    <a href="{{ route('shop.products', array_filter(['kategorie' => $categoryId, 'brand' => $brandId, 'gebinde' => $gebindeId, 'warengruppe' => $warengruppeId, 'sort' => $sort])) }}"
                       class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 text-xs font-medium px-2.5 py-1 rounded-full">
                        Suche: {{ $search }}
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
                <a href="{{ route('shop.products') }}" class="inline-flex items-center gap-1 text-gray-400 text-xs px-2.5 py-1 rounded-full hover:bg-gray-100">
                    Filter zurücksetzen
                </a>
            </div>
        @endif

        @if($products->isEmpty())
            <div class="text-center py-20 text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-lg font-medium">Keine Produkte gefunden</p>
                <a href="{{ route('shop.products') }}" class="mt-2 inline-block text-amber-600 hover:underline text-sm">Alle Produkte anzeigen</a>
            </div>
        @else

        {{-- ════════════════════════════════════════════════════════
             ANSICHT: GRID GROSS (Standard)
        ════════════════════════════════════════════════════════ --}}
        @if($viewMode === 'grid_large')
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($products as $product)
                @php $pd = $priceData[$product->id] ?? null; $price = $pd['price'] ?? null; $pfand = $pd['pfand'] ?? 0; $stockAvailable = $pd['stock_available'] ?? true; @endphp
                <div class="product-card bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md flex flex-col relative">
                    @include('shop._product-badges', compact('product', 'stockAvailable', 'showNewBadge', 'favoriteProductIds', 'showFavBadge'))
                    <a href="{{ route('shop.product', $product) }}" class="block aspect-square bg-gray-50 rounded-t-2xl overflow-hidden">
                        <img src="{{ $product->mainImage ? Storage::url($product->mainImage->path) : $product->placeholderImageUrl() }}"
                             alt="{{ $product->produktname }}" class="w-full h-full object-contain p-2" loading="lazy">
                    </a>
                    <div class="p-3 flex flex-col flex-1">
                        @if($product->brand)<p class="text-xs text-gray-400 mb-0.5">{{ $product->brand->name }}</p>@endif
                        @if($showArtNr)<p class="text-xs text-gray-300 mb-0.5">{{ $product->artikelnummer }}</p>@endif
                        <a href="{{ route('shop.product', $product) }}" class="text-sm font-medium text-gray-900 hover:text-amber-600 line-clamp-2 leading-snug flex-1">{!! $product->produktname_formatted !!}</a>
                        @if($descMode !== 'none' && $product->short_description)
                            <p class="text-xs text-gray-400 mt-1 {{ $descMode === 'short' ? 'line-clamp-1' : '' }}">{{ $product->short_description }}</p>
                        @endif
                        @include('shop._product-price', compact('price', 'pfand', 'priceDisplayMode', 'isBusiness', 'showPfand') + ['grundpreisText' => $product->grundpreis_text])
                        @include('shop._product-cart', compact('product', 'stockAvailable'))
                        @include('shop._product-fav', compact('product', 'favoriteProductIds'))
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ════════════════════════════════════════════════════════
             ANSICHT: GRID KOMPAKT
        ════════════════════════════════════════════════════════ --}}
        @elseif($viewMode === 'grid_compact')
        <div class="grid grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3">
            @foreach($products as $product)
                @php $pd = $priceData[$product->id] ?? null; $price = $pd['price'] ?? null; $pfand = $pd['pfand'] ?? 0; $stockAvailable = $pd['stock_available'] ?? true; @endphp
                <div class="product-card bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md flex flex-col relative">
                    @include('shop._product-badges', compact('product', 'stockAvailable', 'showNewBadge', 'favoriteProductIds', 'showFavBadge'))
                    <a href="{{ route('shop.product', $product) }}" class="block aspect-square bg-gray-50 rounded-t-xl overflow-hidden">
                        <img src="{{ $product->mainImage ? Storage::url($product->mainImage->path) : $product->placeholderImageUrl() }}"
                             alt="{{ $product->produktname }}" class="w-full h-full object-contain p-1" loading="lazy">
                    </a>
                    <div class="p-2 flex flex-col flex-1">
                        @if($showArtNr)<p class="text-xs text-gray-300">{{ $product->artikelnummer }}</p>@endif
                        <a href="{{ route('shop.product', $product) }}" class="text-xs font-medium text-gray-900 hover:text-amber-600 line-clamp-2 leading-snug flex-1">{!! $product->produktname_formatted !!}</a>
                        @include('shop._product-price', compact('price', 'pfand', 'priceDisplayMode', 'isBusiness', 'showPfand') + ['grundpreisText' => $product->grundpreis_text])
                        @include('shop._product-cart', compact('product', 'stockAvailable'))
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ════════════════════════════════════════════════════════
             ANSICHT: LISTE MIT BILD
        ════════════════════════════════════════════════════════ --}}
        @elseif($viewMode === 'list_images')
        <div class="flex flex-col gap-3">
            @foreach($products as $product)
                @php $pd = $priceData[$product->id] ?? null; $price = $pd['price'] ?? null; $pfand = $pd['pfand'] ?? 0; $stockAvailable = $pd['stock_available'] ?? true; @endphp
                <div class="product-card bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md flex gap-3 p-3 items-center">
                    <a href="{{ route('shop.product', $product) }}" class="w-16 h-16 shrink-0 bg-gray-50 rounded-lg overflow-hidden">
                        <img src="{{ $product->mainImage ? Storage::url($product->mainImage->path) : $product->placeholderImageUrl() }}"
                             alt="{{ $product->produktname }}" class="w-full h-full object-contain p-1" loading="lazy">
                    </a>
                    <div class="flex-1 min-w-0">
                        @if($product->brand)<p class="text-xs text-gray-400">{{ $product->brand->name }}</p>@endif
                        <a href="{{ route('shop.product', $product) }}" class="text-sm font-semibold text-gray-900 hover:text-amber-600 line-clamp-1">{!! $product->produktname_formatted !!}</a>
                        @if($showArtNr)<p class="text-xs text-gray-400">Art.-Nr.: {{ $product->artikelnummer }}</p>@endif
                        @if($descMode !== 'none' && $product->short_description)
                            <p class="text-xs text-gray-400 line-clamp-1">{{ $product->short_description }}</p>
                        @endif
                        @if(!$stockAvailable)<span class="text-xs text-red-500 font-medium">Nicht verfügbar</span>@endif
                    </div>
                    <div class="shrink-0 text-right min-w-[100px]">
                        @if($price)
                            <p class="text-sm font-bold text-gray-900">{{ milli_to_eur($priceDisplayMode === 'netto' ? $price->netMilli : $price->grossMilli) }}</p>
                            @if($showPfand && $pfand > 0)<p class="text-xs text-amber-600">+ {{ milli_to_eur($pfand) }} Pfand</p>@endif
                            @if($product->grundpreis_text)<p class="text-xs text-gray-400 mt-0.5">{{ $product->grundpreis_text }}</p>@endif
                        @else
                            <p class="text-xs text-gray-400 italic">Auf Anfrage</p>
                        @endif
                    </div>
                    <div class="shrink-0">
                        @include('shop._product-cart', compact('product', 'stockAvailable'))
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ════════════════════════════════════════════════════════
             ANSICHT: TEXTLISTE (ohne Bilder)
        ════════════════════════════════════════════════════════ --}}
        @elseif($viewMode === 'list_no_images')
        <div class="flex flex-col divide-y divide-gray-100">
            @foreach($products as $product)
                @php $pd = $priceData[$product->id] ?? null; $price = $pd['price'] ?? null; $pfand = $pd['pfand'] ?? 0; $stockAvailable = $pd['stock_available'] ?? true; @endphp
                <div class="product-card flex gap-4 py-2.5 px-2 rounded-lg items-center">
                    <div class="flex-1 min-w-0">
                        <a href="{{ route('shop.product', $product) }}" class="text-sm font-medium text-gray-900 hover:text-amber-600">{!! $product->produktname_formatted !!}</a>
                        @if($product->brand || $showArtNr)
                        <p class="text-xs text-gray-400">
                            @if($product->brand){{ $product->brand->name }}@endif
                            @if($showArtNr)<span class="ml-2">{{ $product->artikelnummer }}</span>@endif
                        </p>
                        @endif
                        @if(!$stockAvailable)<span class="text-xs text-red-500 font-medium">Nicht verfügbar</span>@endif
                    </div>
                    <div class="shrink-0 text-right min-w-[90px]">
                        @if($price)
                            <p class="text-sm font-bold text-gray-900 whitespace-nowrap">{{ milli_to_eur($priceDisplayMode === 'netto' ? $price->netMilli : $price->grossMilli) }}</p>
                            @if($showPfand && $pfand > 0)<p class="text-xs text-amber-600 whitespace-nowrap">+ {{ milli_to_eur($pfand) }} P</p>@endif
                            @if($product->grundpreis_text)<p class="text-xs text-gray-400 whitespace-nowrap">{{ $product->grundpreis_text }}</p>@endif
                        @else
                            <p class="text-xs text-gray-400 italic">Anfrage</p>
                        @endif
                    </div>
                    <div class="shrink-0">
                        @include('shop._product-cart', compact('product', 'stockAvailable'))
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ════════════════════════════════════════════════════════
             ANSICHT: TABELLE
        ════════════════════════════════════════════════════════ --}}
        @elseif($viewMode === 'table')
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="border-b-2 border-gray-200 text-xs text-gray-500 uppercase tracking-wide">
                        @if($showArtNr)<th class="text-left py-2 px-3 font-semibold">Art.-Nr.</th>@endif
                        <th class="text-left py-2 px-3 font-semibold">Produkt</th>
                        <th class="text-left py-2 px-3 font-semibold hidden md:table-cell">Marke</th>
                        <th class="text-right py-2 px-3 font-semibold">Preis</th>
                        @if($showPfand)<th class="text-right py-2 px-3 font-semibold hidden sm:table-cell">Pfand</th>@endif
                        <th class="text-right py-2 px-3 font-semibold">Menge</th>
                        <th class="py-2 px-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($products as $product)
                    @php $pd = $priceData[$product->id] ?? null; $price = $pd['price'] ?? null; $pfand = $pd['pfand'] ?? 0; $stockAvailable = $pd['stock_available'] ?? true; @endphp
                    @php $isDisabled = $product->availability_mode === 'discontinued' || $product->availability_mode === 'out_of_stock' || !$stockAvailable; @endphp
                    <tr class="product-card {{ !$stockAvailable ? 'opacity-60' : '' }}">
                        @if($showArtNr)
                        <td class="py-2 px-3 text-xs text-gray-400 whitespace-nowrap">{{ $product->artikelnummer }}</td>
                        @endif
                        <td class="py-2 px-3">
                            <a href="{{ route('shop.product', $product) }}" class="font-medium text-gray-900 hover:text-amber-600">{!! $product->produktname_formatted !!}</a>
                            @if(!$stockAvailable)<span class="ml-2 text-xs text-red-500">Nicht verfügbar</span>@endif
                        </td>
                        <td class="py-2 px-3 text-gray-400 hidden md:table-cell">{{ $product->brand?->name ?? '—' }}</td>
                        <td class="py-2 px-3 text-right font-bold whitespace-nowrap">
                            @if($price)
                                {{ milli_to_eur($priceDisplayMode === 'netto' ? $price->netMilli : $price->grossMilli) }}
                                @if($product->grundpreis_text)<br><span class="text-xs font-normal text-gray-400">{{ $product->grundpreis_text }}</span>@endif
                            @else
                                <span class="text-gray-400 font-normal italic text-xs">Anfrage</span>
                            @endif
                        </td>
                        @if($showPfand)
                        <td class="py-2 px-3 text-right text-xs text-amber-600 whitespace-nowrap hidden sm:table-cell">
                            {{ $pfand > 0 ? milli_to_eur($pfand) : '—' }}
                        </td>
                        @endif
                        <td class="py-2 px-3">
                            <form method="POST" action="{{ route('cart.add') }}" class="flex gap-1 justify-end">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="number" name="qty" value="1" min="1" max="999"
                                       @disabled($isDisabled)
                                       class="w-14 border border-gray-200 rounded-lg px-2 py-1.5 text-sm text-center focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:bg-gray-100">
                                <button type="submit" @disabled($isDisabled)
                                        class="bg-amber-500 hover:bg-amber-600 disabled:bg-gray-200 disabled:text-gray-400 text-white text-xs font-medium rounded-lg px-3 py-1.5 transition-colors whitespace-nowrap">
                                    +
                                </button>
                            </form>
                        </td>
                        <td class="py-2 px-3">
                            @auth
                            @php $inFav = isset($favoriteProductIds[$product->id]); @endphp
                            @if($showFavBadge)
                            <form method="POST" action="{{ route('account.favorites.store') }}">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <button type="submit" title="{{ $inFav ? 'Im Stammsortiment' : 'Zum Stammsortiment' }}"
                                        class="text-lg {{ $inFav ? 'text-amber-500' : 'text-gray-300 hover:text-amber-400' }}">
                                    {{ $inFav ? '♥' : '♡' }}
                                </button>
                            </form>
                            @endif
                            @endauth
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Pagination --}}
        <div class="mt-8 flex items-center justify-between flex-wrap gap-4">
            <div>{{ $products->links() }}</div>
            {{-- Items per page selector --}}
            @if(count($availViews) > 0)
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <span>Pro Seite:</span>
                @foreach([24, 48, 96] as $n)
                <form method="POST" action="{{ route('shop.display_preferences.update') }}" style="display:inline">
                    @csrf
                    <input type="hidden" name="view_mode" value="{{ $viewMode }}">
                    <input type="hidden" name="items_per_page" value="{{ $n }}">
                    <button type="submit"
                            class="px-2 py-1 rounded-lg text-sm border transition-colors
                                   {{ $displaySettings['items_per_page'] == $n
                                      ? 'border-amber-400 bg-amber-50 text-amber-700 font-medium'
                                      : 'border-gray-200 text-gray-500 hover:border-gray-300' }}">
                        {{ $n }}
                    </button>
                </form>
                @endforeach
            </div>
            @endif
        </div>

        @endif {{-- /products isEmpty --}}
    </div>
</div>
@endsection
