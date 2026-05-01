@extends('admin.layout')

@section('title', 'Rückgabeschein')

@section('actions')
    <a href="{{ route('admin.rental.return-slips.index') }}" class="btn btn-outline btn-sm">← Übersicht</a>
@endsection

@section('content')

{{-- Kopfdaten --}}
<div class="card">
    <div class="card-header">Schein-Informationen</div>
    <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
            <div class="hint">Bestellung</div>
            <div>
                @if($slip->order)
                    <a href="{{ route('admin.orders.show', $slip->order) }}">
                        {{ $slip->order->order_number ?? '#' . $slip->order->id }}
                    </a>
                @else
                    —
                @endif
            </div>
        </div>
        <div>
            <div class="hint">Kunde</div>
            <div>{{ $slip->order?->customer?->name ?? '—' }}</div>
        </div>
        <div>
            <div class="hint">Fahrer</div>
            <div>{{ $slip->driver?->name ?? '—' }}</div>
        </div>
        <div>
            <div class="hint">Rückgabe am</div>
            <div>{{ $slip->returned_at?->format('d.m.Y H:i') ?? '—' }}</div>
        </div>
        <div>
            <div class="hint">Status</div>
            <div>
                @switch($slip->status)
                    @case('open')     <span class="badge badge-pending">offen</span> @break
                    @case('reviewed') <span class="badge">geprüft</span> @break
                    @case('charged')  <span class="badge badge-delivered">verbucht</span> @break
                    @default <span class="badge">{{ $slip->status }}</span>
                @endswitch
            </div>
        </div>
    </div>
</div>

{{-- Positionen --}}
<div class="card" style="margin-top:16px">
    <div class="card-header">Rückgabepositionen</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Leihgerät</th>
                    <th style="text-align:center">Menge</th>
                    <th>Reinigungsstatus</th>
                    <th>Schadensstatus</th>
                    <th style="text-align:right">Vorgeschlagene Gebühr</th>
                    <th style="text-align:right">Manuelle Gebühr</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($slip->items as $slipItem)
                <tr>
                    <td>{{ $slipItem->rentalItem?->name ?? '—' }}</td>
                    <td style="text-align:center">{{ $slipItem->quantity }}</td>
                    <td>{{ $slipItem->clean_status ?? '—' }}</td>
                    <td>{{ $slipItem->damage_status ?? '—' }}</td>
                    <td style="text-align:right">
                        @if($slipItem->suggested_charge_milli !== null)
                            {{ number_format($slipItem->suggested_charge_milli / 1000000, 2) }} €
                        @else
                            —
                        @endif
                    </td>
                    <td style="text-align:right">
                        <form method="POST"
                              action="{{ route('admin.rental.return-slips.items.charge', $slipItem) }}"
                              style="display:flex;gap:6px;justify-content:flex-end;align-items:center">
                            @csrf @method('PUT')
                            <input type="number" name="manual_charge_euro"
                                   value="{{ $slipItem->manual_charge_milli !== null ? number_format($slipItem->manual_charge_milli / 1000000, 2, '.', '') : '' }}"
                                   class="form-control" step="0.01" min="0"
                                   style="width:100px;text-align:right"
                                   placeholder="0,00">
                            <button type="submit" class="btn btn-outline btn-sm">OK</button>
                        </form>
                    </td>
                    <td></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center;color:var(--c-muted);padding:16px">
                        Keine Positionen vorhanden.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Aktionen --}}
<div style="display:flex;gap:8px;margin-top:16px">
    @if($slip->status === 'open')
    <form method="POST" action="{{ route('admin.rental.return-slips.mark-reviewed', $slip) }}">
        @csrf
        <button type="submit" class="btn btn-primary"
                onclick="return confirm('Schein als geprüft markieren?')">
            Als geprüft markieren
        </button>
    </form>
    @endif

    @if($slip->status === 'reviewed')
    <form method="POST" action="{{ route('admin.rental.return-slips.mark-charged', $slip) }}">
        @csrf
        <button type="submit" class="btn btn-primary"
                onclick="return confirm('Schein als verbucht markieren?')">
            Als verbucht markieren
        </button>
    </form>
    @endif

    <a href="{{ route('admin.rental.return-slips.index') }}" class="btn btn-outline">Zurück</a>
</div>

@endsection
