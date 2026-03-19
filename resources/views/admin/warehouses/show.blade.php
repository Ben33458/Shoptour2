@extends('admin.layout')

@section('title', 'Lagerort: ' . $warehouse->name)

@section('actions')
    <a href="{{ route('admin.warehouses.index') }}" class="btn btn-outline btn-sm">← Alle Lagerorte</a>
@endsection

@section('content')
<div class="card" style="margin-bottom:16px;padding:14px 16px">
    <div style="display:flex;gap:24px;flex-wrap:wrap">
        <div><strong>Name:</strong> {{ $warehouse->name }}</div>
        <div><strong>Standort:</strong> {{ $warehouse->location ?? '—' }}</div>
        <div><strong>Abholort:</strong> {{ $warehouse->is_pickup_location ? 'Ja' : 'Nein' }}</div>
        <div><strong>Status:</strong>
            @if($warehouse->active)
                <span class="badge badge-delivered">aktiv</span>
            @else
                <span class="badge badge-cancelled">inaktiv</span>
            @endif
        </div>
    </div>
</div>

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
                    <th style="text-align:right">Gesamt</th>
                    <th style="text-align:right">Reserviert</th>
                    <th style="text-align:right">Verfügbar</th>
                    <th style="text-align:right">Letzte Änderung</th>
                </tr>
            </thead>
            <tbody>
            @forelse($stocks as $stock)
                <tr>
                    <td><code>{{ $stock->product->artikelnummer ?? '—' }}</code></td>
                    <td>{{ $stock->product->produktname ?? '—' }}</td>
                    <td style="text-align:right">{{ number_format($stock->quantity, 2, ',', '.') }}</td>
                    <td style="text-align:right">{{ number_format($stock->reserved_quantity, 2, ',', '.') }}</td>
                    <td style="text-align:right">
                        @php $avail = $stock->quantity - $stock->reserved_quantity; @endphp
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
                    <td colspan="6" style="text-align:center;color:var(--c-muted);padding:24px">
                        Keine Bestände für diesen Lagerort.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $stocks->links() }}
@endsection
