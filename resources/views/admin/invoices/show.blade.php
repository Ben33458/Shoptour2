@extends('admin.layout')

@section('title', 'Rechnung — Bestellung #' . $order->id)

@section('actions')
    <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline btn-sm">← Bestellung</a>
    <a href="{{ route('admin.orders.closeout', $order) }}" class="btn btn-outline btn-sm">Abschluss</a>
@endsection

@section('content')

{{-- ── Invoice status banner ── --}}
@if($invoice && $invoice->isFinalized())
    <div class="alert alert-success">
        ✅ Rechnung <strong>{{ $invoice->invoice_number }}</strong> wurde am
        {{ $invoice->finalized_at->format('d.m.Y \u\m H:i') }} finalisiert.
        <a href="{{ route('admin.invoices.download', $invoice) }}" class="btn btn-success btn-sm"
           style="margin-left:12px">PDF herunterladen ↓</a>
    </div>
@endif

{{-- ── Customer & order info ── --}}
<div class="meta-grid" style="margin-bottom:20px">
    <div class="meta-item">
        <label>Bestellung</label>
        <div class="val">#{{ $order->id }}</div>
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
        <label>Rechnungs-Nr.</label>
        <div class="val">
            @if($invoice?->invoice_number)
                <code>{{ $invoice->invoice_number }}</code>
            @else
                <span class="text-muted">—</span>
            @endif
        </div>
    </div>
    <div class="meta-item">
        <label>Status</label>
        <div class="val">
            @if($invoice)
                <span class="badge badge-{{ $invoice->status }}">{{ $invoice->status }}</span>
            @else
                <span class="text-muted">Keine Rechnung</span>
            @endif
        </div>
    </div>
</div>

{{-- ── Actions ── --}}
@if(! $invoice || ! $invoice->isFinalized())
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        {{-- Draft / Recalculate --}}
        <form method="POST" action="{{ route('admin.orders.invoice.draft', $order) }}"
              style="display:inline">
            @csrf
            <button type="submit" class="btn btn-outline">
                🔄 Entwurf neu berechnen
            </button>
        </form>

        {{-- Finalize --}}
        @if($invoice)
            <form method="POST" action="{{ route('admin.invoices.finalize', $invoice) }}"
                  style="display:inline"
                  onsubmit="return confirm('Rechnung wirklich finalisieren? Dies kann nicht rückgängig gemacht werden.')">
                @csrf
                <button type="submit" class="btn btn-success">
                    ✅ Finalisieren &amp; PDF erzeugen
                </button>
            </form>
        @endif
    </div>
</div>
@endif

{{-- ── Invoice line items ── --}}
@if($invoice && $invoice->items->isNotEmpty())
<div class="card">
    <div class="card-header">Rechnungspositionen</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Typ</th>
                    <th>Beschreibung</th>
                    <th class="text-right">Menge</th>
                    <th class="text-right">EP (netto)</th>
                    <th class="text-right">EP (brutto)</th>
                    <th class="text-right">MwSt.%</th>
                    <th class="text-right">Gesamt (brutto)</th>
                </tr>
            </thead>
            <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>
                        <span class="badge badge-draft" style="font-size:10px">
                            {{ strtoupper($item->line_type) }}
                        </span>
                    </td>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ number_format($item->qty, 0, ',', '.') }}</td>
                    <td class="text-right">
                        {{ number_format($item->unit_price_net_milli / 1_000_000, 2, ',', '.') }} €
                    </td>
                    <td class="text-right">
                        {{ number_format($item->unit_price_gross_milli / 1_000_000, 2, ',', '.') }} €
                    </td>
                    <td class="text-right text-muted">
                        {{ number_format($item->tax_rate_basis_points / 10000, 0) }}%
                    </td>
                    <td class="text-right">
                        <strong>
                            {{ number_format($item->line_total_gross_milli / 1_000_000, 2, ',', '.') }} €
                        </strong>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totals box --}}
    <div class="card-body">
        <div class="totals-box">
            <div class="row">
                <span>Nettobetrag</span>
                <strong>{{ number_format($invoice->total_net_milli / 1_000_000, 2, ',', '.') }} €</strong>
            </div>
            <div class="row">
                <span>MwSt.</span>
                <span>{{ number_format($invoice->total_tax_milli / 1_000_000, 2, ',', '.') }} €</span>
            </div>
            @if($invoice->total_adjustments_milli !== 0)
            <div class="row">
                <span>Anpassungen</span>
                <span>{{ number_format($invoice->total_adjustments_milli / 1_000_000, 2, ',', '.') }} €</span>
            </div>
            @endif
            @if($invoice->total_deposit_milli > 0)
            <div class="row">
                <span>Pfand (brutto)</span>
                <span>{{ number_format($invoice->total_deposit_milli / 1_000_000, 2, ',', '.') }} €</span>
            </div>
            @endif
            <div class="row">
                <span>GESAMTBETRAG</span>
                <strong>{{ number_format($invoice->total_gross_milli / 1_000_000, 2, ',', '.') }} €</strong>
            </div>
        </div>
    </div>
</div>
@elseif(! $invoice)
    <div class="alert alert-info">
        Noch keine Rechnung vorhanden. Klicken Sie auf
        <strong>„Entwurf neu berechnen"</strong>, um eine zu erstellen.
    </div>
@endif

@endsection
