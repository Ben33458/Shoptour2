@extends('admin.layout')

@section('title', 'Lieferanten verwalten')

@section('actions')
    <a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary btn-sm">+ Neuer Lieferant</a>
@endsection

@section('content')

{{-- ── Search bar ── --}}
<form method="GET" action="{{ route('admin.suppliers.index') }}">
    <div class="filter-bar">
        <div class="form-group" style="flex:2">
            <label>Suche</label>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Name, Ansprechpartner oder E-Mail…">
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;padding-bottom:0">
            <button type="submit" class="btn btn-primary">Suchen</button>
            <a href="{{ route('admin.suppliers.index') }}" class="btn btn-outline">Zurücksetzen</a>
        </div>
    </div>
</form>

{{-- ── Suppliers table ── --}}
<div class="card">
    <div class="card-header">
        Lieferanten ({{ $suppliers->total() }})
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Ansprechpartner</th>
                    <th>E-Mail</th>
                    <th>Telefon</th>
                    <th>Währung</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($suppliers as $supplier)
                <tr>
                    <td><strong>{{ $supplier->name }}</strong></td>
                    <td>{{ $supplier->contact_name ?? '—' }}</td>
                    <td>{{ $supplier->email ?? '—' }}</td>
                    <td>{{ $supplier->phone ?? '—' }}</td>
                    <td><code>{{ $supplier->currency }}</code></td>
                    <td>
                        @if($supplier->active)
                            <span class="badge badge-delivered">aktiv</span>
                        @else
                            <span class="badge badge-cancelled">inaktiv</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.suppliers.edit', $supplier) }}"
                               class="btn btn-outline btn-sm">Bearbeiten</a>
                            <form method="POST" action="{{ route('admin.suppliers.destroy', $supplier) }}"
                                  onsubmit="return confirm('Lieferant wirklich löschen?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline btn-sm"
                                        style="color:var(--c-danger)">Löschen</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted" style="padding:24px">
                        Keine Lieferanten gefunden.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $suppliers->links() }}

@endsection
