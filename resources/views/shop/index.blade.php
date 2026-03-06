@extends('shop.layout')

@section('title', 'Produkte')

@section('content')
<div class="flex gap-8" x-data="{ filterOpen: false }">

    {{-- === Mobile filter button (visible < lg) ============================== --}}
    <button @click="filterOpen = true"
            class="lg:hidden fixed bottom-4 right-4 z-30 bg-amber-500 hover:bg-amber-600 text-white font-medium text-sm px-4 py-3 rounded-full shadow-lg flex items-center gap-2 transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
        Filter
    </button>

    {{-- === Mobile backdrop =================================================== --}}
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

        {{-- Mobile: close button --}}
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
                <a href="{{ route('shop.products', array_filter(['suche' => $search, 'brand' => $brandId, 'gebinde' => $gebindeId, 'warengruppe' => $warengruppeId, 'sort' => $sort])) }}"
                   class="block px-3 py-1.5 rounded-lg text-sm {{ !$categoryId ? 'bg-amber-100 text-amber-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                    Alle Kategorien
                </a>
                @foreach($categories as $cat)
                    <a href="{{ route('shop.products', array_filter(['kategorie' => $cat->id, 'suche' => $search, 'brand' => $brandId, 'gebinde' => $gebindeId, 'warengruppe' => $warengruppeId, 'sort' => $sort])) }}"
                       class="block px-3 py-1.5 rounded-lg text-sm {{ $categoryId == $cat->id ? 'bg-amber-100 text-amber-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                        {{ $cat->name }}
                    </a>
                    @if($cat->children->isNotEmpty())
                        @foreach($cat->children as $child)
                            <a href="{{ route('shop.products', array_filter(['kategorie' => $child->id, 'suche' => $search, 'brand' => $brandId, 'gebinde' => $gebindeId, 'warengruppe' => $warengruppeId, 'sort' => $sort])) }}"
                               class="block px-3 py-1.5 pl-6 rounded-lg text-sm {{ $categoryId == $child->id ? 'bg-amber-100 text-amber-700 font-medium' : 'text-gray-400 hover:bg-gray-100' }}">
                                {{ $child->name }}
                            </a>
                        @endforeach
                    @endif
                @endforeach
            </nav>
        </div>

        {{-- Brand filter --}}
        @if($brands->isNotEmpty())
        <div>
            <h2 class="font-semibold text-sm text-gray-500 uppercase tracking-wide mb-3">Marken</h2>
            <nav class="space-y-1 max-h-48 overflow-y-auto">
                <a href="{{ route('shop.products', array_filter(['kategorie' => $categoryId, 'suche' => $search, 'gebinde' => $gebindeId, 'warengruppe' => $warengruppeId, 'sort' => $sort])) }}"
                   class="block px-3 py-1.5 rounded-lg text-sm {{ !$brandId ? 'bg-amber-100 text-amber-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                    Alle Marken
                </a>
                @foreach($brands as $brand)
                    <a href="{{ route('shop.products', array_filter(['kategorie' => $categoryId, 'brand' => $brand->id, 'suche' => $search, 'gebinde' => $gebindeId, 'warengruppe' => $warengruppeId, 'sort' => $sort])) }}"
                       class="block px-3 py-1.5 rounded-lg text-sm {{ $brandId == $brand->id ? 'bg-amber-100 text-amber-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                        {{ $brand->name }}
                    </a>
                @endforeach
            </nav>
        </div>
        @endif

        {{-- Warengruppe filter --}}
        @if($warengruppen->isNotEmpty())
        <div>
            <h2 class="font-semibold text-sm text-gray-500 uppercase tracking-wide mb-3">Warengruppen</h2>
            <nav class="space-y-1 max-h-48 overflow-y-auto">
                <a href="{{ route('shop.products', array_filter(['kategorie' => $categoryId, 'brand' => $brandId, 'suche' => $search, 'gebinde' => $gebindeId, 'sort' => $sort])) }}"
                   class="block px-3 py-1.5 rounded-lg text-sm {{ !$warengruppeId ? 'bg-amber-100 text-amber-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                    Alle Warengruppen
                </a>
                @foreach($warengruppen as $wg)
                    <a href="{{ route('shop.products', array_filter(['kategorie' => $categoryId, 'brand' => $brandId, 'suche' => $search, 'gebinde' => $gebindeId, 'warengruppe' => $wg->id, 'sort' => $sort])) }}"
                       class="block px-3 py-1.5 rounded-lg text-sm {{ $warengruppeId == $wg->id ? 'bg-amber-100 text-amber-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                        {{ $wg->name }}
                    </a>
                @endforeach
            </nav>
        </div>
        @endif

        {{-- Gebinde filter --}}
        @if($gebindeList->isNotEmpty())
        <div>
            <h2 class="font-semibold text-sm text-gray-500 uppercase tracking-wide mb-3">Gebinde</h2>
            <nav class="space-y-1 max-h-48 overflow-y-auto">
                <a href="{{ route('shop.products', array_filter(['kategorie' => $categoryId, 'brand' => $brandId, 'suche' => $search, 'warengruppe' => $warengruppeId, 'sort' => $sort])) }}"
                   class="block px-3 py-1.5 rounded-lg text-sm {{ !$gebindeId ? 'bg-amber-100 text-amber-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                    Alle Gebinde
                </a>
                @foreach($gebindeList as $geb)
                    <a href="{{ route('shop.products', array_filter(['kategorie' => $categoryId, 'brand' => $brandId, 'suche' => $search, 'gebinde' => $geb->id, 'warengruppe' => $warengruppeId, 'sort' => $sort])) }}"
                       class="block px-3 py-1.5 rounded-lg text-sm {{ $gebindeId == $geb->id ? 'bg-amber-100 text-amber-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                        {{ $geb->name }}
                    </a>
                @endforeach
            </nav>
        </div>
        @endif

    </aside>

    {{-- === Main content ===================================================== --}}
    <div class="flex-1 min-w-0">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <h1 class="text-2xl font-bold text-gray-900">
                @if($search) Suchergebnisse für &bdquo;{{ $search }}&ldquo; @else Getränke @endif
            </h1>
            <div class="flex items-center gap-4">
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
            </div>
        </div>

        {{-- Mobile search --}}
        <form action="{{ route('shop.products') }}" method="GET" class="sm:hidden mb-4">
            <div class="relative">
                <input type="text" name="suche" value="{{ $search }}" placeholder="Produkte suchen (Name, Art.-Nr., Barcode)..."
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
            {{-- Product grid --}}
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($products as $product)
                    @php
                        $pd             = $priceData[$product->id] ?? null;
                        $price          = $pd['price'] ?? null;
                        $pfand          = $pd['pfand'] ?? 0;
                        $stockAvailable = $pd['stock_available'] ?? true;
                    @endphp
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow flex flex-col">

                        {{-- Image --}}
                        <a href="{{ route('shop.product', $product) }}" class="block aspect-square bg-gray-50 rounded-t-2xl overflow-hidden relative">
                            @if($product->mainImage)
                                <img src="{{ Storage::url($product->mainImage->path) }}"
                                     alt="{{ $product->mainImage->alt_text ?: $product->produktname }}"
                                     class="w-full h-full object-contain p-2"
                                     loading="lazy">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-300">
                                    <svg class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            @endif

                            {{-- Stock-based unavailability badge --}}
                            @if(!$stockAvailable)
                                <span class="absolute top-2 right-2 bg-red-500 text-white text-xs font-medium px-2 py-0.5 rounded-full">
                                    Nicht verfügbar
                                </span>
                            @endif
                        </a>

                        <div class="p-3 flex flex-col flex-1">
                            {{-- Brand --}}
                            @if($product->brand)
                                <p class="text-xs text-gray-400 mb-0.5">{{ $product->brand->name }}</p>
                            @endif

                            {{-- Name --}}
                            <a href="{{ route('shop.product', $product) }}"
                               class="text-sm font-medium text-gray-900 hover:text-amber-600 line-clamp-2 leading-snug flex-1">
                                {{ $product->produktname }}
                            </a>

                            {{-- Price --}}
                            <div class="mt-2">
                                @if($price)
                                    @if($priceDisplayMode === 'netto')
                                        <p class="text-base font-bold text-gray-900">{{ milli_to_eur($price->netMilli) }} <span class="text-xs font-normal text-gray-400">netto</span></p>
                                    @else
                                        <p class="text-base font-bold text-gray-900">{{ milli_to_eur($price->grossMilli) }}</p>
                                    @endif
                                    @if($pfand > 0)
                                        @if($isBusiness)
                                            <p class="text-xs text-amber-600">+ {{ milli_to_eur($pfand) }} Pfand <span class="text-amber-400">(netto, zzgl. MwSt.)</span></p>
                                        @else
                                            <p class="text-xs text-amber-600">+ {{ milli_to_eur($pfand) }} Pfand</p>
                                        @endif
                                    @endif
                                @else
                                    <p class="text-sm text-gray-400 italic">Preis auf Anfrage</p>
                                @endif
                            </div>

                            {{-- Availability badge --}}
                            @if($product->availability_mode === 'out_of_stock')
                                <p class="mt-1 text-xs text-red-500 font-medium">Nicht verfügbar</p>
                            @elseif($product->availability_mode === 'preorder')
                                <p class="mt-1 text-xs text-blue-500 font-medium">Vorbestellung</p>
                            @endif

                            {{-- Add to cart --}}
                            @php
                                $isDisabled = $product->availability_mode === 'discontinued'
                                           || $product->availability_mode === 'out_of_stock'
                                           || !$stockAvailable;
                            @endphp
                            <form method="POST" action="{{ route('cart.add') }}" class="mt-3">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <div class="flex gap-2">
                                    <input type="number" name="qty" value="1" min="1" max="999"
                                           @disabled($isDisabled)
                                           class="w-16 border border-gray-200 rounded-xl px-2 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:bg-gray-100">
                                    <button type="submit"
                                            @disabled($isDisabled)
                                            class="flex-1 bg-amber-500 hover:bg-amber-600 disabled:bg-gray-200 disabled:text-gray-400 text-white text-sm font-medium rounded-xl py-2 transition-colors">
                                        @if(!$stockAvailable)
                                            Nicht verfügbar
                                        @elseif($product->availability_mode === 'discontinued')
                                            Eingestellt
                                        @elseif($product->availability_mode === 'out_of_stock')
                                            Nicht verfügbar
                                        @else
                                            In den Warenkorb
                                        @endif
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="mt-8">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
