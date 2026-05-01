@extends('admin.layout')

@section('title', 'Verpackungseinheiten')

@section('actions')
    <a href="{{ route('admin.rental.packaging-units.create', request()->only('item_id')) }}"
       class="btn btn-primary btn-sm">+ Neue Verpackungseinheit</a>
@endsection

@section('content')

{{-- Filter --}}
<div class="card mb-3">
    <div style="padding:12px 16px">
        <form method="GET" action="{{ route('admin.rental.packaging-units.index') }}"
              class="d-flex align-items-center gap-2 flex-wrap">
            <select name="item_id" class="form-select form-select-sm" style="max-width:280px"
                    onchange="this.form.submit()">
                <option value="">— Alle Leihartikel —</option>
                @foreach($items as $item)
                    <option value="{{ $item->id }}"
                        {{ request('item_id') == $item->id ? 'selected' : '' }}>
                        {{ $item->name }}
                    </option>
                @endforeach
            </select>
            @if(request('item_id'))
                <a href="{{ route('admin.rental.packaging-units.index') }}" class="btn btn-sm btn-outline-secondary">
                    Filter aufheben
                </a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        Verpackungseinheiten ({{ $units->total() }})
        @if(request('item_id') && ($filtered = $items->firstWhere('id', request('item_id'))))
            — {{ $filtered->name }}
        @endif
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Leihartikel</th>
                    <th>Bezeichnung</th>
                    <th>Stück / Gebinde</th>
                    <th>Verfügbare Gebinde</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($units as $unit)
                <tr>
                    <td>{{ $unit->rentalItem?->name ?? '—' }}</td>
                    <td>{{ $unit->label }}</td>
                    <td>{{ $unit->pieces_per_pack }}</td>
                    <td>{{ $unit->available_packs }}</td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.rental.packaging-units.edit', $unit) }}" class="btn btn-outline btn-sm">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.rental.packaging-units.destroy', $unit) }}"
                              style="display:inline"
                              onsubmit="return confirm('Verpackungseinheit löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--c-danger)">Löschen</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align:center;color:var(--c-muted);padding:24px">
                        Noch keine Einheiten angelegt.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $units->links() }}
@endsection
