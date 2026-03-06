@extends('admin.layout')

@section('title', 'Rechnungen')

@section('content')

<div class="card">
    <div class="card-header">Alle Rechnungen ({{ $invoices->total() }})</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Rechnung-Nr.</th>
                    <th>Bestellung</th>
                    <th>Kunde</th>
                    <th>Status</th>
                    <th class="text-right">Gesamt (brutto)</th>
                    <th>Erstellt</th>
                    <th>Finalisiert</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr>
                    <td>
                        @if($invoice->invoice_number)
                            <code>{{ $invoice->invoice_number }}</code>
                        @else
                            <span class="text-muted">Entwurf #{{ $invoice->id }}</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.orders.show', $invoice->order_id) }}">
                            #{{ $invoice->order_id }}
                        </a>
                    </td>
                    <td>
                        @if($invoice->order?->customer)
                            {{ $invoice->order->customer->first_name }}
                            {{ $invoice->order->customer->last_name }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-{{ $invoice->status }}">
                            {{ $invoice->status }}
                        </span>
                    </td>
                    <td class="text-right">
                        {{ number_format($invoice->total_gross_milli / 1_000_000, 2, ',', '.') }} €
                    </td>
                    <td class="text-muted">{{ $invoice->created_at->format('d.m.Y') }}</td>
                    <td class="text-muted">
                        {{ $invoice->finalized_at?->format('d.m.Y') ?? '—' }}
                    </td>
                    <td>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.orders.invoice', $invoice->order_id) }}"
                               class="btn btn-outline btn-sm">Detail</a>
                            @if($invoice->isFinalized() && $invoice->pdf_path)
                                <a href="{{ route('admin.invoices.download', $invoice) }}"
                                   class="btn btn-outline btn-sm">PDF ↓</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted" style="padding:24px">
                        Keine Rechnungen vorhanden.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $invoices->links() }}

@endsection
