@extends('admin.layout')

@section('title', 'GetraenkeDB-Abgleich')

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

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:20px">
    <h1 style="font-size:20px;font-weight:700;color:var(--c-text)">GetraenkeDB-Abgleich</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <form method="POST" action="{{ route('admin.reconcile.getraenkedb.sync') }}">
            @csrf
            <button type="submit"
                    style="padding:8px 16px;background:#7c3aed;color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer;font-weight:600">
                Synchronisieren (Bilder + LMIV)
            </button>
        </form>
        <form method="POST" action="{{ route('admin.reconcile.getraenkedb.sync-categories') }}">
            @csrf
            <button type="submit"
                    style="padding:8px 16px;background:#0891b2;color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer;font-weight:600">
                Kategorien zuordnen
            </button>
        </form>
        <form method="POST" action="{{ route('admin.reconcile.getraenkedb.clear-cache') }}">
            @csrf
            <button type="submit"
                    style="padding:8px 16px;background:transparent;border:1px solid var(--c-border);color:var(--c-muted);border-radius:6px;font-size:13px;cursor:pointer"
                    title="Erzwingt neue API-Abfragen bei nächstem Seitenaufruf">
                Cache leeren
            </button>
        </form>
    </div>
</div>

{{-- Stats (live via JS) --}}
@php
    $totalCount     = count($proposals);
    $confirmedCount = collect($proposals)->filter(fn($p) => ($p['existing_match']?->status ?? '') === 'confirmed')->count();
    $pendingCount   = collect($proposals)->filter(fn($p) => !in_array($p['existing_match']?->status ?? '', ['confirmed','ignored']))->count();
@endphp
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px">
    <div style="background:var(--c-card);border:1px solid var(--c-border);border-radius:10px;padding:16px 20px">
        <div id="stat-visible" style="font-size:26px;font-weight:700;color:var(--c-text)">{{ $totalCount }}</div>
        <div style="font-size:12px;color:var(--c-muted);margin-top:2px">Angezeigt</div>
    </div>
    <div style="background:var(--c-card);border:1px solid var(--c-border);border-radius:10px;padding:16px 20px">
        <div style="font-size:26px;font-weight:700;color:#ef4444">{{ $pendingCount }}</div>
        <div style="font-size:12px;color:var(--c-muted);margin-top:2px">Offen</div>
    </div>
    <div style="background:var(--c-card);border:1px solid var(--c-border);border-radius:10px;padding:16px 20px">
        <div style="font-size:26px;font-weight:700;color:#10b981">{{ $confirmedCount }}</div>
        <div style="font-size:12px;color:var(--c-muted);margin-top:2px">Bestätigt</div>
    </div>
</div>

{{-- Sort-Links --}}
@php
    $sortLink = fn(string $col) => route('admin.reconcile.getraenkedb.index', [
        'sort' => $col,
        'dir'  => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
    ]);
    $sortIcon = fn(string $col) => $sort === $col ? ($dir === 'asc' ? ' ↑' : ' ↓') : ' ↕';
    $thStyle  = 'padding:10px 14px;text-align:left;color:var(--c-muted);font-weight:600';
    $thLink   = 'color:inherit;text-decoration:none;white-space:nowrap';
@endphp

{{-- Filter-Leiste (client-seitig) --}}
<div class="filter-bar" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px">
    {{-- Status-Pillen --}}
    @foreach([''=>'Alle','open'=>'Offen','confirmed'=>'Bestätigt','ignored'=>'Abgelehnt'] as $val => $label)
    <button type="button" data-status-filter="{{ $val }}"
            onclick="setStatusFilter('{{ $val }}')"
            style="padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--c-border);background:transparent;color:var(--c-muted)">
        {{ $label }}
    </button>
    @endforeach

    {{-- Suche --}}
    <input type="text" id="table-search"
           placeholder="Suche nach Produktname, Art.Nr. …"
           oninput="applyFilters()"
           style="flex:1;min-width:220px;padding:7px 12px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface,var(--c-card));color:var(--c-text)">
    <button type="button" onclick="clearFilters()"
            style="padding:7px 14px;background:transparent;border:1px solid var(--c-border);border-radius:6px;font-size:12px;cursor:pointer;color:var(--c-muted)">
        × Zurücksetzen
    </button>
</div>

{{-- Verstecktes Bulk-Form (außerhalb der Tabelle, wird per JS befüllt) --}}
<form id="bulk-form" method="POST" action="">
    @csrf
</form>

{{-- Bulk-Aktionsleiste --}}
<div id="bulk-bar"
     style="display:none;background:var(--c-card);border:1px solid var(--c-border);border-radius:8px;padding:10px 16px;margin-bottom:10px;align-items:center;gap:12px;flex-wrap:wrap">
    <span id="bulk-count" style="font-size:13px;font-weight:600;color:var(--c-text)">0 ausgewählt</span>
    <button type="button" onclick="bulkSubmit('{{ route('admin.reconcile.getraenkedb.bulk-confirm') }}')"
            style="padding:6px 14px;background:#10b981;color:#fff;border:none;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer">
        Ausgewählte bestätigen
    </button>
    <button type="button" onclick="bulkSubmit('{{ route('admin.reconcile.getraenkedb.bulk-ignore') }}')"
            style="padding:6px 14px;background:#fef2f2;color:var(--c-danger,#ef4444);border:1px solid var(--c-danger,#ef4444);border-radius:5px;font-size:12px;font-weight:600;cursor:pointer">
        Ausgewählte ablehnen
    </button>
    <button type="button" onclick="deselectAll()"
            style="padding:6px 14px;background:transparent;border:1px solid var(--c-border);border-radius:5px;font-size:12px;cursor:pointer;color:var(--c-muted)">
        Auswahl aufheben
    </button>
</div>

<div style="background:var(--c-card);border:1px solid var(--c-border);border-radius:10px;overflow:hidden">
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead>
                    <tr style="background:var(--c-bg);border-bottom:1px solid var(--c-border)">
                        <th style="padding:10px 14px;width:36px;text-align:center">
                            <input type="checkbox" id="select-all" title="Alle auswählen"
                                   style="width:15px;height:15px;cursor:pointer">
                        </th>
                        <th style="{{ $thStyle }}"><a href="{{ $sortLink('name') }}" style="{{ $thLink }}">shoptour2-Produkt{{ $sortIcon('name') }}</a></th>
                        <th style="{{ $thStyle }}">getraenkeDB-Vorschlag</th>
                        <th style="{{ $thStyle }};text-align:center"><a href="{{ $sortLink('confidence') }}" style="{{ $thLink }}">Konfidenz{{ $sortIcon('confidence') }}</a></th>
                        <th style="{{ $thStyle }};text-align:center">Methode</th>
                        <th style="{{ $thStyle }};text-align:center">Status</th>
                        <th style="{{ $thStyle }};text-align:right">Aktionen</th>
                    </tr>
                </thead>
                <tbody id="proposals-tbody">
                    @forelse($proposals as $p)
                    @php
                        $status     = $p['existing_match']?->status ?? 'unmatched';
                        $statusKey  = in_array($status, ['confirmed','ignored']) ? $status : 'open';
                        $suggestion = $p['suggestion'];
                        $product    = $p['product'];
                        $slug       = $suggestion['slug'] ?? $suggestion['product_family']['slug'] ?? null;
                        $searchText = mb_strtolower($product->produktname . ' ' . $product->artikelnummer);
                    @endphp
                    <tr data-status="{{ $statusKey }}"
                        data-search="{{ $searchText }}"
                        style="border-bottom:1px solid var(--c-border);{{ $status === 'ignored' ? 'opacity:0.5' : '' }}">

                        {{-- Checkbox (data-attrs, kein form-name — wird per JS ins Bulk-Form übertragen) --}}
                        <td style="padding:10px 14px;text-align:center">
                            @if($slug)
                            <input type="checkbox" class="row-check"
                                   data-product-id="{{ $product->id }}"
                                   data-slug="{{ $slug }}"
                                   style="width:15px;height:15px;cursor:pointer">
                            @endif
                        </td>

                        {{-- shoptour2 Produkt --}}
                        <td style="padding:10px 14px">
                            <div style="font-weight:600;color:var(--c-text)">{{ $product->produktname }}</div>
                            <div style="font-size:11px;color:var(--c-muted)">Art.Nr. {{ $product->artikelnummer }} · ID {{ $product->id }}</div>
                        </td>

                        {{-- getraenkeDB Vorschlag --}}
                        <td style="padding:10px 14px">
                            @if(!empty($suggestion))
                                @php $name = $suggestion['name'] ?? ($suggestion['product_family']['name'] ?? '—'); @endphp
                                <div style="font-weight:600;color:var(--c-text)">{{ $name }}</div>
                                <div style="font-size:11px;color:var(--c-muted)">
                                    {{ $suggestion['brand'] ?? ($suggestion['product_family']['brand'] ?? '') }}
                                    @if(!empty($suggestion['slug']))
                                        · <code>{{ $suggestion['slug'] }}</code>
                                    @elseif(!empty($suggestion['product_family']['slug']))
                                        · <code>{{ $suggestion['product_family']['slug'] }}</code>
                                    @endif
                                </div>
                                @php
                                    $tradeItems = $suggestion['trade_items'] ?? [];
                                    $hasDeposit = collect($tradeItems)->contains('deposit_applicable', true);
                                @endphp
                                @if($hasDeposit)
                                    <div style="font-size:11px;color:#7c3aed;margin-top:2px">Pfand vorhanden</div>
                                @endif
                            @else
                                <span style="color:var(--c-muted)">—</span>
                            @endif
                        </td>

                        {{-- Konfidenz --}}
                        <td style="padding:10px 14px;text-align:center">
                            @if($p['confidence'] > 0)
                                @php $color = $p['confidence'] >= 90 ? '#10b981' : ($p['confidence'] >= 70 ? '#f59e0b' : '#ef4444'); @endphp
                                <span style="font-weight:700;color:{{ $color }}">{{ $p['confidence'] }}%</span>
                            @else
                                <span style="color:var(--c-muted)">—</span>
                            @endif
                        </td>

                        {{-- Methode --}}
                        <td style="padding:10px 14px;text-align:center">
                            <span style="font-size:11px;background:#f3f4f6;padding:2px 8px;border-radius:12px;color:#374151">
                                {{ $p['method'] ?: '—' }}
                            </span>
                        </td>

                        {{-- Status --}}
                        <td style="padding:10px 14px;text-align:center">
                            @if($status === 'confirmed')
                                <span style="font-size:11px;background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:12px;font-weight:600">Bestätigt</span>
                            @elseif($status === 'ignored')
                                <span style="font-size:11px;background:#fef2f2;color:var(--c-danger);padding:2px 8px;border-radius:12px">Abgelehnt</span>
                            @else
                                <span style="font-size:11px;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:12px">Offen</span>
                            @endif
                        </td>

                        {{-- Aktionen --}}
                        <td style="padding:10px 14px;text-align:right;white-space:nowrap">
                            @if(!empty($suggestion) && $status !== 'confirmed' && $slug)
                            <form method="POST" action="{{ route('admin.reconcile.getraenkedb.confirm') }}" style="display:inline">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="hidden" name="slug" value="{{ $slug }}">
                                <button type="submit"
                                        style="font-size:12px;padding:4px 10px;background:#10b981;color:#fff;border:none;border-radius:4px;cursor:pointer;margin-right:4px">
                                    Bestätigen
                                </button>
                            </form>
                            @endif

                            @if($status !== 'ignored')
                            <form method="POST" action="{{ route('admin.reconcile.getraenkedb.ignore') }}" style="display:inline">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <button type="submit"
                                        style="font-size:12px;padding:4px 10px;background:#fef2f2;color:var(--c-danger);border:1px solid var(--c-danger);border-radius:4px;cursor:pointer">
                                    Ablehnen
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr id="empty-row">
                        <td colspan="7" style="padding:40px;text-align:center;color:var(--c-muted)">
                            Keine Vorschläge gefunden. API-Key konfiguriert?
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div id="no-results" style="display:none;padding:40px;text-align:center;color:var(--c-muted)">
                Keine Einträge für diesen Filter.
            </div>
        </div>
    </div>

<script>
let activeStatus = '';
const bulkForm  = document.getElementById('bulk-form');
const bulkBar   = document.getElementById('bulk-bar');
const countEl   = document.getElementById('bulk-count');
const selectAll = document.getElementById('select-all');
const statEl    = document.getElementById('stat-visible');
const noResults = document.getElementById('no-results');
const tbody     = document.getElementById('proposals-tbody');

// ── Filter ────────────────────────────────────────────────────────────────────

function applyFilters() {
    const q     = document.getElementById('table-search').value.toLowerCase().trim();
    const rows  = tbody.querySelectorAll('tr[data-status]');
    let visible = 0;

    rows.forEach(row => {
        const show = (activeStatus === '' || row.dataset.status === activeStatus)
                  && (q === '' || row.dataset.search.includes(q));
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    statEl.textContent      = visible;
    noResults.style.display = visible === 0 && rows.length > 0 ? 'block' : 'none';
    updateBulkBar();
}

function setStatusFilter(val) {
    activeStatus = val;
    document.querySelectorAll('[data-status-filter]').forEach(btn => {
        const active         = btn.dataset.statusFilter === val;
        btn.style.background = active ? 'var(--c-primary,#2563eb)' : 'transparent';
        btn.style.color      = active ? '#fff' : 'var(--c-muted)';
        btn.style.border     = active ? '1px solid var(--c-primary,#2563eb)' : '1px solid var(--c-border)';
    });
    applyFilters();
}

function clearFilters() {
    document.getElementById('table-search').value = '';
    setStatusFilter('');
}

// ── Bulk-Auswahl ──────────────────────────────────────────────────────────────

function updateBulkBar() {
    const checked = document.querySelectorAll('.row-check:checked');
    const visible = tbody.querySelectorAll('tr[data-status]:not([style*="display: none"]) .row-check');
    const n       = checked.length;

    bulkBar.style.display   = n > 0 ? 'flex' : 'none';
    countEl.textContent     = n + ' ausgewählt';
    selectAll.indeterminate = n > 0 && n < visible.length;
    selectAll.checked       = visible.length > 0 && n >= visible.length;
}

selectAll.addEventListener('change', function () {
    tbody.querySelectorAll('tr[data-status]:not([style*="display: none"]) .row-check')
        .forEach(cb => cb.checked = this.checked);
    updateBulkBar();
});

document.querySelectorAll('.row-check').forEach(cb => cb.addEventListener('change', updateBulkBar));

function bulkSubmit(action) {
    const checked = document.querySelectorAll('.row-check:checked');
    if (!checked.length) return;

    // Populate hidden bulk-form with selected items
    bulkForm.querySelectorAll('input[name^="sel"]').forEach(el => el.remove());
    checked.forEach(cb => {
        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = `sel[${cb.dataset.productId}]`;
        input.value = cb.dataset.slug;
        bulkForm.appendChild(input);
    });

    bulkForm.action = action;
    bulkForm.submit();
}

function deselectAll() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
    updateBulkBar();
}

setStatusFilter('');
</script>

@endsection
