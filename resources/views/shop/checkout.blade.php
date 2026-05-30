@extends('shop.layout')

@section('title', 'Kasse')

@section('content')
<div x-data="checkoutWizard()" class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Kasse</h1>

    {{-- Step indicators --}}
    <div class="flex items-center gap-2 mb-8 overflow-x-auto pb-2">
        @foreach(['Lieferart', 'Adresse', 'Termin', 'Zahlung', 'Zusammenfassung'] as $i => $label)
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
        <input type="hidden" name="has_rental_items" value="{{ $hasRentalItems ? '1' : '0' }}">
        @if($hasRentalItems && !$hasProducts && $rentalFrom)
            <input type="hidden" name="delivery_date" value="{{ $rentalFrom->format('Y-m-d') }}">
        @endif

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
                        <p class="font-medium text-gray-900">{{ $hasRentalItems ? 'Lieferung' : 'Heimlieferung' }}</p>
                        <p class="text-sm text-gray-500">{{ $hasRentalItems ? 'Wir liefern zu Ihnen nach Hause oder zum Veranstaltungsort.' : 'Wir liefern direkt zu dir nach Hause.' }}</p>
                    </div>
                </label>

                <label class="flex gap-4 p-4 rounded-xl border cursor-pointer transition-colors"
                       :class="deliveryType === 'pickup' ? 'border-amber-400 bg-amber-50' : 'border-gray-200 hover:border-amber-300'">
                    <input type="radio" name="delivery_type" value="pickup"
                           x-model="deliveryType" class="mt-0.5 accent-amber-500">
                    <div>
                        <p class="font-medium text-gray-900">Abholung im Lager/Markt</p>
                        <p class="text-sm text-gray-500">Sie holen Ihre Bestellung bei uns selbst ab.</p>
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

                {{-- Event location option --}}
                @if($eventLocations->isNotEmpty())
                <label class="flex gap-3 p-3 rounded-xl border cursor-pointer mb-2 transition-colors"
                       :class="selectedAddressId === 'event_location' ? 'border-blue-400 bg-blue-50' : 'border-gray-200 hover:border-blue-200'">
                    <input type="radio" name="delivery_address_id" value="event_location"
                           x-model="selectedAddressId" class="mt-0.5 accent-blue-500">
                    <div>
                        <span class="text-sm font-medium text-blue-700">📍 Veranstaltungsort auswählen</span>
                        <p class="text-xs text-gray-400 mt-0.5">Lieferung direkt an den Veranstaltungsort</p>
                    </div>
                </label>

                <div x-show="selectedAddressId === 'event_location'" x-cloak
                     class="mb-2 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                    <input type="hidden" name="event_location_delivery_id" x-model="selectedEventLocationId">
                    <label class="text-xs text-gray-500 block mb-1">Veranstaltungsort wählen</label>
                    <select x-model="selectedEventLocationId"
                            @change="applyEventLocation()"
                            class="w-full border border-blue-300 rounded-lg px-3 py-2 text-sm bg-white">
                        <option value="">-- Bitte wählen --</option>
                        @foreach($eventLocations as $loc)
                            <option value="{{ $loc->id }}"
                                    data-name="{{ $loc->name }}"
                                    data-street="{{ $loc->street ?? '' }}"
                                    data-zip="{{ $loc->zip ?? '' }}"
                                    data-city="{{ $loc->city ?? '' }}">
                                {{ $loc->name }}@if($loc->zip || $loc->city) — {{ trim(($loc->zip ?? '') . ' ' . ($loc->city ?? '')) }}@endif
                            </option>
                        @endforeach
                    </select>
                    <div x-show="selectedEventLocationId" x-cloak class="mt-2 text-xs text-blue-700">
                        <span x-text="eventLocStreet"></span><template x-if="eventLocStreet">, </template><span x-text="eventLocZip"></span> <span x-text="eventLocCity"></span>
                    </div>
                </div>
                @endif

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
                            <input type="text" name="new_address[street]" :required="selectedAddressId === 'new'" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Hausnr. *</label>
                            <input type="text" name="new_address[house_number]" :required="selectedAddressId === 'new'" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="text-xs text-gray-500">PLZ *</label>
                            <input type="text" name="new_address[zip]" x-model="newAddressZip" :required="selectedAddressId === 'new'" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs text-gray-500">Stadt *</label>
                            <input type="text" name="new_address[city]" x-model="newAddressCity" :required="selectedAddressId === 'new'" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Telefon (optional)</label>
                        <input type="text" name="new_address[phone]" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>

                {{-- Drop-off location --}}
                <div class="mt-4 pt-4 border-t border-gray-100" x-show="selectedAddressId !== 'event_location'">
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Abstellort (optional)</h3>
                    <select name="drop_off_location" x-model="dropOffLocation"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">-- Kein Abstellort --</option>
                        @foreach(\App\Models\Address::DROP_OFF_LABELS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <template x-if="dropOffLocation === 'sonstiges'">
                        <div class="mt-2">
                            <input type="text" name="drop_off_location_custom"
                                   x-model="dropOffLocationCustom"
                                   placeholder="Abstellort beschreiben..."
                                   class="w-full border rounded-lg px-3 py-2 text-sm"
                                   :class="stepErrors.dropOffLocationCustom ? 'border-red-400' : 'border-gray-300'">
                            <p x-show="stepErrors.dropOffLocationCustom"
                               x-text="stepErrors.dropOffLocationCustom"
                               class="text-red-600 text-xs mt-1"></p>
                        </div>
                    </template>

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
             Step 3: Liefertermin / Abholtermin
             ================================================================ --}}
        <div x-show="step === 3" x-cloak class="space-y-4">
            <div class="bg-white rounded-2xl border border-gray-100 p-6">

                {{-- ── Abholung ── --}}
                <template x-if="deliveryType === 'pickup'">
                    <div class="space-y-4">
                        <h2 class="font-bold text-gray-900">Abholtermin</h2>

                        <div>
                            <label class="text-sm font-medium text-gray-700 mb-1 block">Abholdatum</label>
                            <input type="date" name="pickup_date" x-model="pickupDate"
                                   min="{{ now()->addDay()->format('Y-m-d') }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>

                        <div x-show="availableSlots.length > 0">
                            <label class="text-sm font-medium text-gray-700 mb-1 block">Zeitfenster (1 Stunde)</label>
                            <select name="pickup_time_from" x-model="pickupTimeFrom"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <template x-for="slot in availableSlots" :key="slot.from">
                                    <option :value="slot.from" x-text="slot.from + ' – ' + slot.to + ' Uhr'"></option>
                                </template>
                            </select>
                            <input type="hidden" name="pickup_time_to"
                                   :value="availableSlots.find(s => s.from === pickupTimeFrom)?.to ?? ''">
                        </div>

                        <div x-show="pickupDate && isHoliday(pickupDate)"
                             class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                            Dieser Tag ist ein gesetzlicher Feiertag — bitte wähle ein anderes Datum.
                        </div>
                        <div x-show="pickupDate && !isHoliday(pickupDate) && availableSlots.length === 0"
                             class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                            Für diesen Tag sind keine Öffnungszeiten hinterlegt.
                        </div>
                    </div>
                </template>

                {{-- ── Heimlieferung ── --}}
                <template x-if="deliveryType === 'home_delivery'">
                <div>
                <h2 class="font-bold text-gray-900 mb-4">Wunsch-Liefertermin</h2>

                <div class="mb-4" x-show="visibleTours.length > 0">
                    <label class="text-sm font-medium text-gray-700 mb-1 block">Tour</label>
                    <select name="tour_id" x-model="selectedTourId"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <template x-for="tour in visibleTours" :key="tour.id">
                            <option :value="String(tour.id)"
                                    x-text="tour.name + ' (' + tour.day_de + ', ' + tour.freq_de + ')' + (tour.min_order > 0 ? ' — Mindestbestellwert ' + (tour.min_order / 1000000).toFixed(2).replace('.', ',') + ' €' : '')">
                            </option>
                        </template>
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
                <div x-show="selectedAddressId === 'new' && newAddressZip.length >= 4 && visibleTours.length === 0"
                     class="mb-4 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    Für diese PLZ wurde keine Heimdienst-Tour gefunden.
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 mb-1 block">Nächster Liefertermin</label>

                    {{-- Automatisch aus Ninox ermitteltes Datum --}}
                    <template x-if="deliveryDate">
                        <p class="text-sm font-semibold text-gray-900 py-2"
                           x-text="new Date(deliveryDate + 'T00:00:00').toLocaleDateString('de-DE', {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'})">
                        </p>
                    </template>

                    {{-- Fallback: kein Ninox-Termin vorhanden --}}
                    <template x-if="!deliveryDate">
                        <input type="date" x-model="deliveryDate"
                               min="{{ now()->addDay()->format('Y-m-d') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </template>

                    <input type="hidden" name="delivery_date" :value="deliveryDate">
                </div>
                </div>
                </template>
            </div>

            <div class="flex justify-between">
                <button type="button" @click="prevStep()"
                        class="border border-gray-300 text-gray-600 font-medium px-6 py-2.5 rounded-xl hover:bg-gray-50 transition-colors">
                    Zurueck
                </button>
                <button type="button" @click="nextStep()"
                        :disabled="deliveryType === 'home_delivery' ? !deliveryDate : (!pickupDate || (availableSlots.length > 0 && !pickupTimeFrom))"
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

                {{-- SEPA-Lastschrift: Mandat prüfen / eingeben --}}
                <div x-show="paymentMethod === 'sepa'" x-cloak
                     class="mt-4 rounded-xl border border-blue-200 bg-blue-50 p-4"
                     x-data="{
                         useExisting: {{ $sepaMandateInfo ? 'true' : 'false' }},
                         iban: '',
                         ibanError: '',
                         bankName: '',
                         bankLookupTimer: null,
                         validateIban() {
                             const v = this.iban.replace(/\s+/g, '').toUpperCase();
                             if (!v) { this.ibanError = ''; return; }
                             if (!/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/.test(v)) {
                                 this.ibanError = 'Ungültiges IBAN-Format.'; return;
                             }
                             const r = (v.slice(4) + v.slice(0, 4)).split('').map(c =>
                                 /[A-Z]/.test(c) ? String(c.charCodeAt(0) - 55) : c
                             ).join('');
                             let rem = 0;
                             for (let i = 0; i < r.length; i++) {
                                 rem = (rem * 10 + parseInt(r[i])) % 97;
                             }
                             this.ibanError = rem === 1 ? '' : 'Die IBAN ist ungültig (Prüfziffer stimmt nicht).';
                         },
                         lookupBank(raw) {
                             clearTimeout(this.bankLookupTimer);
                             const v = raw.replace(/\s+/g, '').toUpperCase();
                             if (v.startsWith('DE') && v.length >= 12) {
                                 this.bankLookupTimer = setTimeout(() => {
                                     fetch('/shop/bank-lookup?iban=' + encodeURIComponent(v))
                                         .then(r => r.json())
                                         .then(d => { this.bankName = d.bank_name || ''; })
                                         .catch(() => {});
                                 }, 300);
                             } else {
                                 this.bankName = '';
                             }
                         }
                     }">

                    @if($sepaMandateInfo)
                        <p class="text-sm font-semibold text-blue-800 mb-2">Vorhandenes SEPA-Mandat</p>
                        <div class="text-sm text-blue-700 space-y-0.5 mb-3">
                            <p><span class="text-blue-500">IBAN:</span>
                                {{ app(\App\Services\Payments\IbanValidator::class)->mask($sepaMandateInfo->iban) }}</p>
                            @if($sepaMandateInfo->account_holder)
                                <p><span class="text-blue-500">Kontoinhaber:</span> {{ $sepaMandateInfo->account_holder }}</p>
                            @endif
                            @if($sepaMandateInfo->mandate_date)
                                <p><span class="text-blue-500">Mandat vom:</span>
                                    {{ \Carbon\Carbon::parse($sepaMandateInfo->mandate_date)->format('d.m.Y') }}</p>
                            @endif
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                            <input type="checkbox" @change="useExisting = !$event.target.checked"
                                   class="accent-amber-500">
                            Anderes Konto verwenden
                        </label>
                    @endif

                    {{-- Neue IBAN eingeben --}}
                    <div x-show="!useExisting" class="mt-3 space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">IBAN</label>
                            <input type="text" name="sepa_iban" x-model="iban"
                                   @blur="validateIban()"
                                   @input="lookupBank($event.target.value)"
                                   placeholder="z.B. DE89 3704 0044 0532 0130 00"
                                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
                                   :class="ibanError ? 'border-red-400' : 'border-gray-300'">
                            <p x-show="bankName" x-text="'✓ ' + bankName"
                               class="mt-1 text-xs text-green-600 font-medium"></p>
                            <p x-show="ibanError" x-text="ibanError" class="mt-1 text-xs text-red-600"></p>
                            @error('sepa_iban')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Kontoinhaber</label>
                            <input type="text" name="sepa_account_holder"
                                   placeholder="Vor- und Nachname / Firmenname"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                            @error('sepa_account_holder')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <p class="text-xs text-gray-500">
                            Durch Angabe der IBAN erteilst du ein SEPA-Lastschriftmandat.
                            Der Betrag wird nach Lieferung eingezogen.
                        </p>
                    </div>

                    <input type="hidden" name="sepa_use_existing" :value="useExisting ? '1' : '0'">
                </div>
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

            {{-- Event fields (shown when rental items are in cart) --}}
            @if($hasRentalItems)
            <div class="bg-white rounded-2xl border border-blue-200 p-6">
                <h2 class="font-bold text-gray-900 mb-1">Veranstaltungsdetails für Leihartikel</h2>
                <p class="text-sm text-blue-600 mb-4">
                    Zeitraum:
                    <strong>{{ $rentalFrom->format('d.m.Y') }}</strong>
                    –
                    <strong>{{ $rentalUntil->format('d.m.Y') }}</strong>
                </p>

                {{-- Veranstaltungsort-Adresse nur zeigen, wenn explizit ein Event-Ort gewählt wurde --}}
                <div class="mb-4" x-show="selectedAddressId === 'event_location'" x-cloak>
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Veranstaltungsort</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label class="text-xs text-gray-500">Bezeichnung / Name</label>
                            <input type="text" name="event_location_name"
                                x-model="eventLocName"
                                :value="eventLocName || '{{ old('event_location_name') }}'"
                                placeholder="z.B. Vereinsheim Musterbach"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs text-gray-500">Straße & Hausnummer</label>
                            <input type="text" name="event_location_street"
                                x-model="eventLocStreet"
                                :value="eventLocStreet || '{{ old('event_location_street') }}'"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">PLZ</label>
                            <input type="text" name="event_location_zip"
                                x-model="eventLocZip"
                                :value="eventLocZip || '{{ old('event_location_zip') }}'"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Ort</label>
                            <input type="text" name="event_location_city"
                                x-model="eventLocCity"
                                :value="eventLocCity || '{{ old('event_location_city') }}'"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Ansprechpartner vor Ort</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-500">Name *</label>
                            <input type="text" name="event_contact_name"
                                value="{{ old('event_contact_name', $customer->company_name ?? '') }}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Telefon *</label>
                            <input type="tel" name="event_contact_phone"
                                value="{{ old('event_contact_phone', $customer->phone ?? '') }}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Lieferung & Rückgabe</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Lieferung der Leihartikel</label>
                            <label class="flex items-center gap-2 text-sm mb-1 cursor-pointer">
                                <input type="radio" name="event_delivery_mode" value="delivery"
                                    {{ old('event_delivery_mode', 'delivery') === 'delivery' ? 'checked' : '' }}
                                    class="accent-blue-600">
                                Lieferung zum Veranstaltungsort
                            </label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" name="event_delivery_mode" value="self_pickup"
                                    {{ old('event_delivery_mode') === 'self_pickup' ? 'checked' : '' }}
                                    class="accent-blue-600">
                                Selbstabholung bei uns
                            </label>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Rückgabe der Leihartikel</label>
                            <label class="flex items-center gap-2 text-sm mb-1 cursor-pointer">
                                <input type="radio" name="event_pickup_mode" value="pickup_by_us"
                                    {{ old('event_pickup_mode', 'pickup_by_us') === 'pickup_by_us' ? 'checked' : '' }}
                                    class="accent-blue-600">
                                Abholung bei der Veranstaltung
                            </label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" name="event_pickup_mode" value="self_return"
                                    {{ old('event_pickup_mode') === 'self_return' ? 'checked' : '' }}
                                    class="accent-blue-600">
                                Rückgabe bei uns
                            </label>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-500">Zufahrtshinweise</label>
                        <textarea name="event_access_notes" rows="2"
                            placeholder="z.B. Zufahrt über Hintereingang ..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('event_access_notes') }}</textarea>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Aufbauhinweise</label>
                        <textarea name="event_setup_notes" rows="2"
                            placeholder="Besonderheiten beim Aufbau ..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('event_setup_notes') }}</textarea>
                    </div>
                </div>
            </div>
            @endif

            <div class="bg-white rounded-2xl border border-gray-100 p-6">
                <h2 class="font-bold text-gray-900 mb-4">Bestelluebersicht</h2>

                {{-- Delivery info summary — BUG-3 fix: address now shown --}}
                <div class="grid md:grid-cols-2 gap-4 mb-6 text-sm">
                    <div>
                        <p class="text-gray-500 font-medium mb-1">Lieferart</p>
                        <p x-show="deliveryType === 'home_delivery'" class="text-gray-900">{{ $hasRentalItems ? 'Lieferung' : 'Heimlieferung' }}</p>
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
                    <div x-show="deliveryType === 'home_delivery'">
                        <p class="text-gray-500 font-medium mb-1">Liefertermin</p>
                        <p class="text-gray-900" x-text="deliveryDate || '-'"></p>
                    </div>
                    <div x-show="deliveryType === 'pickup'">
                        <p class="text-gray-500 font-medium mb-1">Abholtermin</p>
                        <p class="text-gray-900"
                           x-text="pickupDate ? (new Date(pickupDate + 'T00:00:00').toLocaleDateString('de-DE', {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'}) + (pickupTimeFrom ? ', ' + pickupTimeFrom + ' – ' + (availableSlots.find(s => s.from === pickupTimeFrom)?.to ?? '') + ' Uhr' : '')) : '-'">
                        </p>
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

                {{-- Rental items --}}
                @if($hasRentalItems)
                <div class="border-t border-gray-100 pt-4 mt-2">
                    <h3 class="text-sm font-medium text-blue-700 mb-3">Leihartikel</h3>
                    <div class="space-y-2">
                        @foreach($rentalSummary as $row)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 flex-1 mr-2">
                                    {{ $row['qty'] }}x {{ $row['item']->name }}
                                    @if($row['packaging_unit'])
                                        <span class="text-xs text-gray-400">({{ $row['packaging_unit']->label }})</span>
                                    @endif
                                </span>
                                @if($row['price_found'] && $row['total_price_net_milli'])
                                    <span class="shrink-0 font-medium">{{ milli_to_eur($row['total_price_net_milli']) }}</span>
                                @else
                                    <span class="shrink-0 text-gray-400 text-xs">auf Anfrage</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Totals --}}
                <div class="border-t border-gray-200 mt-4 pt-4 space-y-1.5 text-sm">

                    @if(!empty($cartData['items']))
                    {{-- Getränke --}}
                    <div class="flex justify-between text-gray-500">
                        <span>Getränke (netto)</span>
                        <span>{{ milli_to_eur($cartData['subtotal_net_milli']) }}</span>
                    </div>
                    @foreach($cartData['tax_breakdown'] as $tax)
                        <div class="flex justify-between text-gray-400 text-xs pl-3">
                            <span>{{ number_format($tax['rate'] * 100, 0) }}% MwSt.</span>
                            <span>{{ milli_to_eur($tax['tax_milli']) }}</span>
                        </div>
                    @endforeach
                    <div class="flex justify-between text-gray-700 font-medium">
                        <span>Getränke (brutto)</span>
                        <span>{{ milli_to_eur($cartData['subtotal_gross_milli']) }}</span>
                    </div>
                    @if($cartData['pfand_total_milli'] > 0)
                        <div class="flex justify-between text-amber-600">
                            <span>Pfand</span>
                            <span>{{ milli_to_eur($cartData['pfand_total_milli']) }}</span>
                        </div>
                    @endif
                    @endif

                    @if($hasRentalItems && $rentalTotal > 0)
                        <div class="flex justify-between text-blue-700{{ !empty($cartData['items']) ? ' pt-1' : '' }}">
                            <span>Festbedarf (netto)</span>
                            <span>{{ milli_to_eur($rentalTotal) }}</span>
                        </div>
                    @endif

                    @if($leergutTotal > 0)
                        <div class="flex justify-between text-amber-700">
                            <span>Leergut-Gutschrift</span>
                            <span>−{{ milli_to_eur($leergutTotal) }}</span>
                        </div>
                    @endif

                    <div class="flex justify-between font-bold text-gray-900 text-base pt-2 mt-1 border-t border-gray-200">
                        <span>Gesamtbetrag</span>
                        <span>{{ milli_to_eur(max(0, $cartData['total_milli'] + $rentalTotal - $leergutTotal)) }}</span>
                    </div>
                </div>
            </div>

            {{-- Jugendschutz warning --}}
            @if($minAge > 0)
            <div class="flex items-start gap-2 bg-red-50 border border-red-300 rounded-xl px-4 py-3 text-sm text-red-800">
                <svg class="w-4 h-4 mt-0.5 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <span>{{ \App\Services\Catalog\JugendschutzService::checkoutWarning($minAge) }}</span>
            </div>
            @endif

            {{-- BUG-11: minimum order value warning in summary --}}
            <div x-show="minOrderWarning" x-cloak
                 class="flex items-start gap-2 bg-amber-50 border border-amber-300 rounded-xl px-4 py-3 text-sm text-amber-800">
                <svg class="w-4 h-4 mt-0.5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <span x-text="minOrderWarning"></span>
            </div>

            {{-- AGB + Widerruf Pflicht-Checkbox --}}
            <div class="space-y-3 border border-gray-200 rounded-xl p-4 bg-gray-50 text-sm">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="agb_accepted" value="1" required
                           x-model="agbAccepted"
                           class="mt-0.5 accent-amber-500 w-4 h-4 shrink-0">
                    <span class="text-gray-700">
                        Ich habe die
                        <a href="{{ route('page.show', 'agb') }}" target="_blank" class="underline hover:text-amber-600">AGB</a>
                        und die
                        <a href="{{ route('page.show', 'widerruf') }}" target="_blank" class="underline hover:text-amber-600">Widerrufsbelehrung</a>
                        gelesen und bin damit einverstanden. Ich nehme zur Kenntnis, dass ich ein
                        14-tägiges Widerrufsrecht habe, das bei Lebensmitteln und Getränken
                        gemäß § 312g Abs. 2 Nr. 2 BGB ausgeschlossen sein kann.
                        <span class="text-red-500">*</span>
                    </span>
                </label>

                @if($minAge > 0)
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="age_confirmed" value="1" required
                           x-model="ageConfirmed"
                           class="mt-0.5 accent-amber-500 w-4 h-4 shrink-0">
                    <span class="text-gray-700">
                        Ich bestätige, dass ich mindestens <strong>{{ $minAge }} Jahre</strong> alt bin.
                        Der Verkauf von Alkohol an Personen unter {{ $minAge }} Jahren ist gesetzlich verboten.
                        <span class="text-red-500">*</span>
                    </span>
                </label>
                @endif

                <p class="text-xs text-gray-400">
                    <span class="text-red-500">*</span> Pflichtfeld — Bestellung ohne Zustimmung nicht möglich.
                </p>
            </div>

            {{-- Submit --}}
            <div class="flex justify-between">
                <button type="button" @click="prevStep()"
                        class="border border-gray-300 text-gray-600 font-medium px-6 py-2.5 rounded-xl hover:bg-gray-50 transition-colors">
                    Zurück
                </button>
                <button type="submit" :disabled="submitting || !!minOrderWarning || !agbAccepted {{ $minAge > 0 ? '|| !ageConfirmed' : '' }}"
                        class="bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 text-white font-bold px-8 py-3 rounded-xl transition-colors">
                    <span x-show="!submitting">Jetzt verbindlich bestellen</span>
                    <span x-show="submitting" x-cloak>Bestellung wird verarbeitet...</span>
                </button>
            </div>
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

@php
    $eventLocationsForJs = $eventLocations->map(function($l) {
        return ['id' => $l->id, 'name' => $l->name, 'street' => $l->street ?? '', 'zip' => $l->zip ?? '', 'city' => $l->city ?? ''];
    })->values()->toArray();
@endphp
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

    const eventLocations = @json($eventLocationsForJs);

    // Next scheduled tour dates from Ninox (keyed by regular_delivery_tour id)
    // All active tours with delivery areas and next dates — used for
    // client-side tour lookup when the customer enters a new address.
    const allTours = @json($allToursForJs);

    // IDs of tours pre-resolved from the customer's default address.
    const serverTourIds = @json($tours->pluck('id')->values());

    // Pickup locations with opening hours (keyed by day_of_week 0–6)
    const pickupLocations = @json($pickupLocationsForJs);

    // Gesetzliche Feiertage als Set für schnelle Lookup (YYYY-MM-DD)
    const holidayDates = new Set(@json($holidayDates));

    // BUG-11: cart gross total passed from server (milli-cents)
    const cartTotalMilli = {{ $cartData['total_milli'] ?? 0 }};
    const hasProducts    = {{ $hasProducts ? 'true' : 'false' }};

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
        step: {{ $errors->any() ? 5 : 1 }},
        submitting: false,
        agbAccepted: false,
        ageConfirmed: false,

        // Step 1
        deliveryType: 'home_delivery',

        // Step 2
        selectedAddressId: '{{ $defaultAddress?->id ?? "new" }}',
        selectedWarehouseId: '',
        dropOffLocation: '{{ $defaultAddress?->drop_off_location ?? "" }}',
        dropOffLocationCustom: '{{ old('drop_off_location_custom', $defaultAddress?->drop_off_location_custom ?? '') }}',
        stepErrors: {},
        selectedEventLocationId: '',
        eventLocName: '',
        eventLocStreet: '',
        eventLocZip: '',
        eventLocCity: '',

        // Step 3
        deliveryDate: '{{ $hasRentalItems && $rentalFrom ? $rentalFrom->format("Y-m-d") : "" }}',
        selectedTourId: '{{ $customerTourId ?? ($tours->first()?->id ?? "") }}',
        newAddressZip: '',
        newAddressCity: '',
        dynamicTours: [],
        // Pickup
        pickupDate: '',
        pickupTimeFrom: '',
        availableSlots: [],

        // Step 4
        paymentMethod: '',

        applyEventLocation() {
            const loc = eventLocations.find(l => l.id == this.selectedEventLocationId);
            if (!loc) return;
            this.eventLocName   = loc.name;
            this.eventLocStreet = loc.street;
            this.eventLocZip    = loc.zip;
            this.eventLocCity   = loc.city;
        },

        // BUG-3 fix: computed display strings for summary
        get selectedAddressDisplay() {
            if (this.selectedAddressId === 'new') return 'Neue Adresse (wird erstellt)';
            if (this.selectedAddressId === 'event_location') {
                const loc = eventLocations.find(l => l.id == this.selectedEventLocationId);
                return loc ? (loc.name + ', ' + loc.zip + ' ' + loc.city).trim() : 'Veranstaltungsort';
            }
            return savedAddresses[this.selectedAddressId] || '—';
        },

        get selectedWarehouseDisplay() {
            return savedWarehouses[this.selectedWarehouseId] || '—';
        },

        get canProceedFromStep2() {
            if (this.deliveryType === 'home_delivery') {
                if (this.selectedAddressId === 'event_location') {
                    return this.selectedEventLocationId !== '';
                }
                return this.selectedAddressId !== '';
            }
            return this.selectedWarehouseId !== '';
        },

        get paymentMethodLabel() {
            return paymentLabels[this.paymentMethod] || this.paymentMethod;
        },

        get visibleTours() {
            if (this.selectedAddressId === 'new') return this.dynamicTours;
            return allTours.filter(t => serverTourIds.includes(t.id));
        },

        nextDateForTourId(id) {
            const t = allTours.find(t => String(t.id) === String(id));
            return t?.next_date || '';
        },

        refreshDynamicTours() {
            if (this.selectedAddressId !== 'new') return;
            const zip  = this.newAddressZip.trim();
            const city = this.newAddressCity.trim().toLowerCase();
            if (zip.length < 4) {
                this.dynamicTours = [];
                return;
            }
            this.dynamicTours = allTours.filter(t =>
                t.areas.some(a =>
                    a.postal_code === zip &&
                    (!city || a.city_name.includes(city) || city.includes(a.city_name))
                )
            );
            if (this.dynamicTours.length > 0 && !this.dynamicTours.find(t => String(t.id) === String(this.selectedTourId))) {
                this.selectedTourId = String(this.dynamicTours[0].id);
                this.deliveryDate   = this.dynamicTours[0].next_date || '';
            }
        },

        isHoliday(dateStr) {
            return holidayDates.has(dateStr);
        },

        generateSlots(from, to) {
            const slots = [];
            let cur = from;
            while (cur < to) {
                const [h, m] = cur.split(':').map(Number);
                const next = String(h + 1).padStart(2, '0') + ':' + String(m).padStart(2, '0');
                if (next > to) break;
                slots.push({ from: cur, to: next });
                cur = next;
            }
            return slots;
        },

        // Called when the user manually changes the date — regenerates slots, keeps first available.
        refreshPickupSlots() {
            const loc = pickupLocations.find(l => String(l.id) === String(this.selectedWarehouseId));
            if (!loc || !this.pickupDate) { this.availableSlots = []; return; }
            if (this.isHoliday(this.pickupDate)) { this.availableSlots = []; return; }
            const dow   = new Date(this.pickupDate + 'T00:00:00').getDay();
            const oh    = loc.opening_hours[dow];
            if (!oh) { this.availableSlots = []; return; }
            const slots = this.generateSlots(oh.from, oh.to);
            this.availableSlots = slots;
            if (slots.length > 0 && !slots.find(s => s.from === this.pickupTimeFrom)) {
                this.pickupTimeFrom = slots[0].from;
            }
        },

        // Finds the next available pickup date + slot that is at least 30 min from now.
        initPickupDefaults() {
            if (this.deliveryType !== 'pickup' || !this.selectedWarehouseId) return;
            const loc = pickupLocations.find(l => String(l.id) === String(this.selectedWarehouseId));
            if (!loc) return;
            const earliest = new Date(Date.now() + 30 * 60 * 1000);
            for (let d = 0; d < 14; d++) {
                const date = new Date();
                date.setDate(date.getDate() + d);
                const dow     = date.getDay();
                const dateStr = date.toISOString().slice(0, 10);
                if (this.isHoliday(dateStr)) continue;
                const oh      = loc.opening_hours[dow];
                if (!oh) continue;
                const slots   = this.generateSlots(oh.from, oh.to);
                for (const slot of slots) {
                    if (new Date(dateStr + 'T' + slot.from + ':00') >= earliest) {
                        this.pickupDate     = dateStr;
                        this.availableSlots = slots;
                        this.pickupTimeFrom = slot.from;
                        return;
                    }
                }
            }
        },

        // BUG-11: returns formatted minimum order value string when cart is below threshold, else null
        get minOrderWarning() {
            if (this.deliveryType !== 'home_delivery') return null;
            if (!hasProducts) return null;
            const tour = allTours.find(t => String(t.id) === String(this.selectedTourId));
            const min = tour?.min_order || 0;
            if (min <= 0 || cartTotalMilli >= min) return null;
            const minEur = (min / 1_000_000).toFixed(2).replace('.', ',');
            const curEur = (cartTotalMilli / 1_000_000).toFixed(2).replace('.', ',');
            return `Mindestbestellwert ${minEur}\u00a0\u20ac nicht erreicht (aktuell ${curEur}\u00a0\u20ac).`;
        },

        init() {
            // Auto-set delivery date from next tour date on load.
            // Does not overwrite a rental date that was pre-filled server-side.
            if (!this.deliveryDate && this.selectedTourId) {
                this.deliveryDate = this.nextDateForTourId(this.selectedTourId);
            }

            // Update delivery date when tour selection changes.
            this.$watch('selectedTourId', (id) => {
                if (this.deliveryType === 'home_delivery') {
                    this.deliveryDate = this.nextDateForTourId(id);
                }
            });

            // Re-resolve tours when a new address PLZ or city is typed.
            this.$watch('newAddressZip',  () => this.refreshDynamicTours());
            this.$watch('newAddressCity', () => this.refreshDynamicTours());

            // Reset dynamic tours when switching back to an existing address.
            this.$watch('selectedAddressId', (id) => {
                if (id !== 'new') this.dynamicTours = [];
            });

            // Refresh pickup slots when user manually changes the date.
            this.$watch('pickupDate', () => this.refreshPickupSlots());

            // Re-init pickup defaults when warehouse or delivery type changes.
            this.$watch('selectedWarehouseId', () => this.initPickupDefaults());
            this.$watch('deliveryType', (type) => {
                if (type === 'pickup') this.initPickupDefaults();
            });

            // If pickup is already selected on load (e.g. back-navigation), init immediately.
            if (this.deliveryType === 'pickup') this.initPickupDefaults();
        },

        nextStep() {
            this.stepErrors = {};
            if (this.step === 2) {
                if (this.dropOffLocation === 'sonstiges' && !this.dropOffLocationCustom.trim()) {
                    this.stepErrors.dropOffLocationCustom = 'Bitte beschreibe den Abstellort.';
                    return;
                }
            }
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
