@extends('admin.layout')

@section('title', 'Produkt: ' . $product->artikelnummer)

@section('actions')
    <a href="{{ route('admin.products.index') }}" class="btn btn-outline btn-sm">← Produkte</a>
    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-primary btn-sm">Bearbeiten</a>
    @if($product->is_base_item)
        <a href="{{ route('admin.lmiv.edit', $product) }}" class="btn btn-outline btn-sm">LMIV bearbeiten</a>
    @endif
@endsection

@section('content')

@php
    $tdLabel = 'padding:4px 24px 4px 0;color:var(--c-muted);white-space:nowrap;vertical-align:top';
    $tdValue = 'padding:4px 0;vertical-align:top';
@endphp

{{-- ── Stammdaten ── --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header">📦 Stammdaten</div>
    <div class="card-body">
        <table style="border-collapse:collapse;font-size:.9em;width:100%">
            <tr>
                <td style="{{ $tdLabel }}">Artikelnummer</td>
                <td style="{{ $tdValue }}"><code>{{ $product->artikelnummer }}</code></td>
            </tr>
            <tr>
                <td style="{{ $tdLabel }}">Produktname</td>
                <td style="{{ $tdValue }}">{{ $product->produktname }}</td>
            </tr>
            <tr>
                <td style="{{ $tdLabel }}">Slug</td>
                <td style="{{ $tdValue }}"><code style="font-size:.85em">{{ $product->slug }}</code></td>
            </tr>
            <tr>
                <td style="{{ $tdLabel }}">Kategorie</td>
                <td style="{{ $tdValue }}">
                    @if($product->category)
                        {{ $product->category->parent?->name ? $product->category->parent->name . ' → ' : '' }}{{ $product->category->name }}
                    @else
                        <span style="color:var(--c-muted)">–</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="{{ $tdLabel }}">Warengruppe</td>
                <td style="{{ $tdValue }}">{{ $product->warengruppe?->name ?? '–' }}</td>
            </tr>
            <tr>
                <td style="{{ $tdLabel }}">Gebinde-Typ</td>
                <td style="{{ $tdValue }}">{{ $product->gebinde?->name ?? '–' }}</td>
            </tr>
            <tr>
                <td style="{{ $tdLabel }}">Steuersatz</td>
                <td style="{{ $tdValue }}">
                    @if($product->taxRate)
                        {{ $product->taxRate->name }}
                        <span style="color:var(--c-muted);font-size:.85em">({{ number_format($product->taxRate->rate_basis_points / 100, 0) }} %)</span>
                    @else
                        <span style="color:var(--c-muted)">–</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="{{ $tdLabel }}">Aktiv</td>
                <td style="{{ $tdValue }}">
                    @if($product->active)
                        <span class="badge badge-success">Ja</span>
                    @else
                        <span class="badge">Nein</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="{{ $tdLabel }}">Im Shop anzeigen</td>
                <td style="{{ $tdValue }}">
                    @if($product->show_in_shop)
                        <span class="badge badge-success">Ja</span>
                    @else
                        <span class="badge">Nein</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="{{ $tdLabel }}">Verfügbarkeit</td>
                <td style="{{ $tdValue }}">
                    @php
                        $availLabel = match($product->availability_mode) {
                            'available'    => ['Verfügbar', 'badge-success'],
                            'preorder'     => ['Vorbestellung', 'badge-warning'],
                            'out_of_stock' => ['Nicht vorrätig', 'badge-danger'],
                            'stock_based'  => ['Lagerabhängig', ''],
                            'discontinued' => ['Eingestellt', ''],
                            default        => [$product->availability_mode, ''],
                        };
                    @endphp
                    <span class="badge {{ $availLabel[1] }}">{{ $availLabel[0] }}</span>
                    @if($product->availability_mode === 'preorder' && $product->preorder_lead_days)
                        <span style="color:var(--c-muted);font-size:.85em;margin-left:8px">{{ $product->preorder_lead_days }} Tage Vorlauf</span>
                    @endif
                    @if($product->availability_mode === 'preorder' && $product->preorder_note)
                        <div style="font-size:.85em;color:var(--c-muted);margin-top:2px">{{ $product->preorder_note }}</div>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="{{ $tdLabel }}">Basis-Artikel</td>
                <td style="{{ $tdValue }}">
                    @if($product->is_base_item)
                        <span class="badge badge-success">Ja</span>
                    @else
                        <span style="color:var(--c-muted)">Nein</span>
                    @endif
                </td>
            </tr>
            @if($product->base_item_product_id)
            <tr>
                <td style="{{ $tdLabel }}">Basis-Artikel</td>
                <td style="{{ $tdValue }}">
                    @php $bi = $product->baseItem @endphp
                    @if($bi)
                        <a href="{{ route('admin.products.show', $bi) }}">{{ $bi->artikelnummer }} – {{ $bi->produktname }}</a>
                    @else
                        ID {{ $product->base_item_product_id }} (nicht gefunden)
                    @endif
                </td>
            </tr>
            @endif
            @if($product->sales_unit_note)
            <tr>
                <td style="{{ $tdLabel }}">VKE-Hinweis</td>
                <td style="{{ $tdValue }}">{{ $product->sales_unit_note }}</td>
            </tr>
            @endif
            <tr>
                <td style="{{ $tdLabel }}">Angelegt</td>
                <td style="{{ $tdValue }}" style="color:var(--c-muted);font-size:.85em">{{ $product->created_at->format('d.m.Y H:i') }}</td>
            </tr>
            <tr>
                <td style="{{ $tdLabel }}">Zuletzt geändert</td>
                <td style="{{ $tdValue }}" style="color:var(--c-muted);font-size:.85em">{{ $product->updated_at->format('d.m.Y H:i') }}</td>
            </tr>
        </table>

        <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <form method="POST" action="{{ route('admin.products.mark-base-item', $product) }}">
                @csrf
                @if($product->is_base_item)
                    <input type="hidden" name="is_base_item" value="0">
                    <button type="submit" class="btn btn-outline btn-sm"
                            onclick="return confirm('Basis-Artikel-Markierung entfernen?')">
                        Basis-Artikel-Markierung entfernen
                    </button>
                @else
                    <input type="hidden" name="is_base_item" value="1">
                    <button type="submit" class="btn btn-primary btn-sm">Als Basis-Artikel markieren</button>
                @endif
            </form>

            @unless($product->is_base_item)
                <a href="{{ route('admin.products.create-basis-artikel', $product) }}"
                   class="btn btn-outline btn-sm">
                    Basis-Artikel erstellen
                </a>
            @endunless
        </div>
    </div>
</div>

{{-- ── Preise ── --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header">💶 Preise</div>
    <div class="card-body">
        <table style="border-collapse:collapse;font-size:.9em;width:100%">
            <tr>
                <td style="{{ $tdLabel }}">VK Netto</td>
                <td style="{{ $tdValue }}">{{ number_format($product->base_price_net_milli / 1_000_000, 2, ',', '.') }} €</td>
            </tr>
            <tr>
                <td style="{{ $tdLabel }}">VK Brutto</td>
                <td style="{{ $tdValue }}"><strong>{{ number_format($product->base_price_gross_milli / 1_000_000, 2, ',', '.') }} €</strong></td>
            </tr>
            @if($product->grundpreis_text)
            <tr>
                <td style="{{ $tdLabel }}">Grundpreis (PAngV)</td>
                <td style="{{ $tdValue }}">
                    <span style="display:inline-block;padding:2px 8px;border-radius:4px;background:#eff6ff;color:#1d4ed8;font-weight:600">{{ $product->grundpreis_text }}</span>
                </td>
            </tr>
            @endif
            @if($product->alkoholgehalt_vol_percent !== null)
            <tr>
                <td style="{{ $tdLabel }}">Alkoholgehalt</td>
                <td style="{{ $tdValue }}">{{ number_format($product->alkoholgehalt_vol_percent, 1, ',', '') }} % vol.</td>
            </tr>
            @endif
        </table>
    </div>
</div>

{{-- ── Volumen & Gebinde ── --}}
@if($product->volume_ml || $product->gebinde_units || $product->unit_volume_ml)
<div class="card" style="margin-bottom:16px">
    <div class="card-header">📐 Volumen & Gebinde</div>
    <div class="card-body">
        <table style="border-collapse:collapse;font-size:.9em;width:100%">
            @if($product->gebinde_units)
            <tr>
                <td style="{{ $tdLabel }}">Einheiten</td>
                <td style="{{ $tdValue }}">{{ $product->gebinde_units }}</td>
            </tr>
            @endif
            @if($product->unit_volume_ml)
            <tr>
                <td style="{{ $tdLabel }}">Inhalt/Einheit</td>
                <td style="{{ $tdValue }}">{{ number_format($product->unit_volume_ml / 1000, 3, ',', '') }} L
                    <span style="color:var(--c-muted);font-size:.85em">({{ $product->unit_volume_ml }} ml)</span>
                </td>
            </tr>
            @endif
            @if($product->volume_ml)
            <tr>
                <td style="{{ $tdLabel }}">Gesamtinhalt</td>
                <td style="{{ $tdValue }}">
                    @php $totalL = $product->volume_ml / 1000; @endphp
                    {{ number_format($totalL, $totalL == floor($totalL) ? 0 : ($totalL * 10 == floor($totalL * 10) ? 1 : 3), ',', '') }} L
                    <span style="color:var(--c-muted);font-size:.85em">({{ $product->volume_ml }} ml)</span>
                </td>
            </tr>
            @endif
            @if($product->gebinde_unit_text)
            <tr>
                <td style="{{ $tdLabel }}">Gebinde-Formel</td>
                <td style="{{ $tdValue }}">{{ $product->gebinde_unit_text }}</td>
            </tr>
            @endif
        </table>
    </div>
</div>
@endif

{{-- ── Externe Verknüpfungen & Barcodes ── --}}
@php
    $barcodes = $product->barcodes;
    $hasExternal = $product->ninox_artikel_id || $product->wawi_artikel_id || $barcodes->isNotEmpty();
@endphp
@if($hasExternal)
<div class="card" style="margin-bottom:16px">
    <div class="card-header">🔗 Externe Verknüpfungen</div>
    <div class="card-body">
        <table style="border-collapse:collapse;font-size:.9em;width:100%">
            @if($product->ninox_artikel_id)
            <tr>
                <td style="{{ $tdLabel }}">Ninox Artikel-ID</td>
                <td style="{{ $tdValue }}"><code>{{ $product->ninox_artikel_id }}</code></td>
            </tr>
            @endif
            @if($product->wawi_artikel_id)
            <tr>
                <td style="{{ $tdLabel }}">WaWi Artikel-ID</td>
                <td style="{{ $tdValue }}"><code>{{ $product->wawi_artikel_id }}</code></td>
            </tr>
            @endif
            @foreach($barcodes as $bc)
            <tr>
                <td style="{{ $tdLabel }}">Barcode ({{ $bc->barcode_type ?? 'EAN' }})</td>
                <td style="{{ $tdValue }}"><code>{{ $bc->barcode }}</code></td>
            </tr>
            @endforeach
        </table>
    </div>
</div>
@endif

{{-- ── LMIV-Versionen ── --}}
@if($product->is_base_item)
<div class="card">
    <div class="card-header">🏷️ LMIV-Versionen</div>
    <div class="card-body" style="padding:0">
        @if($lmivVersions->isEmpty())
            <p style="padding:16px;color:var(--c-muted)">Noch keine LMIV-Versionen vorhanden.</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>EAN</th>
                        <th>Status</th>
                        <th>Gültig von</th>
                        <th>Gültig bis</th>
                        <th>Grund</th>
                        <th>Erstellt von</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($lmivVersions as $ver)
                    <tr>
                        <td><strong>v{{ $ver->version_number }}</strong></td>
                        <td><code>{{ $ver->ean ?? '–' }}</code></td>
                        <td>
                            @php
                                $badgeClass = match($ver->status) {
                                    'active'   => 'badge-success',
                                    'draft'    => 'badge-warning',
                                    'archived' => '',
                                    default    => '',
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ $ver->statusLabel() }}</span>
                        </td>
                        <td>{{ $ver->effective_from?->format('d.m.Y H:i') ?? '–' }}</td>
                        <td>{{ $ver->effective_to?->format('d.m.Y H:i') ?? '–' }}</td>
                        <td style="font-size:.85em">{{ $ver->change_reason ?? '–' }}</td>
                        <td style="font-size:.85em">{{ $ver->createdBy?->name ?? 'System' }}</td>
                        <td>
                            <a href="{{ route('admin.lmiv.edit', $product) }}" class="btn btn-outline btn-sm">Bearbeiten</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endif

@endsection
