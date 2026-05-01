@extends('admin.layout')

@section('title', $item->name)

@section('actions')
    <a href="{{ route('admin.rental.items.edit', $item) }}" class="btn btn-primary btn-sm">Bearbeiten</a>
    <a href="{{ route('admin.rental.items.index') }}" class="btn btn-outline btn-sm">← Übersicht</a>
@endsection

@section('content')

{{-- Stammdaten --}}
<div class="card">
    <div class="card-header">Stammdaten</div>
    <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
            <div class="hint">Name</div>
            <div><strong>{{ $item->name }}</strong></div>
        </div>
        <div>
            <div class="hint">Artikelnummer</div>
            <div>{{ $item->article_number ?? '—' }}</div>
        </div>
        <div>
            <div class="hint">Slug</div>
            <div><code>{{ $item->slug }}</code></div>
        </div>
        <div>
            <div class="hint">Kategorie</div>
            <div>{{ $item->category?->name ?? '—' }}</div>
        </div>
        <div>
            <div class="hint">Abrechnungsart</div>
            <div>
                @switch($item->billing_mode)
                    @case('per_item') Pro Stück @break
                    @case('per_pack') Pro Gebinde @break
                    @case('per_set') Pro Set @break
                    @case('flat') Pauschal @break
                    @default {{ $item->billing_mode }}
                @endswitch
            </div>
        </div>
        <div>
            <div class="hint">Inventarart</div>
            <div>
                @switch($item->inventory_mode)
                    @case('unit_based') Einheitenbasiert @break
                    @case('quantity_based') Mengenbasiert @break
                    @case('component_based') Komponentenbasiert @break
                    @case('packaging_based') Verpackungsbasiert @break
                    @default {{ $item->inventory_mode }}
                @endswitch
            </div>
        </div>
        <div>
            <div class="hint">Transportklasse</div>
            <div>
                @switch($item->transport_class)
                    @case('small') Klein @break
                    @case('normal') Normal @break
                    @case('truck') LKW @break
                    @default {{ $item->transport_class }}
                @endswitch
            </div>
        </div>
        <div>
            <div class="hint">Status</div>
            <div>
                @if($item->active)
                    <span class="badge badge-delivered">aktiv</span>
                @else
                    <span class="badge badge-cancelled">inaktiv</span>
                @endif
                @if($item->allow_overbooking)
                    <span class="badge" style="margin-left:4px">Überbuchung erlaubt</span>
                @endif
            </div>
        </div>
        @if($item->inventory_mode === 'quantity_based')
        <div>
            <div class="hint">Gesamtmenge</div>
            <div>{{ $item->total_quantity }}</div>
        </div>
        @endif
        @if($item->description)
        <div style="grid-column:1/-1">
            <div class="hint">Beschreibung</div>
            <div>{{ $item->description }}</div>
        </div>
        @endif
    </div>
</div>

{{-- Preisregeln --}}
<div class="card" style="margin-top:16px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Preisregeln ({{ $item->priceRules?->count() ?? 0 }})</span>
        <a href="{{ route('admin.rental.price-rules.create', ['item_id' => $item->id]) }}"
           class="btn btn-primary btn-sm">+ Preisregel anlegen</a>
    </div>
    @if($item->priceRules?->count())
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Zeitmodell</th>
                    <th>Preistyp</th>
                    <th>Kundengruppe</th>
                    <th style="text-align:right">Preis netto</th>
                    <th style="text-align:right">Brutto (19%)</th>
                    <th>Min/Max</th>
                    <th>Gültig von/bis</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($item->priceRules as $rule)
                <tr>
                    <td>{{ $rule->timeModel?->name ?? '—' }}</td>
                    <td>
                        @switch($rule->price_type)
                            @case('per_item') Pro Stück @break
                            @case('per_pack') Pro Gebinde @break
                            @case('per_set') Pro Set @break
                            @case('flat') Pauschal @break
                            @default {{ $rule->price_type ?? '—' }}
                        @endswitch
                    </td>
                    <td>{{ $rule->customerGroup?->name ?? 'Alle' }}</td>
                    <td style="text-align:right">
                        @if($rule->price_net_milli !== null)
                            {{ number_format($rule->price_net_milli / 1000000, 2, ',', '.') }} €
                        @else
                            —
                        @endif
                    </td>
                    <td style="text-align:right">
                        @if($rule->price_net_milli !== null)
                            {{ number_format($rule->price_net_milli * 1.19 / 1000000, 2, ',', '.') }} €
                        @else
                            —
                        @endif
                    </td>
                    <td style="white-space:nowrap">
                        {{ $rule->min_quantity }}
                        @if($rule->max_quantity) – {{ $rule->max_quantity }} @else + @endif
                    </td>
                    <td style="white-space:nowrap;font-size:0.85em">
                        {{ $rule->valid_from?->format('d.m.Y') ?? '—' }}
                        –
                        {{ $rule->valid_until?->format('d.m.Y') ?? '∞' }}
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.rental.price-rules.edit', $rule) }}"
                           class="btn btn-outline btn-sm">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.rental.price-rules.destroy', $rule) }}"
                              style="display:inline" onsubmit="return confirm('Preisregel löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm" style="color:var(--c-danger)">Löschen</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div style="padding:20px;text-align:center;color:var(--c-muted)">
        Noch keine Preisregeln angelegt.
    </div>
    @endif
</div>

{{-- Inventareinheiten (unit_based) --}}
@if($item->inventory_mode === 'unit_based')
<div class="card" style="margin-top:16px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Inventareinheiten ({{ $item->units?->count() ?? 0 }})</span>
        <a href="{{ route('admin.rental.inventory-units.create', ['rental_item_id' => $item->id]) }}"
           class="btn btn-primary btn-sm">+ Einheit anlegen</a>
    </div>
    @if($item->units?->count())
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Inventarnummer</th>
                    <th>Status</th>
                    <th style="text-align:center">Bevorzugt</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($item->units as $unit)
                <tr>
                    <td>{{ $unit->inventory_number }}</td>
                    <td>
                        @switch($unit->status)
                            @case('available')    <span class="badge badge-delivered">verfügbar</span> @break
                            @case('reserved')     <span class="badge">reserviert</span> @break
                            @case('in_use')       <span class="badge badge-pending">im Einsatz</span> @break
                            @case('maintenance')  <span class="badge">Wartung</span> @break
                            @case('defective')    <span class="badge badge-cancelled">defekt</span> @break
                            @case('retired')      <span class="badge badge-cancelled">ausgemustert</span> @break
                            @default {{ $unit->status }}
                        @endswitch
                    </td>
                    <td style="text-align:center">{{ $unit->preferred_for_booking ? '✓' : '—' }}</td>
                    <td style="text-align:right">
                        <a href="{{ route('admin.rental.inventory-units.show', $unit) }}"
                           class="btn btn-outline btn-sm">Details</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div style="padding:20px;text-align:center;color:var(--c-muted)">
        Noch keine Inventareinheiten angelegt.
    </div>
    @endif
</div>
@endif

{{-- Komponenten --}}
@if($item->components?->count())
<div class="card" style="margin-top:16px">
    <div class="card-header">Komponenten ({{ $item->components->count() }})</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Komponente</th>
                    <th style="text-align:center">Menge</th>
                </tr>
            </thead>
            <tbody>
            @foreach($item->components as $component)
                <tr>
                    <td>{{ $component->name }}</td>
                    <td style="text-align:center">{{ $component->pivot->quantity ?? 1 }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Verpackungseinheiten --}}
@if($item->inventory_mode === 'packaging_based')
<div class="card" style="margin-top:16px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Verpackungseinheiten ({{ $item->packagingUnits?->count() ?? 0 }})</span>
        <a href="{{ route('admin.rental.packaging-units.create', ['item_id' => $item->id]) }}"
           class="btn btn-primary btn-sm">+ VPE anlegen</a>
    </div>
    @if($item->packagingUnits?->count())
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Bezeichnung</th>
                    <th style="text-align:center">Stück/Gebinde</th>
                    <th style="text-align:center">Verfügbare Gebinde</th>
                    <th style="text-align:center">Sortierung</th>
                    <th style="text-align:center">Aktiv</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($item->packagingUnits as $pu)
                <tr>
                    <td>{{ $pu->label }}</td>
                    <td style="text-align:center">{{ $pu->pieces_per_pack }}</td>
                    <td style="text-align:center">{{ $pu->available_packs }}</td>
                    <td style="text-align:center">{{ $pu->sort_order }}</td>
                    <td style="text-align:center">
                        @if($pu->active)
                            <span class="badge badge-delivered">aktiv</span>
                        @else
                            <span class="badge badge-cancelled">inaktiv</span>
                        @endif
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.rental.packaging-units.edit', $pu) }}"
                           class="btn btn-outline btn-sm">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.rental.packaging-units.destroy', $pu) }}"
                              style="display:inline" onsubmit="return confirm('Verpackungseinheit löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm" style="color:var(--c-danger)">Löschen</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div style="padding:20px;text-align:center;color:var(--c-muted)">
        Noch keine Verpackungseinheiten angelegt.
    </div>
    @endif
</div>
@endif

@endsection
