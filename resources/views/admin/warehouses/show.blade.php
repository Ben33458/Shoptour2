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

@if($warehouse->is_pickup_location)
<div class="card" style="margin-bottom:16px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Öffnungszeiten (Abholtermin-Zeitfenster)</span>
    </div>

    @php
        $dayNames = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
        $existingDays = $openingHours->pluck('day_of_week')->toArray();
    @endphp

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Wochentag</th>
                    <th>Von</th>
                    <th>Bis</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($openingHours as $oh)
                <tr>
                    <td>{{ $dayNames[$oh->day_of_week] }}</td>
                    <td>{{ $oh->open_from }}</td>
                    <td>{{ $oh->open_to }}</td>
                    <td>
                        <form method="POST"
                              action="{{ route('admin.warehouses.opening-hours.destroy', [$warehouse, $oh]) }}"
                              onsubmit="return confirm('Öffnungszeiten löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align:center;color:var(--c-muted);padding:16px">
                        Noch keine Öffnungszeiten hinterlegt.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding:16px;border-top:1px solid var(--c-border)">
        <form method="POST" action="{{ route('admin.warehouses.opening-hours.store', $warehouse) }}"
              style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            @csrf
            <div class="form-group" style="margin:0">
                <label style="font-size:.85em">Wochentag</label>
                <select name="day_of_week" class="form-control" required>
                    @foreach($dayNames as $num => $label)
                        @if(!in_array($num, $existingDays))
                            <option value="{{ $num }}">{{ $label }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin:0">
                <label style="font-size:.85em">Von</label>
                <input type="time" name="open_from" class="form-control" required>
            </div>
            <div class="form-group" style="margin:0">
                <label style="font-size:.85em">Bis</label>
                <input type="time" name="open_to" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Hinzufügen</button>
        </form>
        @if(session('success'))
            <div class="hint" style="color:var(--c-success);margin-top:8px">{{ session('success') }}</div>
        @endif
    </div>

    <div style="padding:12px 16px;border-top:1px solid var(--c-border);font-size:.85em;color:var(--c-muted)">
        <strong style="color:var(--c-text)">Gesetzliche Feiertage (nächste 3 Monate) — automatisch gesperrt:</strong>
        <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px">
            @forelse($upcomingHolidays as $h)
                <span style="background:var(--c-bg-subtle,#f5f5f5);border:1px solid var(--c-border);padding:3px 10px;border-radius:4px">
                    {{ \Carbon\Carbon::parse($h->date)->format('d.m.Y') }}
                    ({{ \Carbon\Carbon::parse($h->date)->locale('de')->isoFormat('dd') }})
                    — {{ $h->name }}
                </span>
            @empty
                <span>Keine Feiertage in den nächsten 3 Monaten.</span>
            @endforelse
        </div>
    </div>
</div>
@endif

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
