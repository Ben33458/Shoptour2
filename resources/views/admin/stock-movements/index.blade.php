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
        <div style="display:flex;gap:8px;align-items:flex-end;padding-bottom:0">
            <button type="submit" class="btn btn-primary">Filtern</button>
            <a href="{{ route('admin.stock-movements.index') }}" class="btn btn-outline">Zurücksetzen</a>
        </div>
    </div>
</form>

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
                        {{ $movement->created_at->format('d.m.Y H:i') }}
                    </td>
                    <td>
                        <span class="badge">{{ $movement->type_label }}</span>
                    </td>
                    <td>
                        <code style="font-size:.8em">{{ $movement->product->artikelnummer ?? '—' }}</code>
                        <span style="font-size:.85em">{{ $movement->product->produktname ?? '' }}</span>
                    </td>
                    <td>{{ $movement->warehouse->name ?? '—' }}</td>
                    <td style="text-align:right;font-weight:600">
                        <span style="color: {{ $movement->quantity_delta >= 0 ? 'var(--c-success, #16a34a)' : 'var(--c-danger)' }}">
                            {{ $movement->quantity_delta >= 0 ? '+' : '' }}{{ number_format($movement->quantity_delta, 2, ',', '.') }}
                        </span>
                    </td>
                    <td style="font-size:.85em">
                        @if($movement->reference_type)
                            {{ $movement->reference_type }} #{{ $movement->reference_id }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td style="font-size:.85em;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        {{ $movement->note ?? '—' }}
                    </td>
                    <td style="font-size:.85em">
                        {{ $movement->createdBy?->name ?? '—' }}
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
