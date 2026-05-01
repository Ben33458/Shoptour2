@extends('shop.layout')

@section('title', 'Leih-Warenkorb')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">

    <h1 class="text-2xl font-bold text-gray-900 mb-6">Leih-Warenkorb</h1>

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

    {{-- Date / Time Model Context ----------------------------------}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div class="text-sm text-blue-800">
            @if($from && $until)
                <span class="font-semibold">{{ $from->format('d.m.Y') }}</span>
                bis
                <span class="font-semibold">{{ $until->format('d.m.Y') }}</span>
                @if($timeModel)
                    · {{ $timeModel->name }}
                @endif
            @else
                <span class="text-amber-700">Kein Mietzeitraum gewählt</span>
            @endif
        </div>
        <a href="{{ route('rental.landing') }}" class="text-xs text-blue-600 underline whitespace-nowrap">
            Zeitraum ändern
        </a>
    </div>

    @if($summary->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <p class="text-lg mb-4">Dein Leih-Warenkorb ist leer.</p>
            <a href="{{ route('rental.catalog') }}"
                class="bg-blue-600 text-white px-6 py-3 rounded-xl hover:bg-blue-700 transition">
                Zum Katalog
            </a>
        </div>
    @else

        {{-- Cart Items -------------------------------------------------}}
        <div class="space-y-3 mb-6">
            @foreach($summary as $row)
                <div class="bg-white rounded-xl border border-gray-200 p-4 flex flex-row flex-wrap items-center gap-3 w-full">
                    <div class="flex-1 min-w-0 shrink">
                        <div class="font-medium text-gray-800">{{ $row['item']->name }}</div>
                        @if($row['packaging_unit'])
                            <div class="text-xs text-gray-500">
                                {{ $row['packaging_unit']->label }}
                                ({{ $row['pieces'] }} {{ $row['item']->unit_label }})
                            </div>
                        @else
                            <div class="text-xs text-gray-500">{{ $row['qty'] }} {{ $row['item']->unit_label }}</div>
                        @endif

                        {{-- Availability warning --}}
                        @if($from && $until && $row['available_qty'] !== null && $row['available_qty'] < $row['qty'] && !$row['item']->allow_overbooking)
                            <div class="text-xs text-red-600 mt-1">
                                ⚠ Nur {{ $row['available_qty'] }} verfügbar — bitte Menge anpassen
                            </div>
                        @endif
                    </div>

                    {{-- Qty update --}}
                    <form action="{{ route('rental.cart.update', $row['item']->id) }}" method="POST"
                        class="flex items-center gap-2">
                        @csrf
                        @method('PUT')
                        <input type="number" name="qty" value="{{ $row['qty'] }}"
                            min="1" max="9999"
                            class="w-20 border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-center">
                        <button type="submit"
                            class="text-xs text-blue-600 underline hover:no-underline">
                            Ändern
                        </button>
                    </form>

                    {{-- Price --}}
                    <div class="text-right min-w-[80px]">
                        @if($row['price_found'])
                            <div class="font-semibold text-gray-900">{{ milli_to_eur($row['total_price_net_milli']) }}</div>
                            <div class="text-xs text-gray-500">{{ milli_to_eur($row['unit_price_net_milli']) }} / Stk.</div>
                        @else
                            <div class="text-xs text-gray-400">Preis auf Anfrage</div>
                        @endif
                    </div>

                    {{-- Remove --}}
                    <form action="{{ route('rental.cart.remove', $row['item']->id) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="text-gray-400 hover:text-red-500 transition"
                            title="Entfernen">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </form>
                </div>
            @endforeach
        </div>

        {{-- Total & Actions -------------------------------------------}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
            <div class="flex justify-between items-center">
                <span class="font-medium text-gray-700">Gesamt (netto)</span>
                <span class="text-xl font-bold text-gray-900">
                    @if($total > 0)
                        {{ milli_to_eur($total) }}
                    @else
                        auf Anfrage
                    @endif
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">zzgl. gesetzl. MwSt. · Preise können je nach Kundenkategorie abweichen</p>
        </div>

        {{-- CTA -----------------------------------------------------------}}
        @auth
            @php $user = auth()->user(); @endphp
            @if($user->isKunde() || $user->isSubUser())
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('rental.checkout') }}"
                        style="background-color:#2563eb;color:#fff"
                        class="flex-1 hover:bg-blue-700 font-semibold px-8 py-3 rounded-xl transition text-center block">
                        Weiter zur Bestellung →
                    </a>
                    <a href="{{ route('rental.catalog') }}"
                        class="border border-gray-300 text-gray-700 hover:bg-gray-50 px-6 py-3 rounded-xl transition text-center">
                        Weitere Artikel wählen
                    </a>
                </div>
            @else
                <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-center">
                    <p class="text-sm font-semibold text-amber-800 mb-1">Nur für Kundenkonten</p>
                    <p class="text-xs text-amber-700 mb-3">Mit deinem aktuellen Konto sind keine Bestellungen möglich.</p>
                    <a href="{{ route('logout') }}"
                        class="text-sm text-amber-700 underline"
                        onclick="document.getElementById('rental-cart-logout').submit(); return false;">
                        Anderes Konto anmelden
                    </a>
                    <form id="rental-cart-logout" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
                </div>
            @endif
        @else
            <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-center mb-3">
                <p class="text-sm font-semibold text-amber-800 mb-1">Zum Bestellen anmelden</p>
                <p class="text-xs text-amber-700">Bestellungen sind nur für registrierte Kunden möglich.</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('login') }}"
                    class="flex-1 border border-gray-300 text-gray-700 hover:bg-gray-50 px-6 py-3 rounded-xl transition text-center">
                    Anmelden &amp; bestellen
                </a>
                <a href="{{ route('register') }}"
                    class="flex-1 border border-gray-300 text-gray-700 hover:bg-gray-50 px-6 py-3 rounded-xl transition text-center">
                    Neu registrieren
                </a>
            </div>
            @if($from && $until)
                <div class="mt-3 text-center">
                    <a href="{{ route('rental.catalog') }}"
                        class="text-sm text-blue-600 underline hover:no-underline">
                        Direkt zum Katalog für {{ $from->format('d.m.') }}–{{ $until->format('d.m.Y') }} →
                    </a>
                </div>
            @endif
        @endauth

    @endif

</div>
@endsection
