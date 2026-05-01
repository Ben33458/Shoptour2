@extends('shop.layout')

@section('title', 'Leihen — Festinventar & Getränkeequipment')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-10">

    {{-- Hero ----------------------------------------------------------------}}
    <div class="text-center mb-10">
        <h1 class="text-3xl font-bold text-gray-900 mb-3">Festinventar & Equipment leihen</h1>
        <p class="text-gray-600 text-lg max-w-2xl mx-auto">
            Gläser, Kühlgeräte, Zapfanlagen, Stehtische und mehr — einfach online buchen
            und zum Veranstaltungstermin liefern lassen oder abholen.
        </p>
    </div>

    {{-- Date Form -----------------------------------------------------------}}
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8"
         x-data="rentalDatePicker()">

        <h2 class="text-xl font-semibold text-gray-800 mb-2">Wann brauchst du die Artikel?</h2>
        <p class="text-sm text-gray-500 mb-6">Wähle Liefer- und Rückgabedatum um die Verfügbarkeit zu prüfen.</p>

        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('rental.set-dates') }}" method="POST" id="rental-date-form">
            @csrf
            @php $defaultTimeModel = $timeModels->firstWhere('rule_type', 'event') ?? $timeModels->first(); @endphp
            @if($defaultTimeModel)
                <input type="hidden" name="time_model_id" value="{{ $defaultTimeModel->id }}">
            @endif
            <input type="hidden" name="date_from"  x-bind:value="dateFrom">
            <input type="hidden" name="date_until" x-bind:value="dateUntil">

            {{-- Combined date range display (Booking.com style) --}}
            <div class="mb-6">
                <div class="flex rounded-xl border-2 border-gray-200 overflow-hidden hover:border-blue-400 transition-colors focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-100">

                    {{-- Lieferdatum --}}
                    <label class="flex-1 flex flex-col px-4 py-3 cursor-pointer min-w-0"
                           @click="$refs.dateFrom.showPicker ? $refs.dateFrom.showPicker() : $refs.dateFrom.focus()">
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Lieferdatum</span>
                        <span class="text-sm font-medium text-gray-800" x-text="dateFrom ? formatDate(dateFrom) : 'Datum wählen…'"></span>
                        <input type="date" x-ref="dateFrom" x-model="dateFrom" @change="onFromChange"
                               min="{{ now()->toDateString() }}"
                               class="sr-only" tabindex="0">
                    </label>

                    {{-- Divider --}}
                    <div class="flex items-center px-3 text-gray-300 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </div>

                    {{-- Rückgabedatum --}}
                    <label class="flex-1 flex flex-col px-4 py-3 cursor-pointer border-l border-gray-200 min-w-0"
                           @click="$refs.dateUntil.showPicker ? $refs.dateUntil.showPicker() : $refs.dateUntil.focus()">
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Rückgabedatum</span>
                        <span class="text-sm font-medium" :class="dateUntil ? 'text-gray-800' : 'text-gray-400'"
                              x-text="dateUntil ? formatDate(dateUntil) : (dateFrom ? 'Datum wählen…' : '—')"></span>
                        <input type="date" x-ref="dateUntil" x-model="dateUntil"
                               :min="dateFrom || '{{ now()->toDateString() }}'"
                               class="sr-only" tabindex="0">
                    </label>

                </div>
                {{-- Duration badge --}}
                <p class="text-xs text-gray-400 mt-2 ml-1" x-show="dateFrom && dateUntil" x-cloak
                   x-text="durationText()"></p>
            </div>

            <button type="submit" :disabled="!dateFrom || !dateUntil"
                style="background-color:#2563eb;color:#fff"
                class="w-full sm:w-auto font-semibold px-10 py-3 rounded-xl transition shadow-sm flex items-center justify-center gap-2 hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                Festbedarf auswählen
            </button>
        </form>
    </div>

    {{-- Already has a cart --}}
    @if(!empty($cart['date_from']) && !empty($cart['date_until']))
        <div class="mt-4 text-center">
            <a href="{{ route('rental.catalog') }}"
                class="text-sm text-blue-600 underline hover:no-underline">
                Direkt zum Katalog für {{ \Carbon\Carbon::parse($cart['date_from'])->format('d.m.') }}–{{ \Carbon\Carbon::parse($cart['date_until'])->format('d.m.Y') }} →
            </a>
        </div>
    @endif

    {{-- Features -----------------------------------------------------------}}
    <div class="mt-12 grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="p-5 rounded-xl bg-white border border-gray-100 shadow-sm text-center">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
            </div>
            <h3 class="font-semibold text-gray-800 mb-1">Riesige Auswahl</h3>
            <p class="text-sm text-gray-500">Gläser, Kühlgeräte, Zapfanlagen, Tische & mehr</p>
        </div>
        <div class="p-5 rounded-xl bg-white border border-gray-100 shadow-sm text-center">
            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
            </div>
            <h3 class="font-semibold text-gray-800 mb-1">Lieferung & Abholung</h3>
            <p class="text-sm text-gray-500">Wir liefern zum Veranstaltungsort und holen wieder ab</p>
        </div>
        <div class="p-5 rounded-xl bg-white border border-gray-100 shadow-sm text-center">
            <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <h3 class="font-semibold text-gray-800 mb-1">Einfache Buchung</h3>
            <p class="text-sm text-gray-500">Online anfragen, wir kümmern uns um den Rest</p>
        </div>
    </div>

</div>

<script>
function rentalDatePicker() {
    return {
        dateFrom:  '{{ $cart['date_from'] ?? '' }}',
        dateUntil: '{{ $cart['date_until'] ?? '' }}',

        onFromChange() {
            if (this.dateUntil && this.dateUntil < this.dateFrom) {
                this.dateUntil = '';
            }
            if (this.dateFrom && !this.dateUntil) {
                this.$nextTick(() => {
                    if (this.$refs.dateUntil.showPicker) this.$refs.dateUntil.showPicker();
                    else this.$refs.dateUntil.focus();
                });
            }
        },

        formatDate(val) {
            if (!val) return '';
            const d = new Date(val + 'T00:00:00');
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
        },

        durationText() {
            if (!this.dateFrom || !this.dateUntil) return '';
            const days = Math.round((new Date(this.dateUntil) - new Date(this.dateFrom)) / 86400000);
            return `Mietzeitraum: ${this.formatDate(this.dateFrom)} – ${this.formatDate(this.dateUntil)} (${days + 1} Tag${days > 0 ? 'e' : ''})`;
        }
    }
}
</script>
@endsection
