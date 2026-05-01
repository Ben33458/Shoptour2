@extends('shop.layout')

@section('title', 'Leihauftrag abschließen')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">

    <h1 class="text-2xl font-bold text-gray-900 mb-6">Leihauftrag abschließen</h1>

    @if($errors->any())
        <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 space-y-1">
            @foreach($errors->all() as $error)
                <p>• {{ $error }}</p>
            @endforeach
        </div>
    @endif
    @if(session('error'))
        <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- Order summary ---------------------------------------------------}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
        <h2 class="font-semibold text-gray-800 mb-3">Übersicht</h2>
        <div class="text-sm text-blue-800 bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
            Mietzeitraum:
            <strong>{{ $from->format('d.m.Y') }}</strong> bis
            <strong>{{ $until->format('d.m.Y') }}</strong>
            @if($timeModel)
                · {{ $timeModel->name }}
            @endif
        </div>
        <div class="space-y-2">
            @foreach($summary as $row)
                <div class="flex justify-between items-center text-sm">
                    <div>
                        <span class="font-medium text-gray-800">{{ $row['item']->name }}</span>
                        @if($row['packaging_unit'])
                            <span class="text-gray-500"> · {{ $row['packaging_unit']->label }} × {{ $row['qty'] }}</span>
                        @else
                            <span class="text-gray-500"> × {{ $row['qty'] }} {{ $row['item']->unit_label }}</span>
                        @endif
                    </div>
                    <div class="text-gray-900">
                        @if($row['price_found'])
                            {{ milli_to_eur($row['total_price_net_milli']) }}
                        @else
                            auf Anfrage
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <div class="border-t border-gray-100 pt-3 mt-3 flex justify-between font-semibold">
            <span>Gesamt (netto)</span>
            <span>{{ $total > 0 ? milli_to_eur($total) : 'auf Anfrage' }}</span>
        </div>
        <p class="text-xs text-gray-500 mt-1">zzgl. gesetzl. MwSt.</p>
    </div>

    {{-- Checkout Form ----------------------------------------------------}}
    <form action="{{ route('rental.checkout.store') }}" method="POST" class="space-y-6">
        @csrf

        {{-- Event Location --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5"
             x-data="{
                locations: {{ $eventLocations->map(fn($l) => ['id' => $l->id, 'name' => $l->name, 'street' => $l->street ?? '', 'zip' => $l->zip ?? '', 'city' => $l->city ?? ''])->values()->toJson() }},
                fill(id) {
                    const loc = this.locations.find(l => l.id == id);
                    if (!loc) return;
                    document.querySelector('[name=event_location_name]').value   = loc.name;
                    document.querySelector('[name=event_location_street]').value = loc.street;
                    document.querySelector('[name=event_location_zip]').value    = loc.zip;
                    document.querySelector('[name=event_location_city]').value   = loc.city;
                }
             }">
            <h2 class="font-semibold text-gray-800 mb-4">Veranstaltungsort</h2>

            @if($eventLocations->isNotEmpty())
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Gespeicherten Ort übernehmen</label>
                <select @change="fill($event.target.value)"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">— Ort auswählen …</option>
                    @foreach($eventLocations as $loc)
                        <option value="{{ $loc->id }}">
                            {{ $loc->name }}@if($loc->city), {{ $loc->city }}@endif
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bezeichnung / Name</label>
                    <input type="text" name="event_location_name"
                        value="{{ old('event_location_name') }}"
                        placeholder="z.B. Vereinsheim Musterbach"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                        required>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Straße & Hausnummer</label>
                    <input type="text" name="event_location_street"
                        value="{{ old('event_location_street') }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                        required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">PLZ</label>
                    <input type="text" name="event_location_zip"
                        value="{{ old('event_location_zip') }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                        required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ort</label>
                    <input type="text" name="event_location_city"
                        value="{{ old('event_location_city') }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                        required>
                </div>
            </div>
        </div>

        {{-- Contact --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-800 mb-4">Ansprechpartner vor Ort</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="event_contact_name"
                        value="{{ old('event_contact_name', $customer->company_name ?? '') }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                        required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                    <input type="tel" name="event_contact_phone"
                        value="{{ old('event_contact_phone', $customer->phone ?? '') }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                        required>
                </div>
            </div>
        </div>

        {{-- Logistics --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-800 mb-4">Lieferung & Abholung</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Lieferung der Artikel</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" name="event_delivery_mode" value="delivery"
                                {{ old('event_delivery_mode', 'delivery') === 'delivery' ? 'checked' : '' }}
                                class="text-blue-600">
                            Lieferung zum Veranstaltungsort
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" name="event_delivery_mode" value="self_pickup"
                                {{ old('event_delivery_mode') === 'self_pickup' ? 'checked' : '' }}
                                class="text-blue-600">
                            Selbstabholung bei uns
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rückgabe der Artikel</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" name="event_pickup_mode" value="pickup_by_us"
                                {{ old('event_pickup_mode', 'pickup_by_us') === 'pickup_by_us' ? 'checked' : '' }}
                                class="text-blue-600">
                            Abholung bei der Veranstaltung
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" name="event_pickup_mode" value="self_return"
                                {{ old('event_pickup_mode') === 'self_return' ? 'checked' : '' }}
                                class="text-blue-600">
                            Rückgabe bei uns
                        </label>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Zufahrtshinweise</label>
                <textarea name="event_access_notes" rows="2"
                    placeholder="z.B. Zufahrt über Hintereingang, Barriere-Code 1234 ..."
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">{{ old('event_access_notes') }}</textarea>
            </div>
        </div>

        {{-- Location Details --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-800 mb-4">Details zum Veranstaltungsort</h2>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="event_has_power" value="1"
                        {{ old('event_has_power') ? 'checked' : '' }}
                        class="w-4 h-4 text-blue-600 rounded">
                    <span class="text-sm text-gray-700">Stromversorgung vorhanden</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="event_suitable_ground" value="1"
                        {{ old('event_suitable_ground') ? 'checked' : '' }}
                        class="w-4 h-4 text-blue-600 rounded">
                    <span class="text-sm text-gray-700">Ebener/fester Untergrund</span>
                </label>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Aufbauhinweise</label>
                <textarea name="event_setup_notes" rows="2"
                    placeholder="Besonderheiten beim Aufbau ..."
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">{{ old('event_setup_notes') }}</textarea>
            </div>
        </div>

        {{-- Notes --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-800 mb-2">Anmerkungen</h2>
            <textarea name="customer_notes" rows="3"
                placeholder="Weitere Hinweise oder Wünsche ..."
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">{{ old('customer_notes') }}</textarea>
        </div>

        {{-- Submit --}}
        <div class="flex gap-3 flex-wrap">
            <button type="submit"
                style="background-color:#2563eb;color:#fff"
                class="flex-1 sm:flex-none hover:bg-blue-700 font-semibold px-10 py-3 rounded-xl transition">
                Leihauftrag absenden →
            </button>
            <a href="{{ route('rental.cart') }}"
                class="border border-gray-300 text-gray-700 hover:bg-gray-50 px-6 py-3 rounded-xl transition">
                Zurück zum Warenkorb
            </a>
        </div>
    </form>

</div>
@endsection
