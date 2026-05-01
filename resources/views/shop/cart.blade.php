@extends('shop.layout')

@section('title', 'Warenkorb')

@section('content')
<h1 class="text-2xl font-bold text-gray-900 mb-6">Warenkorb</h1>

{{-- Flash messages --}}
@if(session('warning'))
    <div class="mb-4 p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-sm">
        {{ session('warning') }}
    </div>
@endif
@if(session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-xl text-sm">
        {{ session('success') }}
    </div>
@endif

@if($isEmpty)
    <div class="text-center py-20 text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 9m5-9v9m4-9v9m5-9l2 9"/></svg>
        <p class="text-lg font-medium">Dein Warenkorb ist leer</p>
        <a href="{{ route('shop.index') }}" class="mt-3 inline-block bg-amber-500 text-white px-6 py-2 rounded-xl hover:bg-amber-600 transition-colors text-sm font-medium">Weiter einkaufen</a>
    </div>
@else
    {{-- Warning banner for unavailable products --}}
    @if($hasUnavailable)
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-xl text-sm">
            <strong>Achtung:</strong> Einige Produkte in deinem Warenkorb sind nicht mehr verfuegbar.
            Bitte entferne die markierten Produkte, bevor du zur Kasse gehst.
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-8">

        {{-- Cart items --}}
        <div class="lg:col-span-2 space-y-3">

            {{-- ── Festbedarf (Leihartikel) ── --}}
            @if($rentalSummary->isNotEmpty())
                <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide pt-1">Festbedarf</h2>
                <div class="bg-blue-50 rounded-2xl border border-blue-200 p-4">
                    @if($rentalFrom && $rentalUntil)
                        <p class="text-xs text-gray-500 mb-3">
                            Zeitraum: <strong class="text-gray-700">{{ $rentalFrom->format('d.m.Y') }} – {{ $rentalUntil->format('d.m.Y') }}</strong>
                        </p>
                    @endif
                    <div class="space-y-2">
                        @foreach($rentalSummary as $row)
                            <div class="flex items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800">{{ $row['item']->name }}</p>
                                    <p class="text-xs text-gray-500">
                                        @if($row['packaging_unit'])
                                            {{ $row['packaging_unit']->label }} × {{ $row['qty'] }}
                                        @else
                                            {{ $row['qty'] }} {{ $row['item']->unit_label }}
                                        @endif
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    @if($row['price_found'] && $row['total_price_net_milli'])
                                        <p class="text-sm font-semibold text-gray-900">{{ milli_to_eur($row['total_price_net_milli']) }}</p>
                                        <p class="text-xs text-gray-400">netto</p>
                                    @else
                                        <p class="text-xs text-gray-400">Preis auf Anfrage</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3 pt-2 border-t border-blue-200 flex justify-between items-center text-xs text-gray-500">
                        <a href="{{ route('rental.catalog') }}" class="text-blue-600 hover:underline">Festbedarf ändern →</a>
                        @if($rentalTotal > 0)
                            <span>Summe (netto): <strong class="text-gray-800">{{ milli_to_eur($rentalTotal) }}</strong></span>
                        @endif
                    </div>
                </div>
            @endif

            {{-- ── Getränke ── --}}
            @if(!empty($items))
                @if($rentalSummary->isNotEmpty())
                    <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide pt-2">Getränke</h2>
                @endif
                @foreach($items as $productId => $item)
                    @php
                        $product      = $item['product'];
                        $qty          = $item['qty'];
                        $price        = $item['price'] ?? null;
                        $pfandPerUnit = $item['pfand_per_unit'] ?? 0;
                        $lineGross    = $item['line_gross'] ?? 0;
                        $linePfand    = $item['line_pfand'] ?? 0;
                        $unavailable  = $item['unavailable'] ?? false;
                    @endphp
                    <div class="bg-white rounded-2xl border {{ $unavailable ? 'border-red-300 bg-red-50' : 'border-gray-100' }} p-4 flex gap-4 items-start">

                        {{-- Thumbnail --}}
                        <a href="{{ route('shop.product', $product) }}" class="shrink-0 w-16 h-16 bg-gray-50 rounded-xl overflow-hidden {{ $unavailable ? 'opacity-50' : '' }}">
                            @if($product->mainImage)
                                <img src="{{ Storage::url($product->mainImage->path) }}" alt="" class="w-full h-full object-contain p-1">
                            @else
                                <img src="{{ $product->placeholderImageUrl() }}" alt="{{ $product->produktname }}" class="w-full h-full object-contain p-1">
                            @endif
                        </a>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('shop.product', $product) }}" class="font-medium text-gray-900 hover:text-amber-600 text-sm line-clamp-2 {{ $unavailable ? 'line-through opacity-60' : '' }}">
                                {{ $product->produktname }}
                            </a>
                            @if($unavailable)
                                <p class="text-xs text-red-600 font-medium mt-0.5">Nicht mehr verfuegbar</p>
                            @else
                                @if($price)
                                    <p class="text-sm text-gray-500 mt-0.5">{{ milli_to_eur($price->grossMilli) }} / Stk.</p>
                                @endif
                                @if($pfandPerUnit > 0)
                                    <p class="text-xs text-amber-600 mt-0.5">+ {{ milli_to_eur($pfandPerUnit) }} Pfand / Stk.</p>
                                @endif
                            @endif
                            <p class="text-xs text-gray-400 mt-0.5">Art.-Nr.: {{ $product->artikelnummer }}</p>
                        </div>

                        {{-- Qty + Remove --}}
                        <div class="flex flex-col items-end gap-2 shrink-0">
                            @if(!$unavailable)
                                <form method="POST" action="{{ route('cart.update', $productId) }}" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <input type="number" name="qty" value="{{ $qty }}" min="0" max="999"
                                           class="w-16 border border-gray-300 rounded-lg px-2 py-1 text-center text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                                    <button type="submit" class="text-xs text-gray-400 hover:text-amber-600 underline">Aktualisieren</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('cart.remove', $productId) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-600">Entfernen</button>
                            </form>

                            {{-- Line total --}}
                            @if($price && !$unavailable)
                                <div class="text-right">
                                    <p class="font-bold text-gray-900 text-sm">{{ milli_to_eur($lineGross) }}</p>
                                    @if($linePfand > 0)
                                        <p class="text-xs text-amber-600">+ {{ milli_to_eur($linePfand) }} Pfand</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif

            {{-- Action bar --}}
            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('shop.index') }}" class="text-sm text-gray-400 hover:text-amber-600">
                    &larr; Weiter einkaufen
                </a>
                @if(!empty($items))
                    <form method="POST" action="{{ route('cart.clear') }}" id="cart-clear-form">
                        @csrf @method('DELETE')
                        <button type="button" class="text-sm text-red-400 hover:text-red-600"
                                onclick="shopConfirm('Warenkorb leeren', 'Alle Getränke aus dem Warenkorb entfernen?', 'Leeren').then(function(ok){ if(ok) document.getElementById('cart-clear-form').submit(); })">
                            Getränke leeren
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Order summary --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl border border-gray-100 p-6 sticky top-24">
                <h2 class="font-bold text-gray-900 mb-4">Bestellübersicht</h2>

                <div class="space-y-2 text-sm">
                    {{-- Getränke breakdown --}}
                    @if(!empty($items))
                        <div class="flex justify-between text-gray-600">
                            <span>Getränke (brutto)</span>
                            <span>{{ milli_to_eur($subtotalGross) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-500 text-xs">
                            <span>Netto-Betrag</span>
                            <span>{{ milli_to_eur($subtotalNet) }}</span>
                        </div>
                        @foreach($taxBreakdown as $tax)
                            <div class="flex justify-between text-gray-500 text-xs">
                                <span>{{ number_format($tax['rate'], 0) }}% MwSt.</span>
                                <span>{{ milli_to_eur($tax['tax_milli']) }}</span>
                            </div>
                        @endforeach
                        @if($pfandTotal > 0)
                            <div class="flex justify-between text-amber-600">
                                <span>Pfand</span>
                                <span>{{ milli_to_eur($pfandTotal) }}</span>
                            </div>
                        @endif
                    @endif

                    {{-- Festbedarf breakdown --}}
                    @if($rentalTotal > 0)
                        <div class="flex justify-between text-blue-700">
                            <span>Festbedarf (netto)</span>
                            <span>{{ milli_to_eur($rentalTotal) }}</span>
                        </div>
                    @endif

                    <div class="border-t border-gray-200 pt-2 flex justify-between font-bold text-gray-900">
                        <span>Gesamtbetrag</span>
                        <span>{{ milli_to_eur($grandTotal) }}</span>
                    </div>
                </div>

                @if(isset($minAge) && $minAge > 0)
                <div class="mt-4 flex items-start gap-2 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-800">
                    <svg class="w-4 h-4 mt-0.5 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    <span>{{ \App\Services\Catalog\JugendschutzService::checkoutWarning($minAge) }}</span>
                </div>
                @endif

                <div class="mt-6 space-y-2">
                    @auth
                        @if(Auth::user()->isKunde())
                            @if($hasUnavailable)
                                <button disabled
                                        class="block w-full bg-gray-300 text-gray-500 text-center font-semibold rounded-xl py-3 cursor-not-allowed">
                                    Zur Kasse (nicht verfuegbare Produkte entfernen)
                                </button>
                            @else
                                <a href="{{ route('checkout') }}"
                                   class="block w-full bg-amber-500 hover:bg-amber-600 text-white text-center font-semibold rounded-xl py-3 transition-colors">
                                    Zur Kasse
                                </a>
                            @endif
                        @else
                            <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-center">
                                <p class="text-sm font-semibold text-amber-800 mb-1">Nur für Kundenkonten</p>
                                <p class="text-xs text-amber-700 mb-3">Mit deinem aktuellen Konto sind keine Bestellungen möglich. Bitte melde dich mit einem Kundenkonto an.</p>
                                <a href="{{ route('logout') }}"
                                   onclick="event.preventDefault(); document.getElementById('cart-logout-form').submit();"
                                   class="block w-full bg-amber-500 hover:bg-amber-600 text-white text-center font-semibold rounded-lg py-2.5 transition-colors text-sm">
                                    Anderes Konto anmelden
                                </a>
                                <form id="cart-logout-form" action="{{ route('logout') }}" method="POST" style="display:none">@csrf</form>
                            </div>
                        @endif
                    @else
                        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-center">
                            <p class="text-sm font-semibold text-amber-800 mb-1">Zum Bestellen anmelden</p>
                            <p class="text-xs text-amber-700 mb-3">Bestellungen sind nur für registrierte Kunden möglich.</p>
                        </div>
                        <a href="{{ route('login') }}"
                           class="block w-full bg-amber-500 hover:bg-amber-600 text-white text-center font-semibold rounded-xl py-3 transition-colors">
                            Anmelden & bestellen
                        </a>
                        <a href="{{ route('register') }}"
                           class="block w-full border border-amber-400 text-amber-600 text-center font-medium rounded-xl py-2.5 hover:bg-amber-50 transition-colors text-sm">
                            Neu registrieren
                        </a>
                    @endauth
                </div>
            </div>
        </div>

    </div>
@endif
@endsection
