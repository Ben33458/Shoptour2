@extends('shop.account.account-layout')

@section('title', 'Bestellung #' . $order->id)

@section('account-content')
<div>

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('account.orders') }}" class="text-sm text-gray-400 hover:text-amber-600">← Bestellungen</a>
        <h1 class="text-2xl font-bold text-gray-900">Bestellung #{{ $order->id }}</h1>
        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium
            {{ $order->status === 'delivered' ? 'bg-green-100 text-green-700' :
               ($order->status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
            {{ ucfirst($order->status) }}
        </span>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-4">
        <p class="text-sm text-gray-400 mb-4">Aufgegeben am {{ $order->created_at->format('d.m.Y H:i') }}</p>

        {{-- Items --}}
        <div class="space-y-3">
            @foreach($order->items as $item)
                <div class="flex items-center gap-4">
                    @if($item->product?->mainImage)
                        <img src="{{ Storage::url($item->product->mainImage->path) }}" class="w-12 h-12 object-contain rounded-lg border border-gray-100" alt="">
                    @elseif($item->product)
                        <img src="{{ $item->product->placeholderImageUrl() }}" class="w-12 h-12 object-contain rounded-lg border border-gray-100" alt="">
                    @else
                        <div class="w-12 h-12 bg-gray-50 rounded-lg flex items-center justify-center text-xl opacity-30">🍺</div>
                    @endif
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">{{ $item->product?->produktname ?? '–' }}</p>
                        <p class="text-xs text-gray-400">{{ $item->qty }}× {{ milli_to_eur($item->unit_price_gross_milli) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold">{{ milli_to_eur($item->unit_price_gross_milli * $item->qty) }}</p>
                        @if($item->unit_deposit_milli > 0)
                            <p class="text-xs text-amber-600">+ {{ milli_to_eur($item->unit_deposit_milli * $item->qty) }} Pfand</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Totals --}}
        <div class="border-t border-gray-200 mt-4 pt-4 space-y-1 text-sm">
            <div class="flex justify-between text-gray-600">
                <span>Waren (brutto)</span>
                <span>{{ milli_to_eur($order->total_gross_milli) }}</span>
            </div>
            @if($order->total_pfand_brutto_milli > 0)
                <div class="flex justify-between text-amber-600">
                    <span>Pfand gesamt</span>
                    <span>{{ milli_to_eur($order->total_pfand_brutto_milli) }}</span>
                </div>
            @endif
            <div class="flex justify-between font-bold text-gray-900 text-base">
                <span>Gesamt</span>
                <span>{{ milli_to_eur($order->total_gross_milli + $order->total_pfand_brutto_milli) }}</span>
            </div>
        </div>
    </div>

</div>
@endsection
