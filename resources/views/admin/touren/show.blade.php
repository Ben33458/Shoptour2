@extends('admin.layout')

@section('title', 'Tour ' . $tour->tour_date->format('d.m.Y'))

@section('content')

@php
    $statusLabels = [
        'planned'     => ['label' => 'Geplant',   'color' => '#64748b'],
        'in_progress' => ['label' => 'Unterwegs',  'color' => '#d97706'],
        'done'        => ['label' => 'Fertig',     'color' => '#16a34a'],
        'cancelled'   => ['label' => 'Storniert',  'color' => '#dc2626'],
    ];
    $sl      = $statusLabels[$tour->status] ?? ['label' => $tour->status, 'color' => '#64748b'];
    $totalVpe = $tour->stops->sum(fn($s) =>
        $s->order?->ninox_item_count
        ?? $s->order?->items?->sum('qty')
        ?? 0
    );
    $totalGross = $tour->stops->sum(fn($s) => $s->order?->total_gross_milli ?? 0);
@endphp

{{-- Tour header card --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>
            Tour {{ $tour->tour_date->format('d.m.Y') }}
            @if($tour->regularDeliveryTour)
                &mdash; {{ $tour->regularDeliveryTour->name }}
            @endif
        </span>
        <span style="background:{{ $sl['color'] }}1a;color:{{ $sl['color'] }};border-radius:12px;padding:3px 14px;font-size:.85rem;font-weight:600">
            {{ $sl['label'] }}
        </span>
    </div>
    <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px">
        <div>
            <div style="font-size:.75rem;color:var(--c-muted);margin-bottom:2px">Stopps</div>
            <div style="font-size:1.3rem;font-weight:700">{{ $tour->stops->count() }}</div>
        </div>
        <div>
            <div style="font-size:.75rem;color:var(--c-muted);margin-bottom:2px">Gesamt VPE</div>
            <div style="font-size:1.3rem;font-weight:700">{{ $totalVpe ?: '—' }}</div>
        </div>
        <div>
            <div style="font-size:.75rem;color:var(--c-muted);margin-bottom:2px">Gesamtbetrag brutto</div>
            <div style="font-size:1.1rem;font-weight:600">{{ number_format($totalGross / 1_000_000, 2, ',', '.') }} €</div>
        </div>
        @if($tour->started_at)
        <div>
            <div style="font-size:.75rem;color:var(--c-muted);margin-bottom:2px">Gestartet</div>
            <div style="font-size:.95rem">{{ $tour->started_at->format('H:i') }} Uhr</div>
        </div>
        @endif
        @if($tour->ended_at)
        <div>
            <div style="font-size:.75rem;color:var(--c-muted);margin-bottom:2px">Beendet</div>
            <div style="font-size:.95rem">{{ $tour->ended_at->format('H:i') }} Uhr</div>
        </div>
        @endif
    </div>
</div>

{{-- Fahrer zuweisen --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header">Fahrerzuweisung</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.touren.update-driver', $tour) }}" style="display:flex;gap:12px;align-items:flex-end">
            @csrf
            @method('PATCH')
            <div class="form-group" style="flex:1;margin:0">
                <label style="font-size:.82rem;color:var(--c-muted);display:block;margin-bottom:4px">Fahrer</label>
                <select name="driver_employee_id" style="width:100%">
                    <option value="">— Kein Fahrer —</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" @selected($tour->driver_employee_id == $emp->id)>
                            {{ $emp->first_name }} {{ $emp->last_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Speichern</button>
        </form>
        @if($driver)
            <p style="margin-top:8px;font-size:.82rem;color:var(--c-muted)">
                Aktuell: <strong>{{ $driver->first_name }} {{ $driver->last_name }}</strong>
                &nbsp;·&nbsp;
                <a href="{{ route('admin.driver-tokens.index') }}" style="color:var(--c-primary)">API-Token verwalten</a>
            </p>
        @endif
    </div>
</div>

{{-- Stopps --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header">Stopps ({{ $tour->stops->count() }})</div>
    <div class="table-wrap">
        <table id="stops-table">
            <thead>
                <tr>
                    <th style="width:48px">#</th>
                    <th>Kunde</th>
                    <th>Adresse</th>
                    <th style="text-align:center">VPE</th>
                    <th style="text-align:right">Betrag brutto</th>
                    <th style="text-align:center">Status</th>
                    <th data-no-sort data-no-filter data-no-resize data-no-reorder></th>
                </tr>
            </thead>
            <tbody>
                @php
                    $stopStatusLabels = [
                        'open'     => ['label' => 'Offen',      'color' => '#64748b'],
                        'arrived'  => ['label' => 'Vor Ort',    'color' => '#d97706'],
                        'finished' => ['label' => 'Geliefert',  'color' => '#16a34a'],
                        'skipped'  => ['label' => 'Übersprungen','color' => '#dc2626'],
                    ];
                @endphp
                @forelse($tour->stops as $stop)
                    @php
                        $customer = $stop->order?->customer;
                        $vpe      = $stop->order?->ninox_item_count
                            ?? ($stop->order?->items?->isNotEmpty()
                                ? $stop->order->items->sum('qty')
                                : null);
                        $gross    = $stop->order?->total_gross_milli ?? 0;
                        $addr     = $stop->order?->deliveryAddress?->oneLiner()
                            ?? $customer?->delivery_address_text ?? '—';
                        $ssl = $stopStatusLabels[$stop->status] ?? ['label' => $stop->status, 'color' => '#64748b'];
                    @endphp
                    <tr>
                        <td style="font-weight:600;color:var(--c-muted)">{{ $stop->stop_index }}</td>
                        <td>
                            @if($customer)
                                <a href="{{ route('admin.customers.show', $customer) }}" style="color:var(--c-primary)">
                                    {{ $customer->displayName() }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td style="font-size:.82rem;color:var(--c-muted)">{{ $addr }}</td>
                        <td style="text-align:center">{{ $vpe ?? '—' }}</td>
                        <td style="text-align:right">{{ number_format($gross / 1_000_000, 2, ',', '.') }} €</td>
                        <td style="text-align:center">
                            <span style="background:{{ $ssl['color'] }}1a;color:{{ $ssl['color'] }};border-radius:12px;padding:2px 10px;font-size:.78rem;font-weight:600">
                                {{ $ssl['label'] }}
                            </span>
                        </td>
                        <td>
                            @if($stop->order)
                                <a href="{{ route('admin.orders.show', $stop->order) }}" class="btn btn-sm btn-outline">Bestellung</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center;color:var(--c-muted);padding:24px">Keine Stopps.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Aktionen + Navigation --}}
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    @if($prevTour)
        <a href="{{ route('admin.touren.show', $prevTour) }}" class="btn btn-outline"
           title="{{ $prevTour->tour_date->format('d.m.Y') }}">
            ‹ {{ $prevTour->regularDeliveryTour?->name ?? $prevTour->tour_date->format('d.m.Y') }}
        </a>
    @else
        <span class="btn btn-outline" style="opacity:.35;cursor:default">‹</span>
    @endif

    @if($nextTour)
        <a href="{{ route('admin.touren.show', $nextTour) }}" class="btn btn-outline"
           title="{{ $nextTour->tour_date->format('d.m.Y') }}">
            {{ $nextTour->regularDeliveryTour?->name ?? $nextTour->tour_date->format('d.m.Y') }} ›
        </a>
    @else
        <span class="btn btn-outline" style="opacity:.35;cursor:default">›</span>
    @endif

    <a href="{{ route('admin.touren.index', ['date' => $tour->tour_date->toDateString()]) }}" class="btn btn-outline" style="margin-left:8px">
        ↑ Liste
    </a>

    @if($tour->ninox_id)
        <form method="POST" action="{{ route('admin.touren.ninox-sync', $tour) }}">
            @csrf
            <button type="submit" class="btn btn-outline">↻ Von Ninox aktualisieren</button>
        </form>
    @endif

    @if($tour->status === 'planned')
        <form method="POST" action="{{ route('admin.touren.destroy', $tour) }}"
              onsubmit="return confirm('Tour wirklich löschen?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline" style="color:#dc2626;border-color:#dc2626">
                Tour löschen
            </button>
        </form>
    @endif
</div>

@endsection

@push('scripts')
<script>
new AdminTable('stops-table', { tableKey: 'tour-stops-{{ $tour->id }}' });
</script>
@endpush
