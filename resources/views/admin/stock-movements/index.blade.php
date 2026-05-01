@extends('admin.layout')

@section('title', 'Lagerbewegungen')

@section('content')

{{-- ── Filter ── --}}
<form method="GET" action="{{ route('admin.stock-movements.index') }}">
    <div class="filter-bar">
        <div class="form-group" style="flex:1">
            <label>Lagerort</label>
            <select name="warehouse_id">
                <option value="">Alle</option>
                @foreach($warehouses as $id => $name)
                    <option value="{{ $id }}" @selected($warehouseId == $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="flex:1">
            <label>Typ</label>
            <select name="type">
                <option value="">Alle Typen</option>
                @foreach($types as $value => $label)
                    <option value="{{ $value }}" @selected($movementType === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="flex:1">
            <label>Datum</label>
            <input type="date" name="date" value="{{ $date ?? '' }}">
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;padding-bottom:0">
            <button type="submit" class="btn btn-primary">Filtern</button>
            <a href="{{ route('admin.stock-movements.index') }}" class="btn btn-outline">Zurücksetzen</a>
        </div>
    </div>
</form>

@if($date)
<div class="alert alert-info mb-3" style="display:flex;justify-content:space-between;align-items:center">
    <span>Filter: Datum <strong>{{ \Carbon\Carbon::parse($date)->format('d.m.Y') }}</strong></span>
    <a href="{{ route('admin.stock-movements.index', array_filter(['warehouse_id' => $warehouseId, 'type' => $movementType])) }}" class="btn btn-sm btn-outline">× Datum-Filter entfernen</a>
</div>
@endif

<div class="card">
    <div class="card-header">
        Lagerbewegungen ({{ $movements->total() }})
        <span style="font-size:.8em;color:var(--c-muted);margin-left:8px">— Append-only Journal</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Typ</th>
                    <th>Produkt</th>
                    <th>Lagerort</th>
                    <th style="text-align:right">Menge Δ</th>
                    <th>Referenz</th>
                    <th>Notiz</th>
                    <th>Erstellt von</th>
                </tr>
            </thead>
            <tbody>
            @forelse($movements as $movement)
                <tr>
                    <td style="white-space:nowrap;font-size:.85em">
                        <a href="{{ route('admin.stock-movements.index', array_filter(['date' => $movement->created_at->format('Y-m-d'), 'warehouse_id' => $warehouseId, 'type' => $movementType])) }}"
                           style="color:inherit;text-decoration:none;border-bottom:1px dashed var(--c-muted)"
                           title="Alle Bewegungen vom {{ $movement->created_at->format('d.m.Y') }} anzeigen">
                            {{ $movement->created_at->format('d.m.Y H:i') }}
                        </a>
                    </td>
                    <td>
                        <span class="badge">{{ $movement->type_label }}</span>
                    </td>
                    <td>
                        @if($movement->product)
                            <a href="{{ route('admin.products.show', $movement->product_id) }}" style="text-decoration:none">
                                <code style="font-size:.8em">{{ $movement->product->artikelnummer }}</code>
                                <span style="font-size:.85em">{{ $movement->product->produktname }}</span>
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($movement->warehouse)
                            <a href="{{ route('admin.warehouses.show', $movement->warehouse_id) }}" style="text-decoration:none;color:inherit;border-bottom:1px dashed var(--c-muted)">
                                {{ $movement->warehouse->name }}
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td style="text-align:right;font-weight:600">
                        <span style="color: {{ $movement->quantity_delta >= 0 ? 'var(--c-success, #16a34a)' : 'var(--c-danger)' }}">
                            {{ $movement->quantity_delta >= 0 ? '+' : '' }}{{ number_format($movement->quantity_delta, 2, ',', '.') }}
                        </span>
                    </td>
                    <td style="font-size:.85em">
                        @if($movement->reference_type === 'purchase_order' && $movement->reference_id)
                            <a href="{{ route('admin.einkauf.show', $movement->reference_id) }}">
                                Wareneingang #{{ $movement->reference_id }}
                            </a>
                        @elseif($movement->reference_type)
                            {{ $movement->reference_type }} #{{ $movement->reference_id }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td style="font-size:.85em;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        {{ $movement->note ?? '—' }}
                    </td>
                    <td style="font-size:.85em">
                        @if($movement->createdBy)
                            <a href="{{ route('admin.users.index') }}" style="text-decoration:none;color:inherit;border-bottom:1px dashed var(--c-muted)">
                                {{ $movement->createdBy->name }}
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center;color:var(--c-muted);padding:24px">
                        Keine Lagerbewegungen gefunden.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $movements->links() }}
@endsection
