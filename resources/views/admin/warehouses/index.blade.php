@extends('admin.layout')

@section('title', 'Lagerorte')

@section('actions')
    <a href="{{ route('admin.warehouses.create') }}" class="btn btn-primary btn-sm">+ Neues Lager</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        Lagerorte ({{ $warehouses->total() }})
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Standort</th>
                    <th style="text-align:center">Abholort</th>
                    <th style="text-align:center">Status</th>
                    <th style="text-align:center">Positionen</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($warehouses as $warehouse)
                <tr>
                    <td><strong>{{ $warehouse->name }}</strong></td>
                    <td>{{ $warehouse->location ?? '—' }}</td>
                    <td style="text-align:center">
                        {{ $warehouse->is_pickup_location ? '✓' : '—' }}
                    </td>
                    <td style="text-align:center">
                        @if($warehouse->active)
                            <span class="badge badge-delivered">aktiv</span>
                        @else
                            <span class="badge badge-cancelled">inaktiv</span>
                        @endif
                    </td>
                    <td style="text-align:center">
                        <span class="badge">{{ $warehouse->stocks_count }}</span>
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.warehouses.show', $warehouse) }}"
                           class="btn btn-outline btn-sm">Bestände</a>
                        <a href="{{ route('admin.warehouses.edit', $warehouse) }}"
                           class="btn btn-outline btn-sm">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.warehouses.destroy', $warehouse) }}"
                              style="display:inline"
                              onsubmit="return confirm('Lagerort \"{{ addslashes($warehouse->name) }}\" wirklich löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm"
                                    style="color:var(--c-danger)">Löschen</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:var(--c-muted);padding:24px">
                        Noch keine Lagerorte angelegt.
                        <a href="{{ route('admin.warehouses.create') }}"
                           class="btn btn-primary btn-sm" style="margin-left:12px">
                            + Ersten Lagerort anlegen
                        </a>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $warehouses->links() }}
@endsection
