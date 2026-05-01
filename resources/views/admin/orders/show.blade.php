@extends('admin.layout')

@section('title', 'Bestellung #' . $order->id)

@section('actions')
    <a href="{{ route('admin.orders.index') }}" class="btn btn-outline btn-sm">← Zurück</a>
    <a href="{{ route('admin.orders.edit', $order) }}" class="btn btn-outline btn-sm">✏ Bearbeiten</a>
    <a href="{{ route('admin.orders.closeout', $order) }}" class="btn btn-outline btn-sm">Abschluss</a>
    <a href="{{ route('admin.orders.invoice', $order) }}" class="btn btn-primary btn-sm">Rechnung</a>
@endsection

@section('content')

{{-- ── Order meta ── --}}
<div class="meta-grid" style="margin-bottom:20px">
    <div class="meta-item">
        <label>Bestell-ID</label>
        <div class="val">#{{ $order->id }}</div>
    </div>
    <div class="meta-item">
        <label>Status</label>
        <div class="val">
            <span class="badge badge-{{ $order->status }}">{{ $order->status }}</span>
        </div>
    </div>
    <div class="meta-item">
        <label>Kunde</label>
        <div class="val">
            {{ $order->customer?->first_name }} {{ $order->customer?->last_name }}<br>
            <span class="text-muted" style="font-size:12px">
                {{ $order->customer?->customer_number }}
            </span>
        </div>
    </div>
    <div class="meta-item">
        <label>Lieferdatum</label>
        <div class="val">{{ $order->delivery_date?->format('d.m.Y') ?? '—' }}</div>
    </div>
    <div class="meta-item">
        <label>Erstellt</label>
        <div class="val">{{ $order->created_at->format('d.m.Y H:i') }}</div>
    </div>
    <div class="meta-item">
        <label>Rechnung</label>
        <div class="val">
            @if($invoice)
                <span class="badge badge-{{ $invoice->status }}">{{ $invoice->status }}</span>
                @if($invoice->invoice_number)
                    <code>{{ $invoice->invoice_number }}</code>
                @endif
            @else
                <span class="text-muted">—</span>
            @endif
        </div>
    </div>
</div>

{{-- ── Jugendschutz-Hinweis ── --}}
@if($minAge > 0)
<div class="card" style="margin-bottom:16px;border-left:4px solid var(--c-danger,#dc2626)">
    <div style="display:flex;align-items:center;gap:10px;padding:12px 16px">
        <svg style="width:20px;height:20px;flex-shrink:0;color:var(--c-danger,#dc2626)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <div>
            <strong>Jugendschutz — Abgabe ab {{ $minAge }} Jahren</strong><br>
            <span style="font-size:13px">{{ \App\Services\Catalog\JugendschutzService::deliveryNote($minAge) }}</span>
        </div>
    </div>
</div>
@endif

{{-- ── Delivery stop info ── --}}
@if($stop)
<div class="card" style="margin-bottom:16px">
    <div class="card-header">Tourstopp</div>
    <div class="card-body">
        <div class="flex gap-2 items-center">
            <span>Status: <strong>{{ $stop->status }}</strong></span>
            @if($stop->arrived_at)
                <span class="text-muted">· Ankunft: {{ $stop->arrived_at->format('H:i') }}</span>
            @endif
            @if($stop->finished_at)
                <span class="text-muted">· Abgeschlossen: {{ $stop->finished_at->format('H:i') }}</span>
            @endif
        </div>
    </div>
</div>
@endif

{{-- ── Order items ── --}}
<div class="card">
    <div class="card-header">Positionen</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Artikel-Nr.</th>
                    <th>Bezeichnung</th>
                    <th class="text-right">Bestellt</th>
                    <th class="text-right">Geliefert</th>
                    <th class="text-right">Nicht geliefert</th>
                    <th>Grund (ND)</th>
                    <th class="text-right">EP (brutto)</th>
                    <th class="text-right">Gesamt (brutto)</th>
                </tr>
            </thead>
            <tbody>
            @foreach($itemDetails as $detail)
                @php $item = $detail['item']; @endphp
                <tr>
                    <td><code>{{ $item->artikelnummer_snapshot }}</code></td>
                    <td>{{ $item->product_name_snapshot }}</td>
                    <td class="text-right">{{ $detail['ordered_qty'] }}</td>
                    <td class="text-right">
                        @if($detail['delivered_qty'] !== null)
                            {{ $detail['delivered_qty'] }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($detail['not_delivered_qty'])
                            <span style="color:var(--c-danger)">{{ $detail['not_delivered_qty'] }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-muted" style="font-size:12px">
                        {{ $detail['nd_reason'] ?? '' }}
                    </td>
                    <td class="text-right">
                        {{ number_format($item->unit_price_gross_milli / 1_000_000, 2, ',', '.') }} €
                    </td>
                    <td class="text-right">
                        {{ number_format(($item->unit_price_gross_milli * $item->qty) / 1_000_000, 2, ',', '.') }} €
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body text-right">
        <strong>Gesamtbetrag brutto:</strong>
        {{ number_format($order->total_gross_milli / 1_000_000, 2, ',', '.') }} €
        @if($order->total_pfand_brutto_milli > 0)
            &nbsp;+&nbsp;
            <span class="text-muted">
                {{ number_format($order->total_pfand_brutto_milli / 1_000_000, 2, ',', '.') }} € Pfand
            </span>
        @endif
    </div>
</div>

@endsection
