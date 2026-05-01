@extends('admin.layout')

@section('title', 'Fahrzeuge')

@section('actions')
    <a href="{{ route('admin.vehicles.create') }}" class="btn btn-primary btn-sm">+ Neues Fahrzeug</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        Fahrzeuge ({{ $vehicles->total() }})
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Interner Name</th>
                    <th>Kennzeichen</th>
                    <th>Fahrzeugtyp</th>
                    <th style="text-align:center">Status</th>
                    <th>TÜV fällig</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($vehicles as $vehicle)
                @php
                    $tuevDays = $vehicle->tuev_due_date ? now()->diffInDays($vehicle->tuev_due_date, false) : null;
                @endphp
                <tr>
                    <td><strong>{{ $vehicle->internal_name }}</strong></td>
                    <td><code>{{ $vehicle->plate_number }}</code></td>
                    <td>{{ $vehicle->vehicle_type }}</td>
                    <td style="text-align:center">
                        @if($vehicle->active)
                            <span class="badge badge-delivered">aktiv</span>
                        @else
                            <span class="badge badge-cancelled">inaktiv</span>
                        @endif
                    </td>
                    <td>
                        @if($vehicle->tuev_due_date)
                            @if($tuevDays !== null && $tuevDays < 30)
                                <span style="color:var(--c-danger);font-weight:600">
                                    {{ $vehicle->tuev_due_date->format('d.m.Y') }}
                                    @if($tuevDays < 0)
                                        (abgelaufen)
                                    @else
                                        ({{ $tuevDays }} Tage)
                                    @endif
                                </span>
                            @else
                                {{ $vehicle->tuev_due_date->format('d.m.Y') }}
                            @endif
                        @else
                            <span style="color:var(--c-muted)">—</span>
                        @endif
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.vehicles.show', $vehicle) }}"
                           class="btn btn-outline btn-sm">Details</a>
                        <a href="{{ route('admin.vehicles.edit', $vehicle) }}"
                           class="btn btn-outline btn-sm">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.vehicles.destroy', $vehicle) }}"
                              style="display:inline"
                              onsubmit="return confirm('Fahrzeug \"{{ addslashes($vehicle->internal_name) }}\" wirklich löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm"
                                    style="color:var(--c-danger)">Löschen</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:var(--c-muted);padding:24px">
                        Noch keine Fahrzeuge angelegt.
                        <a href="{{ route('admin.vehicles.create') }}"
                           class="btn btn-primary btn-sm" style="margin-left:12px">
                            + Erstes Fahrzeug anlegen
                        </a>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $vehicles->links() }}
@endsection
