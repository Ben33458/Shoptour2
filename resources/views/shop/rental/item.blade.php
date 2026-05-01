@extends('shop.layout')

@section('title', $item->name . ' — Leihen')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">

    {{-- Breadcrumb ---------------------------------------------------------}}
    <nav class="text-sm text-gray-500 mb-6 flex items-center gap-1">
        <a href="{{ route('rental.landing') }}" class="hover:underline">Leihen</a>
        <span>/</span>
        <a href="{{ route('rental.catalog') }}" class="hover:underline">Katalog</a>
        <span>/</span>
        <span class="text-gray-800">{{ $item->name }}</span>
    </nav>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8">

        {{-- Title & Category -------------------------------------------}}
        <div class="mb-6">
            @if($item->category)
                <span class="text-xs text-blue-600 font-medium uppercase tracking-wide">{{ $item->category->name }}</span>
            @endif
            <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $item->name }}</h1>
            @if($item->description)
                <p class="text-gray-600 mt-2">{{ $item->description }}</p>
            @endif
        </div>

        {{-- Date Context -----------------------------------------------}}
        @if($from && $until)
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-6 text-sm text-blue-800">
                Mietzeitraum:
                <strong>{{ $from->format('d.m.Y') }}</strong> bis
                <strong>{{ $until->format('d.m.Y') }}</strong>
                @if($timeModel)
                    · {{ $timeModel->name }}
                @endif
                <a href="{{ route('rental.landing') }}" class="ml-2 underline text-blue-600">ändern</a>
            </div>
        @else
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-6 text-sm text-amber-700">
                ⚠ Kein Mietzeitraum gewählt.
                <a href="{{ route('rental.landing') }}" class="underline">Zeitraum wählen</a> um Verfügbarkeit zu prüfen.
            </div>
        @endif

        {{-- Availability Badge -----------------------------------------}}
        @if($from && $until && $available !== null)
            @if($available === 0 && !$item->allow_overbooking)
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-6 text-sm text-red-700">
                    Für den gewählten Zeitraum leider nicht verfügbar.
                </div>
            @elseif($available <= 3 && !$item->allow_overbooking)
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-6 text-sm text-amber-700">
                    Nur noch <strong>{{ $available }}</strong> verfügbar.
                </div>
            @else
                <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-6 text-sm text-green-700">
                    Für den gewählten Zeitraum verfügbar.
                </div>
            @endif
        @endif

        {{-- Add to Cart Form -------------------------------------------}}
        @if(!$from || !$until)
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-5 text-sm text-amber-800">
                ⚠ Kein Mietzeitraum gewählt — Verfügbarkeit wird nach
                <a href="{{ route('rental.landing') }}" class="underline">Zeitraumwahl</a> gezeigt.
                Du kannst den Artikel trotzdem vormerken.
            </div>
        @elseif($available === 0 && !$item->allow_overbooking)
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-5 text-sm text-red-700">
                ⚠ Für den gewählten Zeitraum leider ausgebucht. Bitte anderen Zeitraum wählen oder
                <a href="mailto:" class="underline">kontaktiere uns</a> direkt.
            </div>
        @endif
        @if($available !== 0 || $item->allow_overbooking || !$from || !$until)
            <form action="{{ route('rental.cart.add') }}" method="POST" class="space-y-5">
                @csrf
                <input type="hidden" name="rental_item_id" value="{{ $item->id }}">

                {{-- Packaging Unit Selection --}}
                @php $activePackaging = $item->packagingUnits->where('active', true); @endphp
                @if($activePackaging->isNotEmpty())
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Verpackungseinheit</label>
                        @foreach($activePackaging as $pu)
                            <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg mb-2 cursor-pointer hover:border-blue-400 transition">
                                <input type="radio" name="packaging_unit_id" value="{{ $pu->id }}"
                                    {{ $loop->first ? 'checked' : '' }}
                                    class="text-blue-600">
                                <span class="flex-1 text-sm">
                                    <span class="font-medium">{{ $pu->label }}</span>
                                    <span class="text-gray-500"> ({{ $pu->pieces_per_pack }} {{ $item->unit_label }})</span>
                                </span>
                                @if($item->price_on_request)
                                    <span class="text-xs text-amber-600 font-medium">Preis auf Anfrage</span>
                                @elseif(isset($priceMap[$pu->id]))
                                    @php
                                        $puPriceMilli      = $priceMap[$pu->id];
                                        $perPieceMilli     = $pu->pieces_per_pack > 1
                                            ? intval(round($puPriceMilli / $pu->pieces_per_pack))
                                            : null;
                                    @endphp
                                    <div class="text-right">
                                        <div class="text-sm font-semibold text-gray-900">
                                            {{ milli_to_eur($puPriceMilli) }}
                                            <span class="text-xs text-gray-500 font-normal"> / VPE</span>
                                        </div>
                                        @if($perPieceMilli)
                                            <div class="text-xs text-gray-400">
                                                = {{ milli_to_eur($perPieceMilli) }} / {{ $item->unit_label }}
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </label>
                        @endforeach
                    </div>
                @else
                    {{-- No packaging units, show base price --}}
                    @if($item->price_on_request)
                        <div class="text-sm text-amber-600 font-medium">Preis auf Anfrage</div>
                    @elseif(isset($priceMap['base']) && $priceMap['base'] !== null)
                        <div class="text-lg font-semibold text-gray-900">
                            {{ milli_to_eur($priceMap['base']) }}
                            <span class="text-sm font-normal text-gray-500">netto / {{ $item->unit_label }}</span>
                        </div>
                    @endif
                @endif

                {{-- Quantity --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Menge ({{ $item->unit_label }})
                        @if($activePackaging->isNotEmpty())
                            <span class="font-normal text-gray-500">— Anzahl Einheiten</span>
                        @endif
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="number" name="qty"
                            value="{{ $cartEntry['qty'] ?? 1 }}"
                            min="1" max="{{ $available && !$item->allow_overbooking ? $available : 9999 }}"
                            class="w-24 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                        @if($available !== null && !$item->allow_overbooking)
                            <span class="text-sm text-gray-500">max. {{ $available }}</span>
                        @endif
                    </div>
                </div>

                <div class="flex gap-3 flex-wrap mt-2">
                    <button type="submit"
                        style="background-color:#2563eb;color:#fff"
                        class="font-semibold px-6 py-3 rounded-xl transition hover:opacity-90">
                        @if($cartEntry)
                            Menge aktualisieren
                        @else
                            In den Warenkorb
                        @endif
                    </button>
                    <a href="{{ route('rental.catalog') }}"
                        class="border border-gray-300 text-gray-700 hover:bg-gray-50 px-6 py-3 rounded-xl transition">
                        Zurück zum Katalog
                    </a>
                </div>
            </form>
        @endif

    </div>

</div>
@endsection
