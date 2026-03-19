@extends('admin.layout')

@section('title', 'Lieferanten verwalten')

@section('actions')
    <a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary btn-sm">+ Neuer Lieferant</a>
    <details class="actions-dropdown">
        <summary class="btn btn-outline btn-sm">Aktionen ▾</summary>
        <div class="actions-menu">
            <a href="{{ route('admin.imports.suppliers') }}">CSV importieren</a>
        </div>
    </details>
@endsection

@section('content')

@if(session('success'))
    <div style="margin-bottom:16px;padding:12px 16px;background:#d1fae5;border:1px solid #10b981;border-radius:6px;color:#065f46">
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div style="margin-bottom:16px;padding:12px 16px;background:#fee2e2;border:1px solid #ef4444;border-radius:6px;color:#991b1b">
        {{ session('error') }}
    </div>
@endif

{{-- ── Filter-Tabs ── --}}
@php $sortQs = request()->only(['search', 'sort', 'direction']); @endphp
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
    <a href="{{ route('admin.suppliers.index', $sortQs) }}"
       class="btn btn-sm {{ !request('type_filter') ? 'btn-primary' : 'btn-outline' }}">
        Alle
    </a>
    <a href="{{ route('admin.suppliers.index', array_merge($sortQs, ['type_filter' => 'supplier'])) }}"
       class="btn btn-sm {{ request('type_filter') === 'supplier' ? 'btn-primary' : 'btn-outline' }}">
        Warenlieferanten
    </a>
    <a href="{{ route('admin.suppliers.index', array_merge($sortQs, ['type_filter' => 'partner'])) }}"
       class="btn btn-sm {{ request('type_filter') === 'partner' ? 'btn-primary' : 'btn-outline' }}">
        Geschäftspartner
    </a>
</div>

{{-- ── Search bar ── --}}
<form method="GET" action="{{ route('admin.suppliers.index') }}">
    @if(request('type_filter'))
        <input type="hidden" name="type_filter" value="{{ request('type_filter') }}">
    @endif
    @if(request('sort'))
        <input type="hidden" name="sort" value="{{ request('sort') }}">
        <input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">
    @endif
    <div class="filter-bar">
        <div class="form-group" style="flex:2">
            <label>Suche</label>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Name, Ansprechpartner oder E-Mail…">
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;padding-bottom:0">
            <button type="submit" class="btn btn-primary">Suchen</button>
            <a href="{{ route('admin.suppliers.index', array_filter(['type_filter' => request('type_filter'), 'sort' => request('sort'), 'direction' => request('direction')])) }}"
               class="btn btn-outline">Zurücksetzen</a>
        </div>
    </div>
</form>

{{-- ── Hidden bulk form (separate, no nesting) ── --}}
<form method="POST" action="{{ route('admin.suppliers.bulk-set-type') }}" id="bulk-form" style="display:none">
    @csrf
    <div id="bulk-ids-container"></div>
    <input type="hidden" name="type" id="bulk-type-input" value="">
</form>

{{-- ── Bulk action bar (shown when items selected) ── --}}
<div id="bulk-bar" style="display:none;margin-bottom:12px;padding:10px 16px;
     background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;
     align-items:center;gap:12px">
    <span id="bulk-count" style="font-size:13px;color:#1e40af;font-weight:600"></span>
    <button type="button" class="btn btn-sm" id="btn-set-partner"
            style="background:#4338ca;color:#fff;border-color:#4338ca">
        Als Geschäftspartner markieren
    </button>
    <button type="button" class="btn btn-sm btn-outline" id="btn-set-supplier">
        Als Warenlieferant markieren
    </button>
</div>

@php
    $sortParams2 = request()->only(['search', 'type_filter']);
    $currentSort2 = request('sort', 'name');
    $currentDir2  = request('direction', 'asc');
    function supplierSortLink(string $col, string $label, array $base, string $active, string $dir): string {
        $newDir = ($active === $col && $dir === 'asc') ? 'desc' : 'asc';
        $arrow  = $active === $col ? ($dir === 'asc' ? ' ↑' : ' ↓') : '';
        $params = http_build_query(array_merge($base, ['sort' => $col, 'direction' => $newDir]));
        $url    = request()->url() . '?' . $params;
        return '<a href="' . e($url) . '" style="color:inherit;text-decoration:none;white-space:nowrap">' . e($label) . $arrow . '</a>';
    }
@endphp

{{-- ── Suppliers table ── --}}
<div class="card">
    <div class="card-header">
        Lieferanten ({{ $suppliers->total() }})
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:32px">
                        <input type="checkbox" id="select-all" title="Alle auswählen">
                    </th>
                    <th>{!! supplierSortLink('name', 'Name', $sortParams2, $currentSort2, $currentDir2) !!}</th>
                    <th>Ansprechpartner</th>
                    <th>{!! supplierSortLink('email', 'E-Mail', $sortParams2, $currentSort2, $currentDir2) !!}</th>
                    <th>{!! supplierSortLink('phone', 'Telefon', $sortParams2, $currentSort2, $currentDir2) !!}</th>
                    <th>{!! supplierSortLink('currency', 'Währung', $sortParams2, $currentSort2, $currentDir2) !!}</th>
                    <th>{!! supplierSortLink('active', 'Status', $sortParams2, $currentSort2, $currentDir2) !!}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($suppliers as $supplier)
                <tr>
                    <td>
                        <input type="checkbox" value="{{ $supplier->id }}"
                               class="row-check">
                    </td>
                    <td>
                        <strong>{{ $supplier->name }}</strong>
                        @if($supplier->type === 'partner')
                            <span class="badge" style="font-size:10px;background:#e0e7ff;color:#4338ca;margin-left:4px">Geschäftspartner</span>
                        @endif
                    </td>
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
                    <td colspan="8" class="text-center text-muted" style="padding:24px">
                        Keine Lieferanten gefunden.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $suppliers->links() }}

<script>
(function () {
    const selectAll   = document.getElementById('select-all');
    const bulkBar     = document.getElementById('bulk-bar');
    const bulkCount   = document.getElementById('bulk-count');
    const bulkForm    = document.getElementById('bulk-form');
    const bulkIds     = document.getElementById('bulk-ids-container');
    const bulkType    = document.getElementById('bulk-type-input');
    const checkboxes  = () => document.querySelectorAll('.row-check');

    function updateBar() {
        const checked = document.querySelectorAll('.row-check:checked').length;
        bulkBar.style.display = checked > 0 ? 'flex' : 'none';
        if (checked > 0) bulkCount.textContent = checked + ' ausgewählt';
        selectAll.indeterminate = checked > 0 && checked < checkboxes().length;
        selectAll.checked = checked > 0 && checked === checkboxes().length;
    }

    function submitBulk(type, label) {
        const ids = [...document.querySelectorAll('.row-check:checked')].map(cb => cb.value);
        if (!ids.length) return;
        bulkIds.innerHTML = ids.map(id => `<input type="hidden" name="ids[]" value="${id}">`).join('');
        bulkType.value = type;
        bulkForm.submit();
    }

    document.getElementById('btn-set-partner').addEventListener('click', () => submitBulk('partner', 'Geschäftspartner'));
    document.getElementById('btn-set-supplier').addEventListener('click', () => submitBulk('supplier', 'Warenlieferant'));

    selectAll.addEventListener('change', function () {
        checkboxes().forEach(cb => cb.checked = this.checked);
        updateBar();
    });

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('row-check')) updateBar();
    });
})();
</script>

@endsection
