@extends('shop.layout')

@section('title', $product->produktname . ' | Kolabri')

@push('head')
{{-- SEO meta --}}
<meta name="description" content="{{ $product->sales_unit_note ?: ($product->brand?->name . ' ' . $product->produktname) }}">
<link rel="canonical" href="{{ route('shop.product', $product) }}">

{{-- Schema.org JSON-LD --}}
<script type="application/ld+json">{!! json_encode($schemaOrg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
<div class="max-w-5xl mx-auto">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-400 mb-6">
        <a href="{{ route('shop.products') }}" class="hover:text-amber-600">Shop</a>
        @if($product->category)
            @if($product->category->parent)
                <span class="mx-1">/</span>
                <a href="{{ route('shop.products', ['kategorie' => $product->category->parent_id]) }}"
                   class="hover:text-amber-600">{{ $product->category->parent->name }}</a>
            @endif
            <span class="mx-1">/</span>
            <a href="{{ route('shop.products', ['kategorie' => $product->category_id]) }}"
               class="hover:text-amber-600">{{ $product->category->name }}</a>
        @endif
        <span class="mx-1">/</span>
        <span class="text-gray-600">{{ $product->produktname }}</span>
    </nav>

    <div class="grid md:grid-cols-2 gap-10">

        {{-- === Image gallery ================================================ --}}
        <div>
            @if($product->images->isNotEmpty())
                <div class="aspect-square bg-white rounded-2xl border border-gray-100 overflow-hidden mb-3">
                    <img id="main-img"
                         src="{{ Storage::url($product->images->first()->path) }}"
                         alt="{{ $product->images->first()->alt_text ?: $product->produktname }}"
                         class="w-full h-full object-contain p-4">
                </div>
                @if($product->images->count() > 1)
                    <div class="flex gap-2 flex-wrap">
                        @foreach($product->images as $img)
                            <button onclick="document.getElementById('main-img').src='{{ Storage::url($img->path) }}'"
                                    class="w-16 h-16 bg-white border-2 border-transparent hover:border-amber-400 rounded-lg overflow-hidden transition-colors">
                                <img src="{{ Storage::url($img->path) }}" alt="{{ $img->alt_text }}" class="w-full h-full object-contain p-1">
                            </button>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="aspect-square bg-white rounded-2xl border border-gray-100 overflow-hidden">
                    <img src="{{ $product->placeholderImageUrl() }}"
                         alt="{{ $product->produktname }}"
                         class="w-full h-full object-contain p-4">
                </div>
            @endif
        </div>

        {{-- === Product info ================================================= --}}
        <div>
            @if($product->brand)
                <p class="text-sm text-amber-600 font-medium mb-1">{{ $product->brand->name }}</p>
            @endif
            <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ $product->produktname }}</h1>
            <p class="text-sm text-gray-400 mb-4">Art.-Nr. {{ $product->artikelnummer }}</p>

            {{-- Availability --}}
            @if($product->availability_mode === 'preorder')
                <span class="inline-flex items-center gap-1.5 text-sm text-blue-600 font-medium mb-4">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span> Vorbestellung
                </span>
                @if($product->preorder_note)
                    <p class="text-sm text-blue-500 mb-2">{{ $product->preorder_note }}</p>
                @endif
            @elseif(!$stockAvailable)
                <span class="inline-flex items-center gap-1.5 text-sm text-red-500 font-medium mb-4">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span> Nicht auf Lager
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 text-sm text-green-600 font-medium mb-4">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span> Verfügbar
                </span>
            @endif

            {{-- Price block --}}
            @if($price)
                <div class="bg-amber-50 rounded-2xl p-4 mb-6">
                    @if($priceDisplayMode === 'netto')
                        {{-- Netto display (B2B) --}}
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-bold text-gray-900">{{ milli_to_eur($price->netMilli) }}</span>
                            <span class="text-sm text-gray-400">netto</span>
                        </div>
                        <p class="text-sm text-gray-500 mt-0.5">Brutto: {{ milli_to_eur($price->grossMilli) }} (inkl. {{ ($product->taxRate?->rate_basis_points ?? 1900) / 100 }}% MwSt.)</p>
                    @else
                        {{-- Brutto display (B2C) --}}
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-bold text-gray-900">{{ milli_to_eur($price->grossMilli) }}</span>
                            <span class="text-sm text-gray-400">inkl. {{ ($product->taxRate?->rate_basis_points ?? 1900) / 100 }}% MwSt.</span>
                        </div>
                        <p class="text-sm text-gray-500 mt-0.5">Netto: {{ milli_to_eur($price->netMilli) }}</p>
                    @endif
                    @if($pfand > 0)
                        <div class="mt-2 border-t border-amber-200 pt-2">
                            @if($isBusiness)
                                <span class="text-sm text-amber-700 font-medium">+ {{ milli_to_eur($pfand) }} Pfand</span>
                                <span class="text-xs text-amber-500 ml-1">(netto, zzgl. MwSt.)</span>
                            @else
                                <span class="text-sm text-amber-700 font-medium">+ {{ milli_to_eur($pfand) }} Pfand</span>
                                <span class="text-xs text-amber-500 ml-1">(zzgl. zum Produktpreis)</span>
                            @endif
                        </div>
                    @endif
                </div>
            @else
                <div class="bg-gray-50 rounded-2xl p-4 mb-6">
                    <p class="text-gray-400 italic text-sm">Preis auf Anfrage</p>
                </div>
            @endif

            {{-- Gebinde / package info --}}
            {{-- Grundpreis (PAngV) + Versandhinweis --}}
            @php $grundpreis = $product->grundpreis_text; @endphp
            <div class="text-xs text-gray-400 mb-4 space-y-0.5">
                @if($grundpreis)
                    <p><span class="font-medium text-gray-500">Grundpreis:</span> {{ $grundpreis }}</p>
                @endif
                <p>
                    zzgl.
                    @if(\Illuminate\Support\Facades\Route::has('shop.shipping'))
                        <a href="{{ route('shop.shipping') }}" class="underline hover:text-gray-600">Versandkosten</a>
                    @else
                        Versandkosten
                    @endif
                </p>
            </div>

            @if($product->gebinde)
                <p class="text-sm text-gray-500 mb-1">
                    <span class="font-medium">Gebinde:</span> {{ $product->gebinde->name }}
                </p>
            @endif
            @if($product->sales_unit_note)
                <p class="text-sm text-gray-500 mb-4">{{ $product->sales_unit_note }}</p>
            @endif

            {{-- Barcodes --}}
            @if($product->barcodes->isNotEmpty())
                <div class="mb-4">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">EAN / Barcodes</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($product->barcodes as $barcode)
                            <span class="inline-block bg-gray-100 text-gray-600 text-xs font-mono px-2 py-1 rounded-lg">
                                {{ $barcode->barcode }}
                                @if($barcode->type)
                                    <span class="text-gray-400">({{ $barcode->type }})</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Add to cart --}}
            @if($product->availability_mode !== 'discontinued')
                <form method="POST" action="{{ route('cart.add') }}" class="flex gap-3 mb-3">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="number" name="qty" value="1" min="1" max="999"
                           class="w-20 border border-gray-300 rounded-xl px-3 py-2 text-center text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    <button type="submit"
                            @disabled(!$stockAvailable || $product->availability_mode === 'out_of_stock')
                            class="flex-1 bg-amber-500 hover:bg-amber-600 disabled:bg-gray-200 disabled:text-gray-400 text-white font-semibold rounded-xl py-2.5 transition-colors">
                        {{ !$stockAvailable ? 'Nicht auf Lager' : 'In den Warenkorb' }}
                    </button>
                </form>
            @endif
            @auth
            <form method="POST" action="{{ route('account.favorites.store') }}" class="mb-4">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <button type="submit"
                        class="flex items-center justify-center gap-2 w-full text-sm font-medium rounded-xl py-2.5 border transition-colors
                               {{ $isFavorite
                                  ? 'bg-amber-50 border-amber-300 text-amber-600 hover:bg-amber-100'
                                  : 'bg-white border-gray-300 text-gray-600 hover:border-amber-400 hover:text-amber-600' }}">
                    <span class="text-base leading-none">{{ $isFavorite ? '♥' : '♡' }}</span>
                    <span>{{ $isFavorite ? 'Im Stammsortiment' : 'Zum Stammsortiment hinzufügen' }}</span>
                </button>
            </form>
            @endauth
        </div>
    </div>

    {{-- === Bundle components ================================================ --}}
    @if($product->isBundle() && !empty($bundleComponents))
        <div class="mt-12 border-t border-gray-200 pt-8">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Enthaltene Produkte</h2>
            <div class="bg-white rounded-2xl border border-gray-100 divide-y divide-gray-100">
                @foreach($bundleComponents as $componentData)
                    @php $comp = $componentData['product']; @endphp
                    <div class="flex items-center gap-4 p-4">
                        <div class="w-12 h-12 bg-gray-50 rounded-lg overflow-hidden flex-shrink-0">
                            @if($comp->mainImage)
                                <img src="{{ Storage::url($comp->mainImage->path) }}" alt="{{ $comp->produktname }}" class="w-full h-full object-contain p-1">
                            @else
                                <img src="{{ $comp->placeholderImageUrl() }}" alt="{{ $comp->produktname }}" class="w-full h-full object-contain p-1">
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ $comp->produktname }}</p>
                            @if($comp->brand)
                                <p class="text-xs text-gray-400">{{ $comp->brand->name }}</p>
                            @endif
                        </div>
                        <span class="text-sm font-medium text-gray-600">{{ $componentData['qty'] }}x</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- === LMIV / Product details =========================================== --}}
    @php
        $lmiv = $product->activeLmivVersion ?? $product->baseItem?->activeLmivVersion;
        // Alkohol: product-level field takes precedence, then LMIV, then null
        $alkohol = $product->alkoholgehalt_vol_percent
                   ?? ($lmiv?->alkoholgehalt)
                   ?? null;
    @endphp
    @if($alkohol !== null && $alkohol > 0)
        <div class="mt-8 border-t border-gray-200 pt-6">
            <div class="inline-flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-xl px-4 py-2 text-sm font-medium text-amber-800">
                <span>🍺</span>
                <span>Alkoholgehalt: {{ number_format($alkohol, 1, ',', '') }} % vol.</span>
                @if($alkohol >= 1.2)
                    <span class="text-xs font-normal text-amber-600 ml-1">· Nur für Erwachsene</span>
                @endif
            </div>
        </div>
    @endif
    @if($lmiv)
        <div class="mt-8 border-t border-gray-200 pt-8">
            <h2 class="text-lg font-bold text-gray-900 mb-6">Produktinformationen (LMIV)</h2>

            {{-- Alkohol already shown above --}}

            <div class="grid md:grid-cols-2 gap-6">

                {{-- Ingredients --}}
                @if($lmiv->zutaten)
                    <div class="bg-white rounded-2xl border border-gray-100 p-5">
                        <h3 class="font-semibold text-gray-700 mb-2">Zutaten</h3>
                        <p class="text-sm text-gray-600 leading-relaxed">{{ $lmiv->zutaten }}</p>
                    </div>
                @endif

                {{-- Nutritional values --}}
                @if($lmiv->naehrwert_energie_kj !== null)
                    <div class="bg-white rounded-2xl border border-gray-100 p-5">
                        <h3 class="font-semibold text-gray-700 mb-3">Nährwerte pro 100ml</h3>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @if($lmiv->naehrwert_energie_kj !== null)
                                    <tr><td class="py-1 text-gray-500">Energie</td><td class="py-1 text-right font-medium">{{ $lmiv->naehrwert_energie_kj }} kJ / {{ $lmiv->naehrwert_energie_kcal }} kcal</td></tr>
                                @endif
                                @if($lmiv->naehrwert_fett !== null)
                                    <tr><td class="py-1 text-gray-500">Fett</td><td class="py-1 text-right font-medium">{{ $lmiv->naehrwert_fett }} g</td></tr>
                                @endif
                                @if($lmiv->naehrwert_gesaettigte_fettsaeuren !== null)
                                    <tr><td class="py-1 pl-4 text-gray-400">davon gesättigt</td><td class="py-1 text-right text-gray-500">{{ $lmiv->naehrwert_gesaettigte_fettsaeuren }} g</td></tr>
                                @endif
                                @if($lmiv->naehrwert_kohlenhydrate !== null)
                                    <tr><td class="py-1 text-gray-500">Kohlenhydrate</td><td class="py-1 text-right font-medium">{{ $lmiv->naehrwert_kohlenhydrate }} g</td></tr>
                                @endif
                                @if($lmiv->naehrwert_zucker !== null)
                                    <tr><td class="py-1 pl-4 text-gray-400">davon Zucker</td><td class="py-1 text-right text-gray-500">{{ $lmiv->naehrwert_zucker }} g</td></tr>
                                @endif
                                @if($lmiv->naehrwert_eiweiss !== null)
                                    <tr><td class="py-1 text-gray-500">Eiweiß</td><td class="py-1 text-right font-medium">{{ $lmiv->naehrwert_eiweiss }} g</td></tr>
                                @endif
                                @if($lmiv->naehrwert_salz !== null)
                                    <tr><td class="py-1 text-gray-500">Salz</td><td class="py-1 text-right font-medium">{{ $lmiv->naehrwert_salz }} g</td></tr>
                                @endif
                                @if($lmiv->naehrwert_natrium !== null || $lmiv->naehrwert_calcium !== null || $lmiv->naehrwert_magnesium !== null)
                                    <tr><td colspan="2" class="py-1 pt-2 text-xs font-semibold text-gray-400 uppercase tracking-wide">Mineralien</td></tr>
                                @endif
                                @if($lmiv->naehrwert_natrium !== null)
                                    <tr><td class="py-1 text-gray-500">Natrium</td><td class="py-1 text-right font-medium">{{ $lmiv->naehrwert_natrium }} mg</td></tr>
                                @endif
                                @if($lmiv->naehrwert_calcium !== null)
                                    <tr><td class="py-1 text-gray-500">Calcium</td><td class="py-1 text-right font-medium">{{ $lmiv->naehrwert_calcium }} mg</td></tr>
                                @endif
                                @if($lmiv->naehrwert_magnesium !== null)
                                    <tr><td class="py-1 text-gray-500">Magnesium</td><td class="py-1 text-right font-medium">{{ $lmiv->naehrwert_magnesium }} mg</td></tr>
                                @endif
                                @if($lmiv->naehrwert_hydrogencarbonat !== null)
                                    <tr><td class="py-1 text-gray-500">Hydrogencarbonat</td><td class="py-1 text-right font-medium">{{ $lmiv->naehrwert_hydrogencarbonat }} mg</td></tr>
                                @endif
                                @if($lmiv->naehrwert_kalium !== null)
                                    <tr><td class="py-1 text-gray-500">Kalium</td><td class="py-1 text-right font-medium">{{ $lmiv->naehrwert_kalium }} mg</td></tr>
                                @endif
                                @if($lmiv->naehrwert_chlorid !== null)
                                    <tr><td class="py-1 text-gray-500">Chlorid</td><td class="py-1 text-right font-medium">{{ $lmiv->naehrwert_chlorid }} mg</td></tr>
                                @endif
                                @if($lmiv->naehrwert_sulfat !== null)
                                    <tr><td class="py-1 text-gray-500">Sulfat</td><td class="py-1 text-right font-medium">{{ $lmiv->naehrwert_sulfat }} mg</td></tr>
                                @endif
                                @if($lmiv->naehrwert_fluorid !== null)
                                    <tr><td class="py-1 text-gray-500">Fluorid</td><td class="py-1 text-right font-medium">{{ $lmiv->naehrwert_fluorid }} mg</td></tr>
                                @endif
                                @if($lmiv->naehrwert_kieselsaeure !== null)
                                    <tr><td class="py-1 text-gray-500">Kieselsäure</td><td class="py-1 text-right font-medium">{{ $lmiv->naehrwert_kieselsaeure }} mg</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Allergens --}}
                @if($lmiv->allergene)
                    <div class="bg-orange-50 rounded-2xl border border-orange-100 p-5">
                        <h3 class="font-semibold text-orange-800 mb-2">Allergene</h3>
                        <p class="text-sm text-orange-700">{{ $lmiv->allergene }}</p>
                    </div>
                @endif

                {{-- Producer / origin --}}
                @if($lmiv->hersteller_name || $lmiv->herkunftsland)
                    <div class="bg-white rounded-2xl border border-gray-100 p-5">
                        <h3 class="font-semibold text-gray-700 mb-2">Hersteller & Herkunft</h3>
                        @if($lmiv->hersteller_name)
                            <p class="text-sm text-gray-600">{{ $lmiv->hersteller_name }}</p>
                        @endif
                        @if($lmiv->hersteller_anschrift)
                            <p class="text-sm text-gray-500">{{ $lmiv->hersteller_anschrift }}</p>
                        @endif
                        @if($lmiv->herkunftsland)
                            <p class="text-sm text-gray-500 mt-1">Herkunft: {{ $lmiv->herkunftsland }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif

</div>
@endsection
