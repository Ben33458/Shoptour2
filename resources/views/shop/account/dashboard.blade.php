@extends('shop.account.account-layout')

@section('title', 'Mein Konto')

@section('account-content')
<div>

    {{-- Header --}}
    @php
        $displayName = $customer->company_name
            ?: trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''))
            ?: Auth::user()->name;
    @endphp
    <div class="flex items-center gap-4 mb-8">
        @if(Auth::user()->avatar_url)
            <img src="{{ Auth::user()->avatar_url }}" class="w-16 h-16 rounded-full" alt="">
        @else
            <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center text-2xl font-bold text-amber-600">
                {{ mb_substr($displayName, 0, 1) }}
            </div>
        @endif
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-gray-900">{{ $displayName }}</h1>
            <p class="text-gray-400 text-sm">{{ $customer->email ?: Auth::user()->email }}</p>
            <p class="text-sm text-amber-600">{{ $customer->customerGroup?->name ?? 'Standard' }}</p>
            <p class="text-xs text-gray-400">Kundennr.: {{ $customer->customer_number }}</p>
        </div>
        <a href="{{ route('account.profile') }}"
           class="shrink-0 text-sm text-gray-400 hover:text-amber-600 border border-gray-200 hover:border-amber-300 rounded-xl px-3 py-1.5 transition-colors">
            Profil bearbeiten
        </a>
    </div>

    {{-- Quick nav --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
        <a href="{{ route('account.orders') }}" class="bg-white border border-gray-100 rounded-2xl p-4 hover:border-amber-300 transition-colors text-center">
            <div class="text-2xl mb-1">📦</div>
            <p class="text-sm font-medium text-gray-700">Bestellungen</p>
        </a>
        <a href="{{ route('account.addresses') }}" class="bg-white border border-gray-100 rounded-2xl p-4 hover:border-amber-300 transition-colors text-center">
            <div class="text-2xl mb-1">📍</div>
            <p class="text-sm font-medium text-gray-700">Adressen</p>
        </a>
        <a href="{{ route('account.profile') }}" class="bg-white border border-gray-100 rounded-2xl p-4 hover:border-amber-300 transition-colors text-center">
            <div class="text-2xl mb-1">👤</div>
            <p class="text-sm font-medium text-gray-700">Profil & Einstellungen</p>
        </a>
        <a href="{{ route('account.favorites') }}" class="bg-white border border-gray-100 rounded-2xl p-4 hover:border-amber-300 transition-colors text-center">
            <div class="text-2xl mb-1">📋</div>
            <p class="text-sm font-medium text-gray-700">Stammsortiment</p>
        </a>
        @if(!Auth::user()->isSubUser())
        <a href="{{ route('account.sub-users') }}" class="bg-white border border-gray-100 rounded-2xl p-4 hover:border-amber-300 transition-colors text-center">
            <div class="text-2xl mb-1">👥</div>
            <p class="text-sm font-medium text-gray-700">Unterbenutzer</p>
        </a>
        @endif
        @if($customer->lexoffice_contact_id)
        <a href="{{ route('account.invoices') }}" class="bg-white border border-gray-100 rounded-2xl p-4 hover:border-amber-300 transition-colors text-center">
            <div class="text-2xl mb-1">🧾</div>
            <p class="text-sm font-medium text-gray-700">Rechnungen</p>
        </a>
        @else
        <a href="{{ route('shop.index') }}" class="bg-white border border-gray-100 rounded-2xl p-4 hover:border-amber-300 transition-colors text-center">
            <div class="text-2xl mb-1">🛒</div>
            <p class="text-sm font-medium text-gray-700">Weiter shoppen</p>
        </a>
        @endif
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
