@extends('shop.account.account-layout')

@section('title', 'Meine Bestellungen')

@section('account-content')
<div>

    <h1 class="text-2xl font-bold text-gray-900 mb-6">Bestellungen</h1>

    @forelse($orders as $order)
        <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-3">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-bold text-gray-900">Bestellung #{{ $order->id }}</p>
                    <p class="text-sm text-gray-400">{{ $order->created_at->format('d.m.Y H:i') }}</p>
                </div>
                <div class="text-right">
                    <p class="font-bold text-gray-900">{{ milli_to_eur($order->total_gross_milli + $order->total_pfand_brutto_milli) }}</p>
                    <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-medium
                        {{ $order->status === 'delivered' ? 'bg-green-100 text-green-700' :
                           ($order->status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                        {{ ucfirst($order->status) }}
                    </span>
                </div>
            </div>
            <div class="mt-3 text-sm text-gray-500">
                {{ $order->items->count() }} Artikel
                @foreach($order->items->take(3) as $item)
                    · {{ $item->product?->produktname ?? '–' }}
                @endforeach
                @if($order->items->count() > 3) <span class="text-gray-400">u.a.</span> @endif
            </div>
            <div class="mt-3">
                <a href="{{ route('account.order', $order) }}" class="text-sm text-amber-600 hover:underline">Details ansehen →</a>
            </div>
        </div>
    @empty
        <div class="text-center py-20 text-gray-400">
            <p class="text-lg font-medium">Noch keine Bestellungen</p>
            <a href="{{ route('shop.index') }}" class="mt-3 inline-block bg-amber-500 text-white px-6 py-2 rounded-xl hover:bg-amber-600 text-sm font-medium">Jetzt einkaufen</a>
        </div>
    @endforelse

    <div class="mt-4">{{ $orders->links() }}</div>
</div>
@endsection
