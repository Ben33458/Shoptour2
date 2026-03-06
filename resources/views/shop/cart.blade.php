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

@if(empty($items))
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
                            <div class="w-full h-full flex items-center justify-center text-2xl opacity-20">
                                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
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

            {{-- Action bar --}}
            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('shop.index') }}" class="text-sm text-gray-400 hover:text-amber-600">
                    &larr; Weiter einkaufen
                </a>
                <form method="POST" action="{{ route('cart.clear') }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-sm text-red-400 hover:text-red-600"
                            onclick="return confirm('Warenkorb wirklich leeren?')">
                        Warenkorb leeren
                    </button>
                </form>
            </div>
        </div>

        {{-- Order summary --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl border border-gray-100 p-6 sticky top-24">
                <h2 class="font-bold text-gray-900 mb-4">Bestelluebersicht</h2>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span>Zwischensumme (brutto)</span>
                        <span>{{ milli_to_eur($subtotalGross) }}</span>
                    </div>
                    <div class="flex justify-between text-gray-500 text-xs">
                        <span>Netto-Betrag</span>
                        <span>{{ milli_to_eur($subtotalNet) }}</span>
                    </div>

                    {{-- Tax breakdown by rate --}}
                    @foreach($taxBreakdown as $tax)
                        <div class="flex justify-between text-gray-500 text-xs">
                            <span>{{ number_format($tax['rate'], 0) }}% MwSt.</span>
                            <span>{{ milli_to_eur($tax['tax_milli']) }}</span>
                        </div>
                    @endforeach

                    @if($pfandTotal > 0)
                        <div class="flex justify-between text-amber-600">
                            <span>Pfand gesamt</span>
                            <span>{{ milli_to_eur($pfandTotal) }}</span>
                        </div>
                    @endif
                    <div class="border-t border-gray-200 pt-2 flex justify-between font-bold text-gray-900">
                        <span>Gesamtbetrag</span>
                        <span>{{ milli_to_eur($grandTotal) }}</span>
                    </div>
                </div>

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
                            <p class="text-xs text-gray-400 text-center">Bestellungen sind nur fuer Kundenkonto moeglich.</p>
                        @endif
                    @else
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
