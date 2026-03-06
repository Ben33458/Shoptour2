@extends('admin.layout')

@section('title', 'Kunden verwalten')

@section('actions')
    <a href="{{ route('admin.customers.create') }}" class="btn btn-primary btn-sm">+ Neuer Kunde</a>
@endsection

@section('content')

{{-- ── Search bar ── --}}
<form method="GET" action="{{ route('admin.customers.index') }}">
    <div class="filter-bar">
        <div class="form-group" style="flex:2">
            <label>Suche</label>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Name, Kundennummer oder E-Mail…">
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;padding-bottom:0">
            <button type="submit" class="btn btn-primary">Suchen</button>
            <a href="{{ route('admin.customers.index') }}" class="btn btn-outline">Zurücksetzen</a>
        </div>
    </div>
</form>

{{-- ── Customers table ── --}}
<div class="card">
    <div class="card-header">
        Kunden ({{ $customers->total() }})
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Kundennr.</th>
                    <th>Name</th>
                    <th>E-Mail</th>
                    <th>Gruppe</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($customers as $customer)
                <tr>
                    <td><code>{{ $customer->customer_number }}</code></td>
                    <td>
                        @if($customer->first_name || $customer->last_name)
                            {{ $customer->first_name }} {{ $customer->last_name }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $customer->email ?? '—' }}</td>
                    <td>{{ $customer->customerGroup?->name ?? '—' }}</td>
                    <td>
                        @if($customer->active)
                            <span class="badge badge-delivered">aktiv</span>
                        @else
                            <span class="badge badge-cancelled">inaktiv</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.customers.edit', $customer) }}"
                               class="btn btn-outline btn-sm">Bearbeiten</a>
                            <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}"
                                  onsubmit="return confirm('Kunden wirklich löschen?')">
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
                    <td colspan="6" class="text-center text-muted" style="padding:24px">
                        Keine Kunden gefunden.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $customers->links() }}

@endsection
