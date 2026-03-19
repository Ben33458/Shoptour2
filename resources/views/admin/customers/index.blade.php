@extends('admin.layout')

@section('title', 'Kunden verwalten')

@section('actions')
    <a href="{{ route('admin.customers.create') }}" class="btn btn-primary btn-sm">+ Neuer Kunde</a>
    <details class="actions-dropdown">
        <summary class="btn btn-outline btn-sm">Aktionen ▾</summary>
        <div class="actions-menu">
            <a href="{{ route('admin.imports.customers') }}">CSV importieren</a>
        </div>
    </details>
@endsection

@section('content')

{{-- ── Filter-Tabs ── --}}
@php $sortQs2 = request()->only(['search', 'sort', 'direction']); @endphp
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
    <a href="{{ route('admin.customers.index', $sortQs2) }}"
       class="btn btn-sm {{ !$notesFilter ? 'btn-primary' : 'btn-outline' }}">
        Alle Kunden
    </a>
    <a href="{{ route('admin.customers.index', array_merge($sortQs2, ['notes_filter' => 'lexoffice_diff'])) }}"
       class="btn btn-sm {{ $notesFilter === 'lexoffice_diff' ? 'btn-primary' : 'btn-outline' }}">
        Lexoffice-Abweichungen
        @if($pendingDiffCount > 0)
            <span class="badge" style="background:var(--c-danger);color:#fff;margin-left:6px">{{ $pendingDiffCount }}</span>
        @endif
    </a>
</div>

{{-- ── Search bar ── --}}
<form method="GET" action="{{ route('admin.customers.index') }}">
    @if($notesFilter)
        <input type="hidden" name="notes_filter" value="{{ $notesFilter }}">
    @endif
    @if(request('sort'))
        <input type="hidden" name="sort" value="{{ request('sort') }}">
        <input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">
    @endif
    <div class="filter-bar">
        <div class="form-group" style="flex:2">
            <label>Suche</label>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Name, Kundennummer oder E-Mail…">
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;padding-bottom:0">
            <button type="submit" class="btn btn-primary">Suchen</button>
            <a href="{{ route('admin.customers.index', array_filter(['notes_filter' => $notesFilter, 'sort' => request('sort'), 'direction' => request('direction')])) }}"
               class="btn btn-outline">Zurücksetzen</a>
        </div>
    </div>
</form>

@php
    $sortParams = request()->only(['search', 'notes_filter', 'type_filter']);
    $currentSort = request('sort', 'customer_number');
    $currentDir  = request('direction', 'asc');
    function sortLink(string $col, string $label, array $base, string $active, string $dir): string {
        $newDir = ($active === $col && $dir === 'asc') ? 'desc' : 'asc';
        $arrow  = $active === $col ? ($dir === 'asc' ? ' ↑' : ' ↓') : '';
        $params = http_build_query(array_merge($base, ['sort' => $col, 'direction' => $newDir]));
        $url    = request()->url() . '?' . $params;
        return '<a href="' . e($url) . '" style="color:inherit;text-decoration:none;white-space:nowrap">' . e($label) . $arrow . '</a>';
    }
@endphp

{{-- ── Customers table ── --}}
<div class="card">
    <div class="card-header">
        Kunden ({{ $customers->total() }})
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>{!! sortLink('customer_number', 'Kundennr.', $sortParams, $currentSort, $currentDir) !!}</th>
                    <th>{!! sortLink('company_name', 'Name', $sortParams, $currentSort, $currentDir) !!}</th>
                    <th>{!! sortLink('email', 'E-Mail', $sortParams, $currentSort, $currentDir) !!}</th>
                    <th>Gruppe</th>
                    <th>{!! sortLink('active', 'Status', $sortParams, $currentSort, $currentDir) !!}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($customers as $customer)
                <tr>
                    <td>
                        <a href="{{ route('admin.customers.show', $customer) }}"
                           style="font-family:monospace;font-size:13px">{{ $customer->customer_number }}</a>
                    </td>
                    <td>
                        @if($customer->company_name)
                            <strong>{{ $customer->company_name }}</strong>
                            @if($customer->first_name || $customer->last_name)
                                <br><span style="color:var(--c-muted);font-size:12px">{{ trim($customer->first_name . ' ' . $customer->last_name) }}</span>
                            @endif
                        @elseif($customer->first_name || $customer->last_name)
                            {{ $customer->first_name }} {{ $customer->last_name }}
                        @else
                            <span style="color:var(--c-muted)">—</span>
                        @endif
                        @if($customer->lexoffice_contact_id && isset($supplierLexIds[$customer->lexoffice_contact_id]))
                            <span class="badge" style="font-size:10px;background:#fef3c7;color:#92400e;margin-left:4px">auch Lieferant</span>
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
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.customers.show', $customer) }}"
                           class="btn btn-outline btn-sm">Detail</a>
                        <a href="{{ route('admin.customers.edit', $customer) }}"
                           class="btn btn-outline btn-sm">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}"
                              style="display:inline"
                              onsubmit="return confirm('Kunden wirklich löschen?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm"
                                    style="color:var(--c-danger)">Löschen</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:var(--c-muted);padding:24px">
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
