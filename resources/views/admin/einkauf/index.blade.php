@extends('admin.layout')

@section('title', 'Einkauf — Bestellungen')

@section('content')

<div class="card">
    <div class="card-header flex justify-between items-center">
        <span>Einkaufsbestellungen ({{ $purchaseOrders->total() }})</span>
        <div class="flex gap-2">
            <a href="{{ route('admin.einkauf.bestellvorschlaege') }}" class="btn btn-outline btn-sm">Bestellvorschläge</a>
            <a href="{{ route('admin.einkauf.create') }}" class="btn btn-primary btn-sm">+ Neue Bestellung</a>
        </div>
    </div>

    {{-- Status badges --}}
    <div class="p-3 flex gap-2 flex-wrap border-b">
        <a href="{{ route('admin.einkauf.index') }}"
           class="badge {{ !request('status') ? 'badge-primary' : 'badge-outline' }}">
            Alle ({{ $statusCounts->sum() }})
        </a>
        @foreach(['draft' => 'Entwurf', 'sent' => 'Versendet', 'confirmed' => 'Bestätigt', 'partially_received' => 'Teillieferung', 'received' => 'Eingegangen', 'cancelled' => 'Storniert'] as $key => $label)
            @if(($statusCounts[$key] ?? 0) > 0)
                <a href="{{ route('admin.einkauf.index', ['status' => $key]) }}"
                   class="badge {{ request('status') === $key ? 'badge-primary' : 'badge-outline' }}">
                    {{ $label }} ({{ $statusCounts[$key] ?? 0 }})
                </a>
            @endif
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.einkauf.index') }}" class="p-3 flex gap-2 flex-wrap items-end border-b">
        <div>
            <label class="text-xs text-muted">Suche</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="PO-Nummer, Lieferant..."
                   class="input input-sm" style="width: 200px;">
        </div>
        <div>
            <label class="text-xs text-muted">Lieferant</label>
            <select name="supplier_id" class="input input-sm">
                <option value="">Alle</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" {{ request('supplier_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-muted">Von</label>
            <input type="date" name="from" value="{{ request('from') }}" class="input input-sm">
        </div>
        <div>
            <label class="text-xs text-muted">Bis</label>
            <input type="date" name="to" value="{{ request('to') }}" class="input input-sm">
        </div>
        <button type="submit" class="btn btn-sm btn-outline">Filtern</button>
        @if(request()->hasAny(['search', 'supplier_id', 'from', 'to', 'status']))
            <a href="{{ route('admin.einkauf.index') }}" class="btn btn-sm btn-ghost">Zurücksetzen</a>
        @endif
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>PO-Nummer</th>
                    <th>Lieferant</th>
                    <th>Bestelldatum</th>
                    <th>Erw. Lieferung</th>
                    <th class="text-right">Betrag (netto)</th>
                    <th>Status</th>
                    <th>Positionen</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($purchaseOrders as $po)
                @php
                    $isOverdue = $po->expected_at && $po->expected_at->isPast()
                        && in_array($po->status, ['sent', 'confirmed', 'partially_received']);
                @endphp
                <tr class="{{ $isOverdue ? 'row-warning' : '' }}">
                    <td>
                        <a href="{{ route('admin.einkauf.show', $po) }}" class="font-mono font-bold">
                            {{ $po->po_number ?? "#{$po->id}" }}
                        </a>
                    </td>
                    <td>{{ $po->supplier->name ?? '—' }}</td>
                    <td class="text-muted">{{ $po->ordered_at?->format('d.m.Y') ?? '—' }}</td>
                    <td class="{{ $isOverdue ? 'text-danger font-bold' : 'text-muted' }}">
                        {{ $po->expected_at?->format('d.m.Y') ?? '—' }}
                        @if($isOverdue) (überfällig) @endif
                    </td>
                    <td class="text-right">{{ number_format($po->total_milli / 1_000_000, 2, ',', '.') }} &euro;</td>
                    <td>
                        @php
                            $badgeClass = match($po->status) {
                                'draft' => 'badge-secondary',
                                'sent' => 'badge-info',
                                'confirmed' => 'badge-info',
                                'partially_received' => 'badge-warning',
                                'received' => 'badge-success',
                                'cancelled' => 'badge-danger',
                                default => '',
                            };
                            $statusLabel = match($po->status) {
                                'draft' => 'Entwurf',
                                'sent' => 'Versendet',
                                'confirmed' => 'Bestätigt',
                                'partially_received' => 'Teillieferung',
                                'received' => 'Eingegangen',
                                'cancelled' => 'Storniert',
                                default => $po->status,
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                    </td>
                    <td class="text-center text-muted">{{ $po->items_count ?? $po->items->count() }}</td>
                    <td>
                        <a href="{{ route('admin.einkauf.show', $po) }}" class="btn btn-xs btn-outline">Details</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted p-4">Keine Bestellungen gefunden.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-3">
        {{ $purchaseOrders->links() }}
    </div>
</div>

@endsection
