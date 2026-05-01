@extends('admin.layout')

@section('title', 'Einheit: ' . $inventoryUnit->inventory_number)

@section('actions')
    <a href="{{ route('admin.rental.inventory-units.edit', $inventoryUnit) }}" class="btn btn-primary btn-sm">Bearbeiten</a>
    <a href="{{ route('admin.rental.inventory-units.index') }}" class="btn btn-outline btn-sm">← Übersicht</a>
@endsection

@section('content')

{{-- Details --}}
<div class="card">
    <div class="card-header">Einheitendetails</div>
    <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
            <div class="hint">Inventarnummer</div>
            <div><strong>{{ $inventoryUnit->inventory_number }}</strong></div>
        </div>
        <div>
            <div class="hint">Leihgerät</div>
            <div>
                @if($inventoryUnit->rentalItem)
                    <a href="{{ route('admin.rental.items.show', $inventoryUnit->rentalItem) }}">
                        {{ $inventoryUnit->rentalItem->name }}
                    </a>
                @else
                    —
                @endif
            </div>
        </div>
        <div>
            <div class="hint">Status</div>
            <div>
                @switch($inventoryUnit->status)
                    @case('available')    <span class="badge badge-delivered">verfügbar</span> @break
                    @case('reserved')     <span class="badge">reserviert</span> @break
                    @case('in_use')       <span class="badge badge-pending">im Einsatz</span> @break
                    @case('maintenance')  <span class="badge">Wartung</span> @break
                    @case('defective')    <span class="badge badge-cancelled">defekt</span> @break
                    @case('retired')      <span class="badge badge-cancelled">ausgemustert</span> @break
                    @default {{ $inventoryUnit->status }}
                @endswitch
            </div>
        </div>
        <div>
            <div class="hint">Bevorzugt für Buchungen</div>
            <div>{{ $inventoryUnit->preferred_for_booking ? 'Ja' : 'Nein' }}</div>
        </div>
        @if($inventoryUnit->notes)
        <div style="grid-column:1/-1">
            <div class="hint">Notizen</div>
            <div>{{ $inventoryUnit->notes }}</div>
        </div>
        @endif
    </div>
</div>

{{-- Offene Schadensmeldungen --}}
<div class="card" style="margin-top:16px">
    <div class="card-header">Offene Schadensmeldungen</div>
    @if($inventoryUnit->assetIssues?->count())
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Beschreibung</th>
                    <th>Gemeldet von</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @foreach($inventoryUnit->assetIssues as $issue)
                <tr>
                    <td>{{ $issue->created_at?->format('d.m.Y') }}</td>
                    <td>{{ $issue->description }}</td>
                    <td>{{ $issue->reportedBy?->name ?? '—' }}</td>
                    <td>
                        @if($issue->resolved_at)
                            <span class="badge badge-delivered">behoben</span>
                        @else
                            <span class="badge badge-cancelled">offen</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div style="padding:20px;text-align:center;color:var(--c-muted)">
        Keine offenen Schadensmeldungen.
    </div>
    @endif
</div>

@endsection
