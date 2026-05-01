@extends('admin.layout')

@section('title', 'Lieferrückgabe')

@section('actions')
    <a href="{{ route('admin.rental.delivery-returns.index') }}" class="btn btn-outline btn-sm">← Übersicht</a>
@endsection

@section('content')

{{-- Kopfdaten --}}
<div class="card">
    <div class="card-header">Rückgabe-Informationen</div>
    <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
            <div class="hint">Kunde</div>
            <div>
                @if($deliveryReturn->customer)
                    <a href="{{ route('admin.customers.show', $deliveryReturn->customer) }}">
                        {{ $deliveryReturn->customer->name }}
                    </a>
                @else
                    —
                @endif
            </div>
        </div>
        <div>
            <div class="hint">Fahrer</div>
            <div>{{ $deliveryReturn->driver?->name ?? '—' }}</div>
        </div>
        <div>
            <div class="hint">Rückgabe am</div>
            <div>{{ $deliveryReturn->returned_at?->format('d.m.Y H:i') ?? '—' }}</div>
        </div>
        <div>
            <div class="hint">Rückgabeart</div>
            <div>
                @switch($deliveryReturn->return_type)
                    @case('full')    <span class="badge badge-delivered">Vollständig</span> @break
                    @case('partial') <span class="badge badge-pending">Teilrückgabe</span> @break
                    @case('damaged') <span class="badge badge-cancelled">Mit Schäden</span> @break
                    @default <span class="badge">{{ $deliveryReturn->return_type }}</span>
                @endswitch
            </div>
        </div>
        @if($deliveryReturn->notes)
        <div style="grid-column:1/-1">
            <div class="hint">Notizen</div>
            <div>{{ $deliveryReturn->notes }}</div>
        </div>
        @endif
    </div>
</div>

{{-- Positionen --}}
<div class="card" style="margin-top:16px">
    <div class="card-header">Rückgabepositionen</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Artikel</th>
                    <th style="text-align:center">Menge</th>
                    <th>MHD</th>
                    <th>Erzeugte Gebührenposition</th>
                </tr>
            </thead>
            <tbody>
            @forelse($deliveryReturn->items as $returnItem)
                <tr>
                    <td>{{ $returnItem->rentalItem?->name ?? $returnItem->article_name ?? '—' }}</td>
                    <td style="text-align:center">{{ $returnItem->quantity }}</td>
                    <td>
                        @if($returnItem->best_before_date)
                            {{ \Carbon\Carbon::parse($returnItem->best_before_date)->format('d.m.Y') }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if($returnItem->generatedFeeArticle)
                            <span class="badge">{{ $returnItem->generatedFeeArticle->name }}</span>
                            @if($returnItem->generatedFeeArticle->amount_milli !== null)
                                — {{ number_format($returnItem->generatedFeeArticle->amount_milli / 1000000, 2) }} €
                            @endif
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align:center;color:var(--c-muted);padding:16px">
                        Keine Positionen vorhanden.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
