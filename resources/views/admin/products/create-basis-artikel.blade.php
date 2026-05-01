@extends('admin.layout')

@section('title', 'Basis-Artikel erstellen für ' . $source->artikelnummer)

@section('actions')
    <a href="{{ route('admin.products.show', $source) }}" class="btn btn-outline btn-sm">← {{ $source->artikelnummer }}</a>
@endsection

@section('content')

@if(session('error'))
    <div class="alert alert-danger" style="margin-bottom:16px">{{ session('error') }}</div>
@endif

<div style="margin-bottom:16px;padding:12px 20px;background:#eff6ff;border-left:4px solid #2563eb;border-radius:6px">
    <strong>Basis-Artikel für:</strong> {{ $source->produktname }} ({{ $source->artikelnummer }})
    <span style="color:var(--c-muted);font-size:.85em;margin-left:8px">— Stammdaten wurden übernommen, Artikelnummer bitte manuell eingeben</span>
</div>

<div class="card">
    <div class="card-header">Neuen Basis-Artikel anlegen</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.products.store-basis-artikel', $source) }}">
            @csrf

            @php $product = null; @endphp

            @include('admin.products._form', [
                'product'          => null,
                'brands'           => $brands,
                'productLines'     => $productLines,
                'categories'       => $categories,
                'gebindeList'      => $gebindeList,
                'taxRates'         => $taxRates,
                'defaultTaxRateId' => $defaultTaxRateId,
            ])

            <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Produktname
                var nameEl = document.querySelector('[name="produktname"]');
                if (nameEl && !nameEl.value) nameEl.value = @js($suggestedName);

                // Kategorie
                var catSel = document.querySelector('[name="category_id"]');
                if (catSel) catSel.value = @js((string)($source->category_id ?? ''));

                // Verfügbarkeit
                var avSel = document.querySelector('[name="availability_mode"]');
                if (avSel) avSel.value = @js($source->availability_mode ?? 'available');

                // Alkoholgehalt
                var alkEl = document.querySelector('[name="alkoholgehalt_vol_percent"]');
                if (alkEl && !alkEl.value) alkEl.value = @js((string)($source->alkoholgehalt_vol_percent ?? ''));

                // Inhalt/Einheit (unit_volume_l)
                var unitVolEl = document.getElementById('unit_volume_l');
                if (unitVolEl && !unitVolEl.value) unitVolEl.value = @js($suggestedUnitVolumeL);

                // Gebinde-Units = 1 (Einzelflasche)
                var gbUnitsEl = document.getElementById('gebinde_units');
                if (gbUnitsEl && !gbUnitsEl.value) gbUnitsEl.value = '1';

                // Gesamtvolumen neu berechnen
                if (gbUnitsEl) gbUnitsEl.dispatchEvent(new Event('input'));

                // Netto-Preis
                var netEl = document.getElementById('price_net_eur');
                if (netEl && !netEl.value) {
                    netEl.value = @js(number_format($suggestedNetMilli / 1_000_000, 4, '.', ''));
                    if (typeof priceFromNet === 'function') priceFromNet(netEl);
                }
            });
            </script>

            <div style="margin-top:24px;display:flex;gap:12px">
                <button type="submit" class="btn btn-primary">Basis-Artikel anlegen</button>
                <a href="{{ route('admin.products.show', $source) }}" class="btn btn-outline">Abbrechen</a>
            </div>
        </form>
    </div>
</div>

@endsection
