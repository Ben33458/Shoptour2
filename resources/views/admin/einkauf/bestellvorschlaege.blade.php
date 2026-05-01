@extends('admin.layout')

@section('title', 'Bestellvorschläge')

@section('content')

<div class="mb-3">
    <a href="{{ route('admin.einkauf.index') }}" class="text-sm text-muted">&larr; Zurück zu Einkauf</a>
    <h2 class="mt-1">Bestellvorschläge</h2>
    <p class="text-sm text-muted">Produkte, deren Bestand unter dem Mindestbestand liegt. Bereits offene Bestellungen werden berücksichtigt.</p>
</div>

{{-- Warehouse filter --}}
<form method="GET" action="{{ route('admin.einkauf.bestellvorschlaege') }}" class="mb-3 flex gap-2 items-end">
    <div>
        <label class="text-xs text-muted">Lager</label>
        <select name="warehouse_id" class="input input-sm">
            <option value="">Alle Lager</option>
            @foreach($warehouses as $wh)
                <option value="{{ $wh->id }}" {{ $warehouseId == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn btn-sm btn-outline">Filtern</button>
</form>

@if($proposals->isEmpty())
    <div class="card">
        <div class="p-4 text-center text-muted">
            Keine Bestellvorschläge vorhanden. Alle Produkte liegen über dem Mindestbestand.
        </div>
    </div>
@else
    @foreach($proposals as $group)
    <div class="card mb-3">
        <div class="card-header flex justify-between items-center">
            <span class="font-bold">{{ $group['supplier_name'] }}</span>
            <form method="GET" action="{{ route('admin.einkauf.create') }}">
                <input type="hidden" name="supplier_id" value="{{ $group['supplier_id'] }}">
                <input type="hidden" name="items" value="{{ json_encode(
                    collect($group['items'])->map(fn ($item) => [
                        'product_id' => $item['product_id'],
                        'qty' => $item['suggested_qty'],
                        'unit_purchase_milli' => $item['unit_purchase_milli'],
                        'notes' => '',
                    ])->values()->toArray()
                ) }}">
                <button type="submit" class="btn btn-sm btn-primary">
                    Bestellung erstellen ({{ count($group['items']) }} Positionen)
                </button>
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Art.-Nr.</th>
                        <th>Produkt</th>
                        <th>Lager</th>
                        <th class="text-right">Bestand</th>
                        <th class="text-right">Reserviert</th>
                        <th class="text-right">Verfügbar</th>
                        <th class="text-right">Eingehend (PO)</th>
                        <th class="text-right">Mindest</th>
                        <th class="text-right text-danger">Fehlmenge</th>
                        <th class="text-right font-bold">Vorschlag</th>
                        <th class="text-right">EK-Preis</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($group['items'] as $item)
                    <tr>
                        <td class="font-mono text-sm">{{ $item['artikelnummer'] }}</td>
                        <td>{{ $item['produktname'] }}</td>
                        <td class="text-muted text-sm">{{ $item['warehouse_name'] }}</td>
                        <td class="text-right">{{ number_format($item['current_stock'], 0, ',', '.') }}</td>
                        <td class="text-right text-muted">{{ number_format($item['reserved'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($item['available'], 0, ',', '.') }}</td>
                        <td class="text-right text-muted">{{ $item['incoming'] > 0 ? number_format($item['incoming'], 0, ',', '.') : '—' }}</td>
                        <td class="text-right">{{ number_format($item['reorder_point'], 0, ',', '.') }}</td>
                        <td class="text-right text-danger font-bold">{{ number_format($item['shortage'], 0, ',', '.') }}</td>
                        <td class="text-right font-bold">{{ number_format($item['suggested_qty'], 0, ',', '.') }}</td>
                        <td class="text-right text-muted">{{ number_format($item['unit_purchase_milli'] / 1_000_000, 2, ',', '.') }} &euro;</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
@endif

@endsection
