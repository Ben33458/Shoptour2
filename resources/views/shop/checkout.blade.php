@extends('shop.layout')

@section('title', 'Kasse')

@section('content')
<div x-data="checkoutWizard()" class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Kasse</h1>

    {{-- Step indicators --}}
    <div class="flex items-center gap-2 mb-8 overflow-x-auto pb-2">
        @foreach(['Lieferart', 'Adresse', 'Liefertermin', 'Zahlung', 'Zusammenfassung'] as $i => $label)
            <button @click="goToStep({{ $i + 1 }})"
                    :class="step === {{ $i + 1 }}
                        ? 'bg-amber-500 text-white'
                        : (step > {{ $i + 1 }} ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-400')"
                    :disabled="step < {{ $i + 1 }}"
                    class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-colors whitespace-nowrap">
                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="step > {{ $i + 1 }} ? 'bg-amber-600 text-white' : 'bg-white/30'">
                    <template x-if="step > {{ $i + 1 }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                        </svg>
                    </template>
                    <template x-if="step <= {{ $i + 1 }}">
                        <span>{{ $i + 1 }}</span>
                    </template>
                </span>
                {{ $label }}
            </button>
            @if($i < 4)
                <div class="w-4 h-px bg-gray-300 shrink-0"></div>
            @endif
        @endforeach
    </div>

    <form method="POST" action="{{ route('checkout.store') }}" @submit="submitting = true">
        @csrf

        {{-- ================================================================
             Step 1: Lieferart
             ================================================================ --}}
        <div x-show="step === 1" x-cloak class="space-y-4">
            <div class="bg-white rounded-2xl border border-gray-100 p-6">
                <h2 class="font-bold text-gray-900 mb-4">Wie moechtest du deine Bestellung erhalten?</h2>

                <label class="flex gap-4 p-4 rounded-xl border cursor-pointer mb-3 transition-colors"
                       :class="deliveryType === 'home_delivery' ? 'border-amber-400 bg-amber-50' : 'border-gray-200 hover:border-amber-300'">
                    <input type="radio" name="delivery_type" value="home_delivery"
                           x-model="deliveryType" class="mt-0.5 accent-amber-500">
                    <div>
                        <p class="font-medium text-gray-900">Heimlieferung</p>
                        <p class="text-sm text-gray-500">Wir liefern direkt zu dir nach Hause.</p>
                    </div>
                </label>

                <label class="flex gap-4 p-4 rounded-xl border cursor-pointer transition-colors"
                       :class="deliveryType === 'pickup' ? 'border-amber-400 bg-amber-50' : 'border-gray-200 hover:border-amber-300'">
                    <input type="radio" name="delivery_type" value="pickup"
                           x-model="deliveryType" class="mt-0.5 accent-amber-500">
                    <div>
                        <p class="font-medium text-gray-900">Abholung im Lager/Markt</p>
                        <p class="text-sm text-gray-500">Du holst deine Bestellung selbst ab.</p>
                    </div>
                </label>
            </div>

            <div class="flex justify-end">
                <button type="button" @click="nextStep()" :disabled="!deliveryType"
                        class="bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 text-white font-medium px-6 py-2.5 rounded-xl transition-colors">
                    Weiter
                </button>
            </div>
        </div>

        {{-- ================================================================
             Step 2: Adresse / Abholort
             ================================================================ --}}
        <div x-show="step === 2" x-cloak class="space-y-4">

            {{-- 2a: Lieferadresse (Heimlieferung) --}}
            <div x-show="deliveryType === 'home_delivery'" class="bg-white rounded-2xl border border-gray-100 p-6">
                <h2 class="font-bold text-gray-900 mb-4">Lieferadresse</h2>

                @forelse($customer->deliveryAddresses as $addr)
                    <label class="flex gap-3 p-3 rounded-xl border cursor-pointer mb-2 transition-colors"
                           :class="selectedAddressId == '{{ $addr->id }}' ? 'border-amber-400 bg-amber-50' : 'border-gray-200 hover:border-amber-300'">
                        <input type="radio" name="delivery_address_id" value="{{ $addr->id }}"
                               x-model="selectedAddressId" class="mt-0.5 accent-amber-500">
                        <div>
                            <p class="font-medium text-sm text-gray-900">
                                {{ trim(($addr->first_name ?? '') . ' ' . ($addr->last_name ?? '')) ?: $addr->company }}
                                @if($addr->is_default) <span class="text-xs text-amber-600 ml-1">Standard</span> @endif
                            </p>
                            <p class="text-sm text-gray-500">{{ $addr->oneLiner() }}</p>
                            @if($addr->drop_off_location)
                                <p class="text-xs text-gray-400 mt-1">
                                    Abstellort: {{ \App\Models\Address::DROP_OFF_LABELS[$addr->drop_off_location] ?? $addr->drop_off_location }}
                                    @if($addr->leave_at_door) | Bei Abwesenheit abstellen @endif
                                </p>
                            @endif
                        </div>
                    </label>
                @empty
                @endforelse

                {{-- New address option --}}
                <label class="flex gap-3 p-3 rounded-xl border cursor-pointer mb-2 transition-colors"
                       :class="selectedAddressId === 'new' ? 'border-amber-400 bg-amber-50' : 'border-gray-200 hover:border-amber-300'">
                    <input type="radio" name="delivery_address_id" value="new"
                           x-model="selectedAddressId" class="mt-0.5 accent-amber-500">
                    <span class="text-sm font-medium text-amber-600">+ Neue Adresse eingeben</span>
                </label>

                {{-- Inline new address form --}}
                <div x-show="selectedAddressId === 'new'" x-cloak class="mt-4 p-4 bg-gray-50 rounded-xl space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-500">Vorname</label>
                            <input type="text" name="new_address[first_name]" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Nachname</label>
                            <input type="text" name="new_address[last_name]" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Firma (optional)</label>
                        <input type="text" name="new_address[company]" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="text-xs text-gray-500">Strasse *</label>
                            <input type="text" name="new_address[street]" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Hausnr. *</label>
                            <input type="text" name="new_address[house_number]" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="text-xs text-gray-500">PLZ *</label>
                            <input type="text" name="new_address[zip]" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs text-gray-500">Stadt *</label>
                            <input type="text" name="new_address[city]" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Telefon (optional)</label>
                        <input type="text" name="new_address[phone]" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>

                {{-- Drop-off location --}}
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Abstellort (optional)</h3>
                    <select name="drop_off_location" x-model="dropOffLocation"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">-- Kein Abstellort --</option>
                        @foreach(\App\Models\Address::DROP_OFF_LABELS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <div x-show="dropOffLocation === 'sonstiges'" x-cloak class="mt-2">
                        <input type="text" name="drop_off_location_custom" placeholder="Abstellort beschreiben..."
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>

                    <label class="flex items-center gap-2 mt-3 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" name="leave_at_door" value="1" class="accent-amber-500">
                        Ware darf bei Abwesenheit abgestellt werden
                    </label>
                </div>
            </div>

            {{-- 2b: Abholort (Abholung) --}}
            <div x-show="deliveryType === 'pickup'" class="bg-white rounded-2xl border border-gray-100 p-6">
                <h2 class="font-bold text-gray-900 mb-4">Abholort waehlen</h2>

                @forelse($pickupLocations as $wh)
                    <label class="flex gap-3 p-3 rounded-xl border cursor-pointer mb-2 transition-colors"
                           :class="selectedWarehouseId == '{{ $wh->id }}' ? 'border-amber-400 bg-amber-50' : 'border-gray-200 hover:border-amber-300'">
                        <input type="radio" name="pickup_warehouse_id" value="{{ $wh->id }}"
                               x-model="selectedWarehouseId" class="mt-0.5 accent-amber-500">
                        <div>
                            <p class="font-medium text-sm text-gray-900">{{ $wh->name }}</p>
                            @if($wh->location)
                                <p class="text-sm text-gray-500">{{ $wh->location }}</p>
                            @endif
                        </div>
                    </label>
                @empty
                    <p class="text-sm text-gray-400">Derzeit sind keine Abholstandorte verfuegbar.</p>
                @endforelse
            </div>

            <div class="flex justify-between">
                <button type="button" @click="prevStep()"
                        class="border border-gray-300 text-gray-600 font-medium px-6 py-2.5 rounded-xl hover:bg-gray-50 transition-colors">
                    Zurueck
                </button>
                <button type="button" @click="nextStep()"
                        :disabled="!canProceedFromStep2"
                        class="bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 text-white font-medium px-6 py-2.5 rounded-xl transition-colors">
                    Weiter
                </button>
            </div>
        </div>

        {{-- ================================================================
             Step 3: Liefertermin
             ================================================================ --}}
        <div x-show="step === 3" x-cloak class="space-y-4">
            <div class="bg-white rounded-2xl border border-gray-100 p-6">
                <h2 class="font-bold text-gray-900 mb-4">Wunsch-Liefertermin</h2>

                @if($tours->isNotEmpty())
                    <div class="mb-4">
                        <label class="text-sm font-medium text-gray-700 mb-1 block">Tour</label>
                        <select name="tour_id" x-model="selectedTourId"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            @foreach($tours as $tour)
                                <option value="{{ $tour->id }}"
                                    {{ $customerTourId == $tour->id ? 'selected' : '' }}>
                                    {{ $tour->name }} ({{ $tour->day_of_week }}, {{ $tour->frequency }})
                                    @if($tour->min_order_value_milli > 0)
                                        — Mindestbestellwert {{ milli_to_eur($tour->min_order_value_milli) }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        {{-- BUG-11: minimum order value warning --}}
                        <div x-show="minOrderWarning" x-cloak
                             class="mt-2 flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-sm text-amber-800">
                            <svg class="w-4 h-4 mt-0.5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                            <span x-text="minOrderWarning"></span>
                        </div>
                    </div>
                @endif

                <div>
                    <label class="text-sm font-medium text-gray-700 mb-1 block">Datum *</label>
                    <input type="date" name="delivery_date" x-model="deliveryDate"
                           min="{{ now()->addDay()->format('Y-m-d') }}"
                           required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <p class="text-xs text-gray-400 mt-1">
                        Bitte waehle ein Datum in der Zukunft.
                    </p>
                </div>
            </div>

            <div class="flex justify-between">
                <button type="button" @click="prevStep()"
                        class="border border-gray-300 text-gray-600 font-medium px-6 py-2.5 rounded-xl hover:bg-gray-50 transition-colors">
                    Zurueck
                </button>
                <button type="button" @click="nextStep()" :disabled="!deliveryDate"
                        class="bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 text-white font-medium px-6 py-2.5 rounded-xl transition-colors">
                    Weiter
                </button>
            </div>
        </div>

        {{-- ================================================================
             Step 4: Zahlungsmethode
             ================================================================ --}}
        <div x-show="step === 4" x-cloak class="space-y-4">
            <div class="bg-white rounded-2xl border border-gray-100 p-6">
                <h2 class="font-bold text-gray-900 mb-4">Zahlungsmethode</h2>

                @php
                    $paymentLabels = [
                        'stripe'  => ['label' => 'Kreditkarte (Stripe)', 'desc' => 'Sichere Zahlung per Kreditkarte'],
                        'paypal'  => ['label' => 'PayPal', 'desc' => 'Zahlung ueber dein PayPal-Konto'],
                        'sepa'    => ['label' => 'SEPA-Lastschrift', 'desc' => 'Bequem per Bankeinzug'],
                        'invoice' => ['label' => 'Rechnung', 'desc' => 'Zahlung innerhalb von 14 Tagen'],
                        'cash'    => ['label' => 'Barzahlung', 'desc' => 'Bei Lieferung oder Abholung bar bezahlen'],
                        'ec'      => ['label' => 'EC-Karte', 'desc' => 'Bei Lieferung oder Abholung mit EC-Karte'],
                    ];
                @endphp

                @foreach($allowedPaymentMethods as $method)
                    @php $info = $paymentLabels[$method] ?? ['label' => $method, 'desc' => '']; @endphp
                    <label class="flex gap-4 p-4 rounded-xl border cursor-pointer mb-2 transition-colors"
                           :class="paymentMethod === '{{ $method }}' ? 'border-amber-400 bg-amber-50' : 'border-gray-200 hover:border-amber-300'">
                        <input type="radio" name="payment_method" value="{{ $method }}"
                               x-model="paymentMethod" class="mt-0.5 accent-amber-500">
                        <div>
                            <p class="font-medium text-sm text-gray-900">{{ $info['label'] }}</p>
                            <p class="text-xs text-gray-500">{{ $info['desc'] }}</p>
                        </div>
                    </label>
                @endforeach
            </div>

            <div class="flex justify-between">
                <button type="button" @click="prevStep()"
                        class="border border-gray-300 text-gray-600 font-medium px-6 py-2.5 rounded-xl hover:bg-gray-50 transition-colors">
                    Zurueck
                </button>
                <button type="button" @click="nextStep()" :disabled="!paymentMethod"
                        class="bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 text-white font-medium px-6 py-2.5 rounded-xl transition-colors">
                    Weiter
                </button>
            </div>
        </div>

        {{-- ================================================================
             Step 5: Zusammenfassung
             ================================================================ --}}
        <div x-show="step === 5" x-cloak class="space-y-4">
            <div class="bg-white rounded-2xl border border-gray-100 p-6">
                <h2 class="font-bold text-gray-900 mb-4">Bestelluebersicht</h2>

                {{-- Delivery info summary — BUG-3 fix: address now shown --}}
                <div class="grid md:grid-cols-2 gap-4 mb-6 text-sm">
                    <div>
                        <p class="text-gray-500 font-medium mb-1">Lieferart</p>
                        <p x-show="deliveryType === 'home_delivery'" class="text-gray-900">Heimlieferung</p>
                        <p x-show="deliveryType === 'pickup'" class="text-gray-900">Abholung</p>
                    </div>
                    <div x-show="deliveryType === 'home_delivery'">
                        <p class="text-gray-500 font-medium mb-1">Lieferadresse</p>
                        <p class="text-gray-900" x-text="selectedAddressDisplay || '—'"></p>
                    </div>
                    <div x-show="deliveryType === 'pickup'">
                        <p class="text-gray-500 font-medium mb-1">Abholort</p>
                        <p class="text-gray-900" x-text="selectedWarehouseDisplay || '—'"></p>
                    </div>
                    <div>
                        <p class="text-gray-500 font-medium mb-1">Liefertermin</p>
                        <p class="text-gray-900" x-text="deliveryDate || '-'"></p>
                    </div>
                    <div>
                        <p class="text-gray-500 font-medium mb-1">Zahlungsmethode</p>
                        <p class="text-gray-900" x-text="paymentMethodLabel"></p>
                    </div>
                </div>

                {{-- Customer notes --}}
                <div class="mb-6">
                    <label class="text-sm font-medium text-gray-700 mb-1 block">Anmerkungen zur Bestellung (optional)</label>
                    <textarea name="customer_notes" rows="2" maxlength="1000"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                              placeholder="z.B. Lieferhinweise, besondere Wuensche..."></textarea>
                </div>

                {{-- Order items --}}
                <div class="border-t border-gray-100 pt-4">
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Bestellte Artikel</h3>
                    <div class="space-y-2">
                        @foreach($cartData['items'] as $productId => $item)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 line-clamp-1 flex-1 mr-2">
                                    {{ $item['qty'] }}x {{ $item['product']->produktname }}
                                </span>
                                <span class="shrink-0 font-medium">{{ milli_to_eur($item['line_gross']) }}</span>
                            </div>
                            @if($item['line_pfand'] > 0)
                                <div class="flex justify-between text-xs text-amber-600 pl-4">
                                    <span>Pfand</span>
                                    <span>{{ milli_to_eur($item['line_pfand']) }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Totals --}}
                <div class="border-t border-gray-200 mt-4 pt-3 space-y-1 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span>Waren (brutto)</span>
                        <span>{{ milli_to_eur($cartData['subtotal_gross_milli']) }}</span>
                    </div>
                    @if($cartData['pfand_total_milli'] > 0)
                        <div class="flex justify-between text-amber-600">
                            <span>Pfand</span>
                            <span>{{ milli_to_eur($cartData['pfand_total_milli']) }}</span>
                        </div>
                    @endif
                    @foreach($cartData['tax_breakdown'] as $tax)
                        <div class="flex justify-between text-gray-400 text-xs">
                            <span>inkl. {{ number_format($tax['rate'], 1, ',', '') }}% MwSt.</span>
                            <span>{{ milli_to_eur($tax['tax_milli']) }}</span>
                        </div>
                    @endforeach
                    <div class="flex justify-between font-bold text-gray-900 text-base pt-1 border-t border-gray-100">
                        <span>Gesamt</span>
                        <span>{{ milli_to_eur($cartData['total_milli']) }}</span>
                    </div>
                </div>
            </div>

            {{-- BUG-11: minimum order value warning in summary --}}
            <div x-show="minOrderWarning" x-cloak
                 class="flex items-start gap-2 bg-amber-50 border border-amber-300 rounded-xl px-4 py-3 text-sm text-amber-800">
                <svg class="w-4 h-4 mt-0.5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <span x-text="minOrderWarning"></span>
            </div>

            {{-- Submit --}}
            <div class="flex justify-between">
                <button type="button" @click="prevStep()"
                        class="border border-gray-300 text-gray-600 font-medium px-6 py-2.5 rounded-xl hover:bg-gray-50 transition-colors">
                    Zurueck
                </button>
                <button type="submit" :disabled="submitting || !!minOrderWarning"
                        class="bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 text-white font-bold px-8 py-3 rounded-xl transition-colors">
                    <span x-show="!submitting">Jetzt verbindlich bestellen</span>
                    <span x-show="submitting" x-cloak>Bestellung wird verarbeitet...</span>
                </button>
            </div>
            <p class="text-xs text-gray-400 text-center">
                Mit deiner Bestellung akzeptierst du unsere
                <a href="{{ route('page.show', 'agb') }}" target="_blank" class="underline hover:text-gray-600">AGB</a>
                und
                <a href="{{ route('page.show', 'datenschutz') }}" target="_blank" class="underline hover:text-gray-600">Datenschutzerklaerung</a>.
            </p>
        </div>

        {{-- Validation errors --}}
        @if($errors->any())
            <div class="mt-4 bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
                <ul class="list-disc pl-4 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('info'))
            <div class="mt-4 bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-700">
                {{ session('info') }}
            </div>
        @endif
    </form>
</div>

@push('head')
<script>
function checkoutWizard() {
    const paymentLabels = {
        stripe:  'Kreditkarte (Stripe)',
        paypal:  'PayPal',
        sepa:    'SEPA-Lastschrift',
        invoice: 'Rechnung',
        cash:    'Barzahlung',
        ec:      'EC-Karte',
    };

    // BUG-11: tour minimum order values (milli-cents, 0 = no minimum)
    const tourMinValues = {
        @foreach($tours as $tour)
        '{{ $tour->id }}': {{ $tour->min_order_value_milli ?? 0 }},
        @endforeach
    };

    // BUG-11: cart gross total passed from server (milli-cents)
    const cartTotalMilli = {{ $cartData['total_milli'] ?? 0 }};

    // BUG-3 fix: address data for summary display (pre-populated from Blade)
    const savedAddresses = {
        @foreach($customer->deliveryAddresses as $addr)
        '{{ $addr->id }}': '{{ addslashes(($addr->oneLiner())) }}',
        @endforeach
    };

    const savedWarehouses = {
        @foreach($pickupLocations as $wh)
        '{{ $wh->id }}': '{{ addslashes($wh->name) }}',
        @endforeach
    };

    return {
        step: 1,
        submitting: false,

        // Step 1
        deliveryType: 'home_delivery',

        // Step 2
        selectedAddressId: '{{ $defaultAddress?->id ?? "new" }}',
        selectedWarehouseId: '',
        dropOffLocation: '{{ $defaultAddress?->drop_off_location ?? "" }}',

        // Step 3
        deliveryDate: '',
        selectedTourId: '{{ $customerTourId ?? ($tours->first()?->id ?? "") }}',

        // Step 4
        paymentMethod: '',

        // BUG-3 fix: computed display strings for summary
        get selectedAddressDisplay() {
            if (this.selectedAddressId === 'new') return 'Neue Adresse (wird erstellt)';
            return savedAddresses[this.selectedAddressId] || '—';
        },

        get selectedWarehouseDisplay() {
            return savedWarehouses[this.selectedWarehouseId] || '—';
        },

        get canProceedFromStep2() {
            if (this.deliveryType === 'home_delivery') {
                return this.selectedAddressId !== '';
            }
            return this.selectedWarehouseId !== '';
        },

        get paymentMethodLabel() {
            return paymentLabels[this.paymentMethod] || this.paymentMethod;
        },

        // BUG-11: returns formatted minimum order value string when cart is below threshold, else null
        get minOrderWarning() {
            if (this.deliveryType !== 'home_delivery') return null;
            const min = tourMinValues[this.selectedTourId] || 0;
            if (min <= 0 || cartTotalMilli >= min) return null;
            const minEur = (min / 1_000_000).toFixed(2).replace('.', ',');
            const curEur = (cartTotalMilli / 1_000_000).toFixed(2).replace('.', ',');
            return `Mindestbestellwert ${minEur}\u00a0\u20ac nicht erreicht (aktuell ${curEur}\u00a0\u20ac).`;
        },

        nextStep() {
            if (this.step < 5) this.step++;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        prevStep() {
            if (this.step > 1) this.step--;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        goToStep(s) {
            if (s <= this.step) {
                this.step = s;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },
    };
}
</script>
@endpush
@endsection
