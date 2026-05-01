@extends('shop.layout')

@section('title', 'Leihartikel — Katalog')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">

    {{-- Header / Date Context -------------------------------------------}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Leihartikel</h1>
            @if($from && $until)
                <p class="text-sm text-gray-500 mt-1">
                    Verfügbarkeit für
                    <span class="font-medium text-gray-700">{{ $from->format('d.m.Y') }}</span>
                    bis
                    <span class="font-medium text-gray-700">{{ $until->format('d.m.Y') }}</span>
                    @if($timeModel)
                        · {{ $timeModel->name }}
                    @endif
                </p>
            @else
                <p class="text-sm text-amber-600 mt-1">
                    ⚠ Kein Zeitraum gewählt — <a href="{{ route('rental.landing') }}" class="underline">Zeitraum wählen</a>
                </p>
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ route('rental.landing') }}"
                class="text-sm border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">
                Zeitraum ändern
            </a>
            <a href="{{ route('rental.cart') }}"
                class="relative text-sm bg-blue-600 text-white rounded-lg px-4 py-2 hover:bg-blue-700 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-1.5 6h11"/>
                </svg>
                Warenkorb
                @if(count($cartItems) > 0)
                    <span class="bg-white text-blue-600 text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                        {{ count($cartItems) }}
                    </span>
                @endif
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    {{-- Search ----------------------------------------------------------}}
    <form method="GET" action="{{ route('rental.catalog') }}" class="mb-6">
        <div class="relative">
            <input type="text" name="q" value="{{ $search }}"
                   placeholder="Leihartikel suchen…"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
            @if($search)
                <a href="{{ route('rental.catalog') }}"
                   class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</a>
            @else
                <button type="submit" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                </button>
            @endif
        </div>
    </form>

    @if($search && $categories->isEmpty())
        <div class="text-center py-12 text-gray-400">
            <p>Keine Leihartikel für <strong>„{{ $search }}"</strong> gefunden.</p>
            <a href="{{ route('rental.catalog') }}" class="mt-2 inline-block text-blue-600 text-sm hover:underline">Suche zurücksetzen</a>
        </div>
    @elseif($search)
        <p class="text-sm text-gray-500 mb-4">
            Suchergebnisse für <strong>„{{ $search }}"</strong>
            <a href="{{ route('rental.catalog') }}" class="text-blue-600 hover:underline ml-2">zurücksetzen</a>
        </p>
    @endif

    {{-- Categories & Items ----------------------------------------------}}
    @forelse($categories as $category)
        <div class="mb-10">
            <h2 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">
                {{ $category->name }}
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($category->items as $item)
                    @php
                        $avail        = $availMap[$item->id] ?? null;
                        $priceEntry   = $priceMap[$item->id] ?? null;
                        $priceMilli   = $priceEntry['milli'] ?? null;
                        $priceLabel   = $priceEntry['label'] ?? null;
                        $onRequest    = $priceEntry['on_request'] ?? false;
                        $inCart       = isset($cartItems[(string) $item->id]);
                        $canQuickAdd  = !$item->isPackagingBased() && ($avail !== 0 || $item->allow_overbooking || !$from || !$until);
                        $firstPu      = $item->isPackagingBased() ? $item->packagingUnits->where('active', true)->first() : null;
                    @endphp
                    <div class="bg-white rounded-xl border {{ $inCart ? 'border-blue-400' : 'border-gray-200' }} shadow-sm hover:shadow-md transition flex flex-col">

                        {{-- Card header (link to detail) --}}
                        <a href="{{ route('rental.item', $item) }}" class="p-4 flex-1 block">
                            <div class="flex justify-between items-start mb-3">
                                <h3 class="font-medium text-gray-800 text-sm leading-tight">{{ $item->name }}</h3>
                                @if($inCart)
                                    <span class="text-xs bg-blue-100 text-blue-700 rounded-full px-2 py-0.5 whitespace-nowrap ml-1">Im Warenkorb</span>
                                @endif
                            </div>

                            {{-- Availability badge --}}
                            @if($from && $until)
                                @if($avail === null)
                                    <span class="inline-block text-xs text-gray-400 mb-2">Verfügbarkeit unbekannt</span>
                                @elseif($avail === 0 && !$item->allow_overbooking)
                                    <span class="inline-block text-xs bg-red-100 text-red-700 rounded px-2 py-0.5 mb-2">Nicht verfügbar</span>
                                @elseif($avail <= 3 && !$item->allow_overbooking)
                                    <span class="inline-block text-xs bg-amber-100 text-amber-700 rounded px-2 py-0.5 mb-2">Nur noch {{ $avail }} verfügbar</span>
                                @else
                                    <span class="inline-block text-xs bg-green-100 text-green-700 rounded px-2 py-0.5 mb-2">Verfügbar</span>
                                @endif
                            @endif

                            {{-- Price --}}
                            @if($onRequest)
                                <p class="text-xs text-amber-600 font-medium">Preis auf Anfrage</p>
                            @elseif($priceMilli !== null)
                                <p class="text-sm font-semibold text-gray-900">
                                    ab {{ milli_to_eur($priceMilli) }}
                                    <span class="font-normal text-gray-500 text-xs">netto / {{ $priceLabel }}</span>
                                </p>
                            @else
                                <p class="text-xs text-gray-400">Preis auf Anfrage</p>
                            @endif
                        </a>

                        {{-- Quick-add footer --}}
                        <div class="border-t border-gray-100 px-4 py-3">
                            @if(($item->isPackagingBased() && $firstPu) || $canQuickAdd)
                                @php
                                    $puId      = $item->isPackagingBased() ? ($firstPu->id ?? null) : null;
                                    $puLabel   = $item->isPackagingBased() ? ($firstPu->label ?? $item->unit_label) : $item->unit_label;
                                    $maxQty    = $avail && !$item->allow_overbooking ? $avail : 999;
                                    $currentQty = $inCart ? ($cartItems[(string)$item->id]['qty'] ?? 1) : 1;
                                @endphp
                                <form action="{{ route('rental.cart.add') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="rental_item_id" value="{{ $item->id }}">
                                    @if($puId)
                                        <input type="hidden" name="packaging_unit_id" value="{{ $puId }}">
                                    @endif
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="text-xs text-gray-500">{{ $puLabel }}</span>
                                        <input type="number" name="qty" value="{{ $currentQty }}"
                                            min="1" max="{{ $maxQty }}"
                                            class="w-16 border border-gray-300 rounded px-2 py-1 text-sm text-center ml-auto"
                                            onclick="event.stopPropagation()">
                                    </div>
                                    <button type="submit"
                                        style="background-color:#2563eb;color:#fff"
                                        class="w-full text-sm font-semibold py-2 rounded-lg hover:bg-blue-700 transition">
                                        {{ $inCart ? 'Menge aktualisieren' : 'In den Warenkorb' }}
                                    </button>
                                </form>
                            @elseif($avail === 0 && !$item->allow_overbooking && $from && $until)
                                <span class="text-xs text-red-500">Für diesen Zeitraum nicht verfügbar</span>
                            @else
                                <a href="{{ route('rental.item', $item) }}" class="text-xs text-blue-600 hover:underline">
                                    Details ansehen →
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="text-center py-16 text-gray-400">
            <p class="text-lg">Keine Leihartikel gefunden.</p>
        </div>
    @endforelse

</div>
@endsection
