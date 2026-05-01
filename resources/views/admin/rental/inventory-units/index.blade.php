@extends('admin.layout')

@section('title', 'Inventareinheiten')

@section('actions')
    <a href="{{ route('admin.rental.inventory-units.create') }}" class="btn btn-primary btn-sm">+ Neue Einheit</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header" style="display:flex;align-items:center;gap:16px">
        <span>Inventareinheiten ({{ $units->total() }})</span>
        <form method="GET" style="display:flex;gap:8px;margin-left:auto">
            <select name="status" class="form-control" style="width:auto" onchange="this.form.submit()">
                <option value="">Alle Status</option>
                @foreach($statuses as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
            <select name="item_id" class="form-control" style="width:auto" onchange="this.form.submit()">
                <option value="">Alle Artikel</option>
                @foreach($items as $item)
                    <option value="{{ $item->id }}" {{ request('item_id') == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Inventarnummer</th>
                    <th>Bezeichnung</th>
                    <th>Leihartikel</th>
                    <th>Status</th>
                    <th style="text-align:center">Bevorzugt</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($units as $unit)
                <tr>
                    <td><code>{{ $unit->inventory_number }}</code></td>
                    <td>{{ $unit->title }}</td>
                    <td>{{ $unit->rentalItem?->name }}</td>
                    <td>
                        @php
                            $badge = match($unit->status) {
                                'available'  => 'badge-delivered',
                                'in_use'     => 'badge-confirmed',
                                'defective','retired' => 'badge-cancelled',
                                default      => '',
                            };
                        @endphp
                        <span class="badge {{ $badge }}">{{ $unit->status }}</span>
                    </td>
                    <td style="text-align:center">
                        @if($unit->preferred_for_booking)
                            <span class="badge badge-delivered">ja</span>
                        @else
                            <span style="color:var(--c-muted)">—</span>
                        @endif
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.rental.inventory-units.edit', $unit) }}" class="btn btn-outline btn-sm">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.rental.inventory-units.destroy', $unit) }}"
                              style="display:inline"
                              onsubmit="return confirm('Einheit löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--c-danger)">Löschen</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:var(--c-muted);padding:24px">
                        Keine Einheiten gefunden.
                        <a href="{{ route('admin.rental.inventory-units.create') }}" class="btn btn-primary btn-sm" style="margin-left:12px">+ Erste anlegen</a>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $units->links() }}
@endsection
