@extends('shop.layout')

@section('title', 'Bestellung aufgegeben')

@section('content')
<div class="max-w-xl mx-auto text-center py-16">
    <div class="text-6xl mb-6">&#127881;</div>
    <h1 class="text-3xl font-bold text-gray-900 mb-3">Vielen Dank!</h1>
    <p class="text-gray-500 mb-2">
        Deine Bestellung <strong>{{ $order->order_number ?? '#' . $order->id }}</strong> ist eingegangen.
    </p>
    <p class="text-gray-400 text-sm mb-8">Wir bearbeiten sie schnellstmoeglich und melden uns bei dir.</p>

    @if(session('warning'))
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-sm text-yellow-700 mb-6 text-left">
            {{ session('warning') }}
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-100 p-6 text-left mb-6">
        {{-- Delivery info --}}
        <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
            <div>
                <p class="text-gray-500 font-medium">Lieferart</p>
                <p class="text-gray-900">
                    @if($order->delivery_type === 'home_delivery')
                        Heimlieferung
                    @else
                        Abholung
                    @endif
                </p>
            </div>
            @if($order->delivery_date)
                <div>
                    <p class="text-gray-500 font-medium">Liefertermin</p>
                    <p class="text-gray-900">{{ $order->delivery_date->format('d.m.Y') }}</p>
                </div>
            @endif
            @if($order->payment_method)
                <div>
                    <p class="text-gray-500 font-medium">Zahlungsmethode</p>
                    <p class="text-gray-900">
                        @php
                            $labels = [
                                'stripe' => 'Kreditkarte', 'paypal' => 'PayPal',
                                'sepa' => 'SEPA-Lastschrift', 'invoice' => 'Rechnung',
                                'cash' => 'Barzahlung', 'ec' => 'EC-Karte',
                            ];
                        @endphp
                        {{ $labels[$order->payment_method] ?? $order->payment_method }}
                    </p>
                </div>
            @endif
            @if($order->deliveryAddress)
                <div>
                    <p class="text-gray-500 font-medium">Lieferadresse</p>
                    <p class="text-gray-900">{{ $order->deliveryAddress->oneLiner() }}</p>
                </div>
            @endif
            @if($order->pickupLocation)
                <div>
                    <p class="text-gray-500 font-medium">Abholort</p>
                    <p class="text-gray-900">{{ $order->pickupLocation->name }}</p>
                </div>
            @endif
        </div>

        <h2 class="font-bold text-gray-900 mb-4 border-t border-gray-100 pt-4">Bestellte Artikel</h2>
        <div class="space-y-3">
            @foreach($order->items as $item)
                <div class="flex justify-between text-sm">
                    <div>
                        <span class="text-gray-800 font-medium">{{ $item->product_name_snapshot }}</span>
                        <span class="text-gray-400 ml-1">x {{ $item->qty }}</span>
                        @if($item->unit_deposit_milli > 0)
                            <p class="text-xs text-amber-600">
                                + {{ milli_to_eur($item->unit_deposit_milli * $item->qty) }} Pfand
                            </p>
                        @endif
                    </div>
                    <div class="text-right">
                        <span class="font-medium">{{ milli_to_eur($item->unit_price_gross_milli * $item->qty) }}</span>
                        <p class="text-xs text-gray-400">{{ milli_to_eur($item->unit_price_gross_milli) }} / Stk.</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="border-t border-gray-100 mt-4 pt-3 space-y-1 text-sm">
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
            <div class="flex justify-between font-bold text-gray-900 text-base pt-1 border-t border-gray-100">
                <span>Gesamt</span>
                <span>{{ milli_to_eur($order->total_gross_milli + $order->total_pfand_brutto_milli) }}</span>
            </div>
        </div>

        @if($order->customer_notes)
            <div class="border-t border-gray-100 mt-4 pt-3">
                <p class="text-gray-500 font-medium text-sm">Deine Anmerkungen</p>
                <p class="text-sm text-gray-700 mt-1">{{ $order->customer_notes }}</p>
            </div>
        @endif
    </div>

    <div class="flex gap-3 justify-center">
        <a href="{{ route('account.orders') }}"
           class="bg-amber-500 hover:bg-amber-600 text-white font-medium px-6 py-2.5 rounded-xl transition-colors">
            Meine Bestellungen
        </a>
        <a href="{{ route('shop.index') }}"
           class="border border-gray-300 text-gray-600 font-medium px-6 py-2.5 rounded-xl hover:bg-gray-50 transition-colors">
            Weiter einkaufen
        </a>
    </div>
</div>
@endsection
