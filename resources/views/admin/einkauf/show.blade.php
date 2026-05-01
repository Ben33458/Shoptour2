@extends('admin.layout')

@section('title', 'Bestellung ' . $purchaseOrder->po_number)

@section('content')

<p style="font-size:.85rem;margin-bottom:6px;">
    <a href="{{ route('admin.einkauf.index') }}" class="text-muted">&larr; Zurück zur Übersicht</a>
</p>
<div class="page-header">
    <h1>Bestellung {{ $purchaseOrder->po_number }}</h1>
    <div class="page-actions">
        {{-- PDF Download --}}
        <a href="{{ route('admin.einkauf.pdf', $purchaseOrder) }}" target="_blank" class="btn btn-sm btn-outline">
            PDF herunterladen
        </a>

        {{-- Email to supplier --}}
        @if($purchaseOrder->isDraft() || $purchaseOrder->status === 'sent')
            @if($purchaseOrder->supplier?->email)
                <form method="POST" action="{{ route('admin.einkauf.send', $purchaseOrder) }}" id="form-send" style="display:inline;">
                    @csrf
                    <button type="button" class="btn btn-sm btn-primary"
                            onclick="confirmAction('form-send', this, 'An {{ addslashes($purchaseOrder->supplier->email) }} senden?')">
                        Per E-Mail senden
                    </button>
                </form>
            @else
                <button class="btn btn-sm btn-outline" disabled title="Lieferant hat keine E-Mail-Adresse">
                    E-Mail (keine Adresse)
                </button>
            @endif
        @endif

        {{-- Edit (draft only) --}}
        @if($purchaseOrder->isDraft())
            <a href="{{ route('admin.einkauf.edit', $purchaseOrder) }}" class="btn btn-sm btn-outline">Bearbeiten</a>
        @endif

        {{-- Cancel --}}
        @if($purchaseOrder->canCancel())
            <form method="POST" action="{{ route('admin.einkauf.cancel', $purchaseOrder) }}" id="form-cancel" style="display:inline;">
                @csrf
                <button type="button" class="btn btn-sm btn-danger"
                        onclick="confirmAction('form-cancel', this, 'Bestellung wirklich stornieren?')">
                    Stornieren
                </button>
            </form>
        @endif

        {{-- Delete (draft only) --}}
        @if($purchaseOrder->isDraft())
            <form method="POST" action="{{ route('admin.einkauf.destroy', $purchaseOrder) }}" id="form-delete" style="display:inline;">
                @csrf
                @method('DELETE')
                <button type="button" class="btn btn-sm btn-danger btn-outline"
                        onclick="confirmAction('form-delete', this, 'Bestellung endgültig löschen?')">
                    Löschen
                </button>
            </form>
        @endif
    </div>
</div>

{{-- Header info --}}
<div class="card mb-3">
    <div class="card-header">Kopfdaten</div>
    <div class="p-3">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div>
                <div class="text-xs text-muted">Lieferant</div>
                <div class="font-bold">{{ $purchaseOrder->supplier->name ?? '—' }}</div>
                @if($purchaseOrder->supplier?->email)
                    <div class="text-xs text-muted">{{ $purchaseOrder->supplier->email }}</div>
                @endif
            </div>
            <div>
                <div class="text-xs text-muted">Bestelldatum</div>
                <div>{{ $purchaseOrder->ordered_at?->format('d.m.Y') ?? '—' }}</div>
            </div>
            <div>
                <div class="text-xs text-muted">Erw. Lieferdatum</div>
                @php
                    $isOverdue = $purchaseOrder->expected_at && $purchaseOrder->expected_at->isPast()
                        && in_array($purchaseOrder->status, ['sent', 'confirmed', 'partially_received']);
                @endphp
                <div class="{{ $isOverdue ? 'text-danger font-bold' : '' }}">
                    {{ $purchaseOrder->expected_at?->format('d.m.Y') ?? '—' }}
                    @if($isOverdue) (überfällig) @endif
                </div>
            </div>
            <div>
                <div class="text-xs text-muted">Status</div>
                @php
                    $badgeClass = match($purchaseOrder->status) {
                        'draft' => 'badge-secondary',
                        'sent' => 'badge-info',
                        'confirmed' => 'badge-info',
                        'partially_received' => 'badge-warning',
                        'received' => 'badge-success',
                        'cancelled' => 'badge-danger',
                        default => '',
                    };
                    $statusLabel = match($purchaseOrder->status) {
                        'draft' => 'Entwurf',
                        'sent' => 'Versendet',
                        'confirmed' => 'Bestätigt',
                        'partially_received' => 'Teillieferung',
                        'received' => 'Eingegangen',
                        'cancelled' => 'Storniert',
                        default => $purchaseOrder->status,
                    };
                @endphp
                <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
            </div>
            <div>
                <div class="text-xs text-muted">Lager</div>
                <div>{{ $purchaseOrder->warehouse->name ?? '—' }}</div>
            </div>
            <div>
                <div class="text-xs text-muted">Gesamtbetrag (netto)</div>
                <div class="font-bold" id="po-total-display">{{ number_format($purchaseOrder->total_milli / 1_000_000, 2, ',', '.') }} &euro;</div>
            </div>
        </div>
        @if($purchaseOrder->notes)
            <div class="mt-3 p-2 bg-muted rounded text-sm">
                <strong>Notiz:</strong> {!! nl2br(e($purchaseOrder->notes)) !!}
            </div>
        @endif
    </div>
</div>

{{-- Items --}}
<div class="card mb-3">
    <div class="card-header">Positionen ({{ $purchaseOrder->items->count() }})</div>
    <div class="table-wrap">
        <table data-col-group="einkauf-items-show">
            <thead>
                <tr>
                    <th style="width:32px"></th>
                    <th>Pos.</th>
                    <th>Art.-Nr.</th>
                    <th>Produkt</th>
                    <th class="text-right">Bestellt</th>
                    <th class="text-right">Geliefert</th>
                    <th class="text-right">EK-Preis</th>
                    <th class="text-right">Gesamt</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="items-tbody">
            @foreach($purchaseOrder->items as $index => $item)
                @php
                    $received = $item->received_qty ?? 0;
                    $complete = $received >= $item->qty;
                    $partial  = $received > 0 && !$complete;
                    $over     = $received > $item->qty;
                @endphp
                <tr data-item-id="{{ $item->id }}">
                    <td class="drag-handle" style="cursor:grab;padding:4px 8px;color:var(--c-muted,#9ca3af);text-align:center;user-select:none" title="Verschieben">⠿</td>
                    <td class="pos-number">{{ $index + 1 }}</td>
                    <td class="font-mono text-sm">
                        @if($item->product_id)
                            <a href="{{ route('admin.products.show', $item->product_id) }}" style="text-decoration:none;color:inherit;border-bottom:1px dashed var(--c-muted)">{{ $item->product->artikelnummer ?? '—' }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if($item->product_id)
                            <a href="{{ route('admin.products.show', $item->product_id) }}" style="text-decoration:none;color:inherit">
                                {{ $item->product->produktname ?? 'Produkt #' . $item->product_id }}
                            </a>
                        @else
                            Produkt #{{ $item->product_id }}
                        @endif
                        @if($item->notes)
                            <div class="text-xs text-muted">{{ $item->notes }}</div>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($item->qty, $item->qty == intval($item->qty) ? 0 : 2, ',', '.') }}</td>
                    <td class="text-right {{ $over ? 'text-warning font-bold' : ($complete ? 'text-success' : ($partial ? 'text-info' : 'text-muted')) }}">
                        {{ $received > 0 ? number_format($received, $received == intval($received) ? 0 : 2, ',', '.') : '—' }}
                        @if($over)
                            <span class="text-xs">(Überlieferung)</span>
                        @endif
                    </td>
                    <td class="text-right" style="padding:0">
                        <input type="text"
                               class="ek-price-input"
                               data-item-id="{{ $item->id }}"
                               data-original="{{ $item->unit_purchase_milli }}"
                               value="{{ number_format($item->unit_purchase_milli / 1_000_000, 4, '.', '') }}"
                               style="width:90px;text-align:right;border:1px solid transparent;border-radius:4px;background:transparent;padding:5px 8px;font-size:inherit;cursor:text;transition:border-color .15s"
                               autocomplete="new-password">
                    </td>
                    <td class="text-right" data-line-total="{{ $item->id }}">{{ number_format($item->line_total_milli / 1_000_000, 2, ',', '.') }} &euro;</td>
                    <td>
                        @if($complete)
                            <span class="badge badge-success">Vollständig</span>
                        @elseif($partial)
                            <span class="badge badge-warning">Teilweise</span>
                        @else
                            <span class="badge badge-secondary">Offen</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
                <tr class="font-bold">
                    <td colspan="6" class="text-right">Gesamtbetrag:</td>
                    <td class="text-right">{{ number_format($purchaseOrder->total_milli / 1_000_000, 2, ',', '.') }} &euro;</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Wareneingang buchen --}}
@if($purchaseOrder->canReceive() || ($purchaseOrder->isDraft() && $purchaseOrder->items->count() > 0))
<div class="card mb-3" id="wareneingang">
    <div class="card-header">Wareneingang buchen</div>
    <form method="POST" action="{{ route('admin.einkauf.wareneingang', $purchaseOrder) }}" class="p-3" id="form-wareneingang">
        @csrf

        <div class="mb-3">
            <label class="text-sm font-bold">Lagerort</label>
            <select name="warehouse_id" class="input" required>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ $wh->id == $purchaseOrder->warehouse_id ? 'selected' : '' }}>
                        {{ $wh->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <table class="w-full mb-3">
            <thead>
                <tr>
                    <th class="text-left text-sm">Produkt</th>
                    <th class="text-right text-sm">Bestellt</th>
                    <th class="text-right text-sm">Bereits geliefert</th>
                    <th class="text-right text-sm">Offen</th>
                    <th class="text-right text-sm" style="width: 140px;">Jetzt geliefert</th>
                </tr>
            </thead>
            <tbody>
            @foreach($purchaseOrder->items as $item)
                @php
                    $received = $item->received_qty ?? 0;
                    $open     = max(0, $item->qty - $received);
                @endphp
                <tr>
                    <td class="py-1">
                        <span class="font-mono text-sm">{{ $item->product->artikelnummer ?? '' }}</span>
                        {{ $item->product->produktname ?? '' }}
                    </td>
                    <td class="text-right py-1">{{ number_format($item->qty, 0, ',', '.') }}</td>
                    <td class="text-right py-1">{{ $received > 0 ? number_format($received, 0, ',', '.') : '—' }}</td>
                    <td class="text-right py-1 {{ $open > 0 ? 'font-bold' : 'text-muted' }}">{{ number_format($open, 0, ',', '.') }}</td>
                    <td class="text-right py-1">
                        <input type="number" name="received[{{ $item->id }}]"
                               value="{{ $open > 0 ? $open : 0 }}"
                               min="0" step="any"
                               class="input input-sm text-right" style="width: 120px;">
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="flex justify-end gap-2">
            <button type="button" class="btn btn-primary"
                    onclick="confirmAction('form-wareneingang', this, 'Lagerbestände werden aktualisiert.')">
                Wareneingang buchen
            </button>
        </div>
    </form>
</div>
@endif

{{-- Portal dropdown for correction product search --}}
<div id="korr-portal" class="dropdown-portal" style="display:none;position:fixed;z-index:9999;max-height:260px;overflow-y:auto"></div>

{{-- Wareneingang korrigieren --}}
@if($purchaseOrder->items->contains(fn($i) => ($i->received_qty ?? 0) > 0))
<div class="card mb-3" id="wareneingang-korrektur">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Wareneingang korrigieren</span>
        <button type="button" class="btn btn-sm btn-outline" onclick="toggleKorrektur(this)">Anzeigen</button>
    </div>
    <div id="korrektur-body" style="display:none">
        <div class="p-3" style="background:#fffbeb;border-bottom:1px solid #fde68a;font-size:.85em;color:#92400e">
            Mengen anpassen: die Differenz wird als Korrektur-Lagerbewegung gebucht. Neue Artikel werden als zusätzliche Positionen erfasst.
        </div>
        <form method="POST" action="{{ route('admin.einkauf.wareneingang.correct', $purchaseOrder) }}" class="p-3" id="form-korrektur">
            @csrf

            <div class="mb-3">
                <label class="text-sm font-bold">Lagerort</label>
                <select name="warehouse_id" class="input" required>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ $wh->id == $purchaseOrder->warehouse_id ? 'selected' : '' }}>
                            {{ $wh->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Existing items --}}
            <p class="text-sm font-bold mb-1">Bestehende Positionen</p>
            <table class="w-full mb-4">
                <thead>
                    <tr>
                        <th class="text-left text-sm">Produkt</th>
                        <th class="text-right text-sm">Bestellt</th>
                        <th class="text-right text-sm" style="width:140px">Bisher geliefert</th>
                        <th class="text-right text-sm" style="width:160px">Korrigierter Gesamtwert</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($purchaseOrder->items as $item)
                    @php $received = $item->received_qty ?? 0; @endphp
                    <tr>
                        <td class="py-1">
                            <span class="font-mono text-sm">{{ $item->product->artikelnummer ?? '' }}</span>
                            {{ $item->product->produktname ?? '' }}
                        </td>
                        <td class="text-right py-1">{{ number_format($item->qty, 0, ',', '.') }}</td>
                        <td class="text-right py-1 text-muted">{{ $received > 0 ? number_format($received, 2, ',', '.') : '—' }}</td>
                        <td class="text-right py-1">
                            <input type="number" name="received[{{ $item->id }}]"
                                   value="{{ number_format($received, 2, '.', '') }}"
                                   min="0" step="any"
                                   class="input input-sm text-right" style="width:140px">
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            {{-- New items --}}
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <p class="text-sm font-bold">Neue Artikel hinzufügen</p>
                <button type="button" class="btn btn-sm btn-outline" onclick="korrAddRow()">+ Artikel</button>
            </div>
            <table class="w-full mb-3" id="korr-new-items-table" style="display:none">
                <thead>
                    <tr>
                        <th class="text-left text-sm" style="width:35%">Produkt</th>
                        <th class="text-right text-sm" style="width:13%">Bestellt</th>
                        <th class="text-right text-sm" style="width:13%">Geliefert</th>
                        <th class="text-right text-sm" style="width:16%">EK-Preis (€)</th>
                        <th style="width:30px"></th>
                    </tr>
                </thead>
                <tbody id="korr-new-body"></tbody>
            </table>

            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-warning"
                        onclick="confirmAction('form-korrektur', this, 'Korrektur-Buchung erstellen?')">
                    Korrektur buchen
                </button>
            </div>
        </form>
    </div>
</div>
@endif

@push('scripts')
<script>
// ── Correction form: toggle ───────────────────────────────────────────────────
function toggleKorrektur(btn) {
    const body = document.getElementById('korrektur-body');
    const visible = body.style.display !== 'none';
    body.style.display = visible ? 'none' : 'block';
    btn.textContent = visible ? 'Anzeigen' : 'Ausblenden';
}

// ── Correction form: new-item product search ──────────────────────────────────
const KORR_SEARCH_URL  = '{{ route('admin.einkauf.product-search') }}';
const KORR_IMPORT_URL  = '{{ route('admin.einkauf.import-wawi') }}';
const KORR_CSRF        = '{{ csrf_token() }}';
const KORR_SUPPLIER_ID = '{{ $purchaseOrder->supplier_id ?? '' }}';
let korrRowIdx         = 0;
let korrHidePortalTimer = null;
let korrSearchTimers   = {};
let korrActiveInput    = null;
const korrPortal       = document.getElementById('korr-portal');

function korrEsc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function korrPositionPortal(inputEl) {
    const r     = inputEl.getBoundingClientRect();
    const maxH  = 260;
    const below = window.innerHeight - r.bottom;
    korrPortal.style.left      = r.left + 'px';
    korrPortal.style.width     = Math.max(r.width, 340) + 'px';
    korrPortal.style.maxHeight = maxH + 'px';
    if (below < maxH && r.top > below) {
        korrPortal.style.top    = (r.top - Math.min(maxH, r.top)) + 'px';
        korrPortal.style.bottom = '';
    } else {
        korrPortal.style.top    = r.bottom + 'px';
        korrPortal.style.bottom = '';
    }
}

function korrHidePortal() {
    clearTimeout(korrHidePortalTimer);
    korrPortal.style.display = 'none';
    korrActiveInput = null;
}

function korrShowPortal(inputEl, results) {
    clearTimeout(korrHidePortalTimer);
    korrActiveInput = inputEl;
    korrPositionPortal(inputEl);
    korrPortal.style.display = 'block';

    if (!results.length) { korrHidePortal(); return; }

    korrPortal.innerHTML = results.map(p => {
        const ekText = p.price_milli > 0 ? (p.price_milli/1e6).toFixed(4).replace('.',',') + ' €' : '';
        if (p.wawi_id && !p.id) {
            return `<div class="product-dropdown-item product-dropdown-wawi" data-wawi-id="${p.wawi_id}" data-label="${korrEsc(p.label)}" data-milli="${p.price_milli}" style="display:flex;align-items:center;gap:8px;padding:7px 12px;cursor:pointer">
                <span style="flex:1;font-size:.88em;color:#888">${korrEsc(p.label)}</span>
                <span class="wawi-badge">WaWi</span>
            </div>`;
        }
        return `<div class="product-dropdown-item" data-id="${p.id}" data-label="${korrEsc(p.label)}" data-milli="${p.price_milli}" style="display:flex;justify-content:space-between;align-items:center;padding:7px 12px;cursor:pointer;font-size:.88em">
            <span>${korrEsc(p.label)}</span>
            ${ekText ? `<span style="color:#888;font-size:.8em;white-space:nowrap;margin-left:8px">EK: ${ekText}</span>` : ''}
        </div>`;
    }).join('');

    korrPortal.querySelectorAll('.product-dropdown-item:not(.product-dropdown-wawi)').forEach(item => {
        item.addEventListener('mousedown', e => { e.preventDefault(); korrSelectProduct(item); });
        item.addEventListener('mouseover', () => {
            korrPortal.querySelectorAll('.product-dropdown-item').forEach(i => i.style.background = '');
            item.style.background = 'var(--c-bg)';
        });
    });
    korrPortal.querySelectorAll('.product-dropdown-wawi').forEach(item => {
        item.addEventListener('mousedown', e => {
            e.preventDefault();
            korrImportWawi(item.dataset.wawiId, item.dataset.label, item.dataset.milli);
        });
        item.addEventListener('mouseover', () => {
            korrPortal.querySelectorAll('.product-dropdown-item').forEach(i => i.style.background = '');
            item.style.background = 'var(--c-bg)';
        });
    });
}

function korrSelectProduct(item) {
    if (!korrActiveInput) return;
    const tr = korrActiveInput.closest('tr');
    tr.querySelector('.korr-product-id').value = item.dataset.id;
    korrActiveInput.value = item.dataset.label;
    if (parseInt(item.dataset.milli) > 0) {
        tr.querySelector('.korr-price').value = (parseInt(item.dataset.milli)/1e6).toFixed(4);
        tr.querySelector('.korr-price-milli').value = item.dataset.milli;
    }
    korrHidePortal();
    tr.querySelector('.korr-qty')?.focus();
}

function korrImportWawi(wawiId, label, priceMilli) {
    if (korrActiveInput) korrActiveInput.value = '…';
    korrHidePortal();
    fetch(KORR_IMPORT_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':KORR_CSRF},
        body: JSON.stringify({wawi_id: wawiId}),
    }).then(r => r.json()).then(data => {
        if (data.id && korrActiveInput) {
            korrSelectProduct({ dataset: { id: data.id, label: data.label, milli: priceMilli } });
        }
    }).catch(() => { if (korrActiveInput) korrActiveInput.value = ''; });
}

document.addEventListener('mousedown', e => {
    if (!korrPortal.contains(e.target) && e.target !== korrActiveInput) korrHidePortal();
});
window.addEventListener('scroll', () => {
    if (korrActiveInput && korrPortal.style.display !== 'none') korrPositionPortal(korrActiveInput);
}, {passive: true});

function korrAddRow() {
    const tbody = document.getElementById('korr-new-body');
    const table = document.getElementById('korr-new-items-table');
    table.style.display = '';
    const i = korrRowIdx++;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td style="padding:3px 4px">
            <input type="hidden" name="new_items[${i}][product_id]" class="korr-product-id" value="">
            <input type="hidden" name="new_items[${i}][unit_purchase_milli]" class="korr-price-milli" value="0">
            <input type="text" class="input input-sm korr-search" placeholder="Produkt suchen…" autocomplete="new-password" style="width:100%">
        </td>
        <td style="padding:3px 4px">
            <input type="number" name="new_items[${i}][qty]" value="1" min="0.001" step="any" class="input input-sm text-right korr-qty" style="width:80px">
        </td>
        <td style="padding:3px 4px">
            <input type="number" name="new_items[${i}][received_qty]" value="1" min="0" step="any" class="input input-sm text-right korr-rcv" style="width:80px">
        </td>
        <td style="padding:3px 4px">
            <input type="text" class="input input-sm text-right korr-price" value="" placeholder="0.0000" style="width:100px">
        </td>
        <td style="padding:3px 4px">
            <button type="button" class="btn btn-xs btn-danger" onclick="this.closest('tr').remove(); if(!document.querySelectorAll('#korr-new-body tr').length) document.getElementById('korr-new-items-table').style.display='none'">×</button>
        </td>
    `;
    tbody.appendChild(tr);
    korrBindSearch(tr);
    tr.querySelector('.korr-search').focus();
}

function korrBindSearch(tr) {
    const searchInput = tr.querySelector('.korr-search');
    searchInput.addEventListener('input', function () {
        const q = this.value.trim();
        clearTimeout(korrSearchTimers[tr.rowIndex]);
        if (q.length < 2) { korrHidePortal(); return; }
        korrSearchTimers[tr.rowIndex] = setTimeout(() => {
            fetch(`${KORR_SEARCH_URL}?q=${encodeURIComponent(q)}&supplier_id=${KORR_SUPPLIER_ID}`)
                .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(results => { if (document.activeElement === searchInput) korrShowPortal(searchInput, results); })
                .catch(() => {});
        }, 250);
    });
    searchInput.addEventListener('focus', function () {
        if (this.value.trim().length >= 2) this.dispatchEvent(new Event('input'));
    });
    searchInput.addEventListener('blur', () => { korrHidePortalTimer = setTimeout(korrHidePortal, 150); });
    searchInput.addEventListener('keydown', function (e) {
        if (korrPortal.style.display !== 'none') {
            const items = korrPortal.querySelectorAll('.product-dropdown-item');
            const active = korrPortal.querySelector('.product-dropdown-item[data-active]');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const next = active ? active.nextElementSibling : items[0];
                if (next) { active?.removeAttribute('data-active'); next.setAttribute('data-active','1'); next.style.background='var(--c-bg)'; }
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prev = active ? active.previousElementSibling : items[items.length-1];
                if (prev) { active?.removeAttribute('data-active'); prev.setAttribute('data-active','1'); prev.style.background='var(--c-bg)'; }
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                const sel = korrPortal.querySelector('[data-active]:not(.product-dropdown-wawi)')
                    || korrPortal.querySelector('.product-dropdown-item:not(.product-dropdown-wawi)');
                if (sel) { korrActiveInput = searchInput; korrSelectProduct(sel); }
                return;
            }
            if (e.key === 'Escape') { korrHidePortal(); return; }
        }
    });
    // price EUR → milli sync
    tr.querySelector('.korr-price').addEventListener('input', function () {
        const eur = parseFloat(this.value.replace(',','.')) || 0;
        tr.querySelector('.korr-price-milli').value = Math.round(eur * 1e6);
    });
}

function confirmAction(formId, btn, message) {
    const existing = btn.parentElement.querySelector('.confirm-inline');
    if (existing) return; // already in confirm state

    const original = btn.textContent.trim();

    // Hide the trigger button
    btn.style.display = 'none';

    // Build confirm row
    const wrap = document.createElement('span');
    wrap.className = 'confirm-inline';
    wrap.style.cssText = 'display:inline-flex;align-items:center;gap:6px';

    const hint = document.createElement('span');
    hint.textContent = message;
    hint.style.cssText = 'font-size:.85em;color:var(--c-muted,#6b7280)';

    const yes = document.createElement('button');
    yes.type = 'button';
    yes.className = 'btn btn-sm btn-danger';
    yes.textContent = 'Ja, bestätigen';
    yes.onclick = function () {
        document.getElementById(formId).submit();
    };

    const no = document.createElement('button');
    no.type = 'button';
    no.className = 'btn btn-sm btn-outline';
    no.textContent = 'Abbrechen';
    no.onclick = function () {
        wrap.remove();
        btn.style.display = '';
    };

    wrap.appendChild(hint);
    wrap.appendChild(yes);
    wrap.appendChild(no);
    btn.parentElement.appendChild(wrap);
}

// ── Inline EK-Preis editing ───────────────────────────────────────────────────
const EK_BASE_URL = '{{ url("admin/einkauf/" . $purchaseOrder->id . "/items") }}';
const EK_CSRF     = '{{ csrf_token() }}';

document.querySelectorAll('.ek-price-input').forEach(input => {
    input.addEventListener('focus', () => {
        input.style.borderColor = 'var(--c-primary, #2563eb)';
        input.style.background  = 'var(--c-surface, #fff)';
    });
    input.addEventListener('blur', () => {
        input.style.borderColor = 'transparent';
        input.style.background  = 'transparent';
        const val = parseFloat(input.value.replace(',', '.'));
        if (isNaN(val) || val < 0) { input.value = (parseInt(input.dataset.original) / 1e6).toFixed(4); return; }
        const newMilli = Math.round(val * 1e6);
        if (newMilli === parseInt(input.dataset.original)) return;
        saveEkPrice(input, newMilli);
    });
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
        if (e.key === 'Escape') { input.value = (parseInt(input.dataset.original) / 1e6).toFixed(4); input.blur(); }
    });
});

function saveEkPrice(input, newMilli) {
    const itemId = input.dataset.itemId;
    input.style.opacity = '0.5';
    fetch(`${EK_BASE_URL}/${itemId}/price`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': EK_CSRF },
        body: JSON.stringify({ unit_purchase_milli: newMilli }),
    })
    .then(r => r.json())
    .then(data => {
        input.style.opacity = '';
        input.dataset.original = data.unit_purchase_milli;
        input.value = (data.unit_purchase_milli / 1e6).toFixed(4);
        // Update line total
        const lineCell = document.querySelector(`[data-line-total="${itemId}"]`);
        if (lineCell) lineCell.textContent = (data.line_total_milli / 1e6).toLocaleString('de-DE', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €';
        // Update PO header total
        const totalDisplay = document.getElementById('po-total-display');
        if (totalDisplay) totalDisplay.textContent = (data.po_total_milli / 1e6).toLocaleString('de-DE', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €';
        // Brief green flash
        input.style.borderColor = '#16a34a'; input.style.background = '#f0fdf4';
        setTimeout(() => { input.style.borderColor = 'transparent'; input.style.background = 'transparent'; }, 1200);
    })
    .catch(() => {
        input.style.opacity = '';
        input.value = (parseInt(input.dataset.original) / 1e6).toFixed(4);
        input.style.borderColor = '#dc2626';
        setTimeout(() => { input.style.borderColor = 'transparent'; }, 2000);
    });
}

// ── Drag-and-drop reordering ──────────────────────────────────────────────────
const REORDER_URL  = '{{ route('admin.einkauf.items.reorder', $purchaseOrder) }}';
const REORDER_CSRF = '{{ csrf_token() }}';

(function loadSortable() {
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js';
    s.onload = function () {
        const tbody = document.getElementById('items-tbody');
        if (!tbody) return;
        Sortable.create(tbody, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function () {
                const ids = [...tbody.querySelectorAll('tr[data-item-id]')]
                    .map(tr => parseInt(tr.dataset.itemId));
                tbody.querySelectorAll('tr[data-item-id]').forEach((tr, i) => {
                    const cell = tr.querySelector('.pos-number');
                    if (cell) cell.textContent = i + 1;
                });
                fetch(REORDER_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': REORDER_CSRF },
                    body: JSON.stringify({ ids }),
                }).catch(() => {});
            },
        });
    };
    document.head.appendChild(s);
})();
</script>
@endpush

@endsection
