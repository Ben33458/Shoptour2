@extends('admin.layout')

@section('title', 'Bestände')

@section('content')

{{-- ── Filter ── --}}
<form method="GET" action="{{ route('admin.stock.index') }}">
    <div class="filter-bar">
        <div class="form-group" style="flex:1">
            <label>Lagerort</label>
            <select name="warehouse_id">
                <option value="">Alle Lagerorte</option>
                @foreach($warehouses as $id => $name)
                    <option value="{{ $id }}" @selected($warehouseId == $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;padding-bottom:0">
            <button type="submit" class="btn btn-primary">Filtern</button>
            <a href="{{ route('admin.stock.index') }}" class="btn btn-outline">Zurücksetzen</a>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header">
        Produktbestände ({{ $stocks->total() }})
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Artikelnummer</th>
                    <th>Produkt</th>
                    <th>Lagerort</th>
                    <th style="text-align:right">Gesamt</th>
                    <th style="text-align:right">Reserviert</th>
                    <th style="text-align:right">Verfügbar</th>
                    <th style="text-align:right">Stand</th>
                </tr>
            </thead>
            <tbody>
            @forelse($stocks as $stock)
                @php $avail = $stock->quantity - $stock->reserved_quantity; @endphp
                <tr>
                    <td><code>{{ $stock->product->artikelnummer ?? '—' }}</code></td>
                    <td>{{ $stock->product->produktname ?? '—' }}</td>
                    <td>{{ $stock->warehouse->name ?? '—' }}</td>
                    <td style="text-align:right">{{ number_format($stock->quantity, 2, ',', '.') }}</td>
                    <td style="text-align:right">{{ number_format($stock->reserved_quantity, 2, ',', '.') }}</td>
                    <td style="text-align:right">
                        <span style="color: {{ $avail < 0 ? 'var(--c-danger)' : 'inherit' }}">
                            {{ number_format($avail, 2, ',', '.') }}
                        </span>
                    </td>
                    <td style="text-align:right;color:var(--c-muted);font-size:.85em">
                        {{ $stock->updated_at->format('d.m.Y H:i') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center;color:var(--c-muted);padding:24px">
                        Keine Bestände gefunden.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $stocks->links() }}
@endsection
