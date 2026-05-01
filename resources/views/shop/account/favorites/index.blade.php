@php use Illuminate\Support\Facades\Storage; @endphp
@extends('shop.account.account-layout')

@section('title', 'Stammsortiment')

@section('account-content')

@include('components.onboarding-banner')

<div>

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Stammsortiment</h1>
            <p class="text-sm text-gray-400 mt-0.5">Ihre persönliche Produktliste mit Soll- und Istbeständen</p>
        </div>
        @if($perms->canOrderFromFavorites() || $perms->canOrderAll())
        <div class="flex gap-2">
            <form method="POST" action="{{ route('account.favorites.add-all') }}">
                @csrf
                <button type="submit"
                        class="bg-white border border-amber-300 text-amber-700 hover:bg-amber-50 font-semibold rounded-xl px-4 py-2 text-sm transition-colors">
                    Alle in Warenkorb
                </button>
            </form>
            <form method="POST" action="{{ route('account.favorites.order-all') }}">
                @csrf
                <button type="submit"
                        class="bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl px-4 py-2 text-sm transition-colors">
                    Direkt bestellen
                </button>
            </form>
        </div>
        @endif
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 mb-4 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('info'))
        <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-xl px-4 py-3 mb-4 text-sm">
            {{ session('info') }}
        </div>
    @endif

    {{-- Add product search --}}
    @if($perms->canEditTargetStock() || !Auth::user()->isSubUser())
    <div class="bg-white rounded-2xl border border-gray-100 p-4 mb-6"
         x-data="{
            query: '',
            results: [],
            loading: false,
            async search() {
                if (this.query.length < 2) { this.results = []; return; }
                this.loading = true;
                const r = await fetch('{{ route('account.favorites.search') }}?q=' + encodeURIComponent(this.query));
                this.results = await r.json();
                this.loading = false;
            },
            async add(id) {
                const form = document.getElementById('add-favorite-form');
                form.querySelector('[name=product_id]').value = id;
                form.submit();
            }
         }">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Produkt hinzufügen</p>
        <div class="relative">
            <input type="text" x-model="query" @input.debounce.300ms="search()"
                   placeholder="Produktname oder Artikelnummer…"
                   class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
            <div x-show="results.length > 0"
                 class="absolute z-20 bg-white border border-gray-200 rounded-xl shadow-lg mt-1 w-full max-h-64 overflow-y-auto">
                <template x-for="p in results" :key="p.id">
                    <button @click="add(p.id)"
                            class="w-full text-left px-4 py-2.5 hover:bg-amber-50 border-b border-gray-50 last:border-0">
                        <span class="font-medium text-sm text-gray-900" x-text="p.name"></span>
                        <span class="ml-2 text-xs text-gray-400" x-text="p.gebinde ? '(' + p.gebinde + ')' : ''"></span>
                        <span class="text-xs text-gray-400 float-right" x-text="p.sku"></span>
                    </button>
                </template>
            </div>
        </div>
        <form id="add-favorite-form" method="POST" action="{{ route('account.favorites.store') }}" class="hidden">
            @csrf
            <input type="hidden" name="product_id" value="">
        </form>
    </div>
    @endif

    {{-- Favorites table --}}
    @if($favorites->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 p-12 text-center">
            <div class="text-4xl mb-3">📋</div>
            <p class="text-gray-500 text-sm">Noch keine Produkte im Stammsortiment.</p>
            @if($perms->canEditTargetStock() || !Auth::user()->isSubUser())
            <p class="text-gray-400 text-xs mt-1">Nutzen Sie die Suche oben, um Produkte hinzuzufügen.</p>
            @endif
        </div>
    @else
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="w-8 px-3 py-3"></th>{{-- drag handle --}}
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Produkt</th>
                    @if($perms->canSeePrices())
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Preis/Gbd.</th>
                    @endif
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Istbestand</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Sollbestand</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Bestellmenge</th>
                    @if($perms->canOrderFromFavorites() || $perms->canOrderAll())
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Aktion</th>
                    @endif
                    <th class="w-10 px-3 py-3"></th>{{-- delete --}}
                </tr>
            </thead>
            <tbody id="favorites-sortable">
                @foreach($favorites as $fav)
                @php
                    $product   = $fav->product;
                    $orderable = $fav->isOrderable();
                    $orderQty  = $fav->orderQty();
                    $price     = $prices[$fav->product_id] ?? null;
                @endphp
                <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors"
                    data-id="{{ $fav->id }}"
                    x-data="{
                        actual: {{ $fav->actual_stock_units }},
                        target: {{ $fav->target_stock_units }},
                        saving: false,
                        get orderQty() { return Math.max(0, this.target - this.actual); },
                        async saveActual() {
                            this.saving = true;
                            await fetch('{{ route('account.favorites.actual-stock', $fav) }}', {
                                method: 'PATCH',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                body: JSON.stringify({ actual_stock_units: this.actual })
                            });
                            this.saving = false;
                        },
                        async saveTarget() {
                            this.saving = true;
                            await fetch('{{ route('account.favorites.target-stock', $fav) }}', {
                                method: 'PATCH',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                body: JSON.stringify({ target_stock_units: this.target })
                            });
                            this.saving = false;
                        }
                    }">
                    {{-- Drag handle --}}
                    <td class="px-3 py-3 cursor-grab text-gray-300 sortable-handle select-none" title="Sortieren">⠿</td>

                    {{-- Product info --}}
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            @if($product?->mainImage)
                                <img src="{{ Storage::url($product->mainImage->path) }}"
                                     class="w-10 h-10 rounded-lg object-contain bg-gray-50 flex-shrink-0" alt="">
                            @else
                                <div class="w-10 h-10 rounded-lg bg-gray-100 flex-shrink-0"></div>
                            @endif
                            <div>
                                @if($product)
                                <a href="{{ route('shop.product', $product->slug) }}"
                                   class="font-medium text-gray-900 hover:text-amber-600 leading-tight block">
                                    {{ $product->produktname }}
                                </a>
                                @else
                                <p class="font-medium text-gray-400 leading-tight italic text-xs">Produkt nicht mehr verfügbar</p>
                                @endif
                                <p class="text-xs text-gray-400">
                                    {{ $product?->gebinde?->name }}
                                    @if($product) · {{ $product->artikelnummer }} @endif
                                </p>
                                @if(!$orderable && $product)
                                    <span class="text-xs text-red-500 font-medium">
                                        @if($product->availability_mode === 'discontinued') Abgekündigt
                                        @elseif($product->availability_mode === 'out_of_stock') Nicht verfügbar
                                        @else Nicht bestellbar
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    </td>

                    {{-- Price --}}
                    @if($perms->canSeePrices())
                    <td class="px-4 py-3 text-right text-gray-700 font-medium whitespace-nowrap">
                        @if($price)
                            {{ milli_to_eur($price->grossMilli) }}
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    @endif

                    {{-- Istbestand --}}
                    <td class="px-4 py-3 text-center">
                        <input type="number" min="0" max="9999"
                               x-model.number="actual"
                               @change="saveActual()"
                               class="w-16 text-center border border-gray-200 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </td>

                    {{-- Sollbestand --}}
                    <td class="px-4 py-3 text-center">
                        @if($perms->canEditTargetStock())
                        <input type="number" min="0" max="9999"
                               x-model.number="target"
                               @change="saveTarget()"
                               class="w-16 text-center border border-gray-200 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                        @else
                        <span class="text-gray-700" x-text="target"></span>
                        @endif
                    </td>

                    {{-- Bestellmenge --}}
                    <td class="px-4 py-3 text-center">
                        <span x-text="orderQty"
                              :class="orderQty > 0 ? 'font-bold text-amber-600' : 'text-gray-300'"></span>
                    </td>

                    {{-- Add to cart --}}
                    @if($perms->canOrderFromFavorites() || $perms->canOrderAll())
                    <td class="px-4 py-3 text-center">
                        @if($orderable)
                        <form method="POST" action="{{ route('cart.add') }}">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $fav->product_id }}">
                            <input type="hidden" name="qty" :value="orderQty > 0 ? orderQty : 1">
                            <button type="submit"
                                    :disabled="!$el.closest('tr')"
                                    class="text-amber-600 hover:text-amber-700 text-xs font-medium whitespace-nowrap">
                                In Warenkorb
                            </button>
                        </form>
                        @else
                        <span class="text-gray-300 text-xs">—</span>
                        @endif
                    </td>
                    @endif

                    {{-- Delete --}}
                    <td class="px-3 py-3">
                        <form method="POST" action="{{ route('account.favorites.destroy', $fav) }}" id="fav-del-{{ $fav->id }}">
                            @csrf
                            @method('DELETE')
                            <button type="button"
                                    onclick="shopConfirm('Aus Stammsortiment entfernen', '{{ addslashes($fav->product->produktname ?? 'Dieses Produkt') }} aus dem Stammsortiment entfernen?', 'Entfernen').then(function(ok){ if(ok) document.getElementById('fav-del-{{ $fav->id }}').submit(); })"
                                    class="text-gray-300 hover:text-red-400 transition-colors text-lg leading-none">
                                ×
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
    const el = document.getElementById('favorites-sortable');
    if (el) {
        Sortable.create(el, {
            handle: '.sortable-handle',
            animation: 150,
            onEnd: async function () {
                const ids = Array.from(el.querySelectorAll('tr[data-id]')).map(r => parseInt(r.dataset.id));
                await fetch('{{ route('account.favorites.reorder') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ ids })
                });
            }
        });
    }
</script>
@endpush
@endsection
