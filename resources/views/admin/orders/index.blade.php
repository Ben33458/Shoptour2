@extends('admin.layout')

@section('title', 'Bestellungen')

@section('content')

{{-- ── Filter bar ── --}}
<form method="GET" action="{{ route('admin.orders.index') }}">
    <div class="filter-bar">
        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="">— Alle —</option>
                @foreach($statuses as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>
                        {{ ucfirst($s) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="flex:2">
            <label>Suche (Kunde / Bestell-ID)</label>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Name, Kundennummer oder ID…">
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;padding-bottom:0">
            <button type="submit" class="btn btn-primary">Filtern</button>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline">Zurücksetzen</a>
        </div>
    </div>
</form>

{{-- ── Orders table ── --}}
<div class="card">
    <div class="card-header">
        Bestellungen ({{ $orders->total() }})
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Kunde</th>
                    <th>Status</th>
                    <th>Lieferdatum</th>
                    <th class="text-right">Gesamt (brutto)</th>
                    <th>Erstellt</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($orders as $order)
                <tr>
                    <td><code>{{ $order->id }}</code></td>
                    <td>
                        @if($order->customer)
                            <strong>{{ $order->customer->first_name }} {{ $order->customer->last_name }}</strong><br>
                            <span class="text-muted">{{ $order->customer->customer_number }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-{{ $order->status }}">{{ $order->status }}</span>
                    </td>
                    <td>{{ $order->delivery_date?->format('d.m.Y') ?? '—' }}</td>
                    <td class="text-right">
                        {{ number_format($order->total_gross_milli / 1_000_000, 2, ',', '.') }} €
                    </td>
                    <td class="text-muted">{{ $order->created_at->format('d.m.Y') }}</td>
                    <td>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.orders.show', $order) }}"
                               class="btn btn-outline btn-sm">Detail</a>
                            <a href="{{ route('admin.orders.invoice', $order) }}"
                               class="btn btn-outline btn-sm">Rechnung</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted" style="padding:24px">
                        Keine Bestellungen gefunden.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $orders->links() }}

@endsection
