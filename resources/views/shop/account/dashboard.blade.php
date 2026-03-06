@extends('shop.layout')

@section('title', 'Mein Konto')

@section('content')
<div class="max-w-4xl mx-auto">

    {{-- Header --}}
    <div class="flex items-center gap-4 mb-8">
        @if(Auth::user()->avatar_url)
            <img src="{{ Auth::user()->avatar_url }}" class="w-16 h-16 rounded-full" alt="">
        @else
            <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center text-2xl font-bold text-amber-600">
                {{ mb_substr(Auth::user()->name, 0, 1) }}
            </div>
        @endif
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ Auth::user()->name }}</h1>
            <p class="text-gray-400 text-sm">{{ Auth::user()->email }}</p>
            <p class="text-sm text-amber-600">{{ $customer->customerGroup?->name ?? 'Standard' }}</p>
        </div>
    </div>

    {{-- Quick nav --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-8">
        <a href="{{ route('account.orders') }}" class="bg-white border border-gray-100 rounded-2xl p-4 hover:border-amber-300 transition-colors text-center">
            <div class="text-2xl mb-1">📦</div>
            <p class="text-sm font-medium text-gray-700">Bestellungen</p>
        </a>
        <a href="{{ route('account.addresses') }}" class="bg-white border border-gray-100 rounded-2xl p-4 hover:border-amber-300 transition-colors text-center">
            <div class="text-2xl mb-1">📍</div>
            <p class="text-sm font-medium text-gray-700">Adressen</p>
        </a>
        <a href="{{ route('shop.index') }}" class="bg-white border border-gray-100 rounded-2xl p-4 hover:border-amber-300 transition-colors text-center">
            <div class="text-2xl mb-1">🛒</div>
            <p class="text-sm font-medium text-gray-700">Weiter shoppen</p>
        </a>
    </div>

    {{-- Recent orders --}}
    <div class="bg-white rounded-2xl border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold text-gray-900">Letzte Bestellungen</h2>
            <a href="{{ route('account.orders') }}" class="text-sm text-amber-600 hover:underline">Alle ansehen</a>
        </div>

        @forelse($customer->orders as $order)
            <div class="flex items-center justify-between py-3 border-b border-gray-50 last:border-0">
                <div>
                    <p class="text-sm font-medium text-gray-900">Bestellung #{{ $order->id }}</p>
                    <p class="text-xs text-gray-400">{{ $order->created_at->format('d.m.Y') }} · {{ $order->items->count() }} Artikel</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium">{{ milli_to_eur($order->total_gross_milli + $order->total_pfand_brutto_milli) }}</span>
                    <a href="{{ route('account.order', $order) }}" class="text-xs text-amber-600 hover:underline">Details</a>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-400 text-center py-6">Noch keine Bestellungen.</p>
        @endforelse
    </div>

</div>
@endsection
