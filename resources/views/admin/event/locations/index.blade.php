@extends('admin.layout')

@section('title', 'Veranstaltungsorte')

@section('actions')
    <a href="{{ route('admin.event.locations.create') }}" class="btn btn-primary btn-sm">+ Neuer Ort</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header">Veranstaltungsorte ({{ $locations->total() }})</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Adresse</th>
                    <th>PLZ / Ort</th>
                    <th>Koordinaten</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($locations as $loc)
                <tr>
                    <td><strong>{{ $loc->name }}</strong></td>
                    <td>{{ $loc->street }}</td>
                    <td>{{ $loc->zip }} {{ $loc->city }}</td>
                    <td>
                        @if($loc->geo_lat && $loc->geo_lng)
                            {{ number_format($loc->geo_lat, 5) }}, {{ number_format($loc->geo_lng, 5) }}
                        @else
                            <span style="color:var(--c-muted)">—</span>
                        @endif
                    </td>
                    <td>
                        @if($loc->active)
                            <span class="badge" style="background:var(--c-success-muted,#d1fae5);color:var(--c-success,#065f46)">Aktiv</span>
                        @else
                            <span class="badge" style="color:var(--c-muted)">Inaktiv</span>
                        @endif
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.event.locations.edit', $loc) }}" class="btn btn-outline btn-sm">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.event.locations.destroy', $loc) }}"
                              style="display:inline"
                              onsubmit="return confirm('Ort \"{{ addslashes($loc->name) }}\" löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--c-danger)">Löschen</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:var(--c-muted);padding:24px">
                        Noch keine Orte angelegt.
                        <a href="{{ route('admin.event.locations.create') }}" class="btn btn-primary btn-sm" style="margin-left:12px">+ Ersten Ort anlegen</a>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $locations->links() }}
@endsection
