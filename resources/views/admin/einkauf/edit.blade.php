@extends('admin.layout')

@section('title', 'Bestellung bearbeiten — ' . $purchaseOrder->po_number)

@section('content')

@if(session('error'))
    <div class="alert alert-danger mb-3">{{ session('error') }}</div>
@endif

<p style="font-size:.85rem;margin-bottom:6px;">
    <a href="{{ route('admin.einkauf.show', $purchaseOrder) }}" class="text-muted">&larr; Zurück zur Bestellung</a>
</p>
<div class="page-header">
    <h1>{{ $purchaseOrder->po_number }} bearbeiten</h1>
</div>

<form method="POST" action="{{ route('admin.einkauf.update', $purchaseOrder) }}" id="po-form">
    @csrf
    @method('PUT')

    <div class="card mb-3">
        <div class="card-header">Kopfdaten</div>
        <div class="p-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div>
                    <label class="text-sm font-bold">Lieferant *</label>
                    <select name="supplier_id" class="input" required id="supplier-select">
                        <option value="">— Lieferant wählen —</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}"
                                    data-filter="{{ $s->po_filter_own_products ? '1' : '0' }}"
                                    {{ old('supplier_id', $purchaseOrder->supplier_id) == $s->id ? 'selected' : '' }}>
                                {{ $s->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-bold">Lager *</label>
                    <select name="warehouse_id" class="input" required>
                        <option value="">— Lager wählen —</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('warehouse_id', $purchaseOrder->warehouse_id) == $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-bold">Bestelldatum</label>
                    <input type="date" name="ordered_at" value="{{ old('ordered_at', $purchaseOrder->ordered_at?->toDateString()) }}" class="input">
                </div>
                <div>
                    <label class="text-sm font-bold">Erw. Lieferdatum</label>
                    <input type="date" name="expected_at" value="{{ old('expected_at', $purchaseOrder->expected_at?->toDateString()) }}" class="input">
                </div>
            </div>
            <div class="mt-3">
                <label class="text-sm font-bold">Notiz</label>
                <textarea name="notes" class="input" rows="2" placeholder="Optionale Bemerkungen...">{{ old('notes', $purchaseOrder->notes) }}</textarea>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px">
            <span>Positionen</span>
            <div style="display:flex;align-items:center;gap:16px">
                <label id="filter-label" style="display:flex;align-items:center;gap:6px;font-size:.85rem;cursor:pointer;font-weight:normal">
                    <input type="checkbox" id="filter-own-cb" style="cursor:pointer">
                    Nur Lieferanten-Produkte
                </label>
                <button type="button" class="btn btn-sm btn-outline" onclick="addItemRow()">+ Position</button>
            </div>
        </div>

        {{-- Schnellerfassung Keyboard-Hints --}}
        <div style="display:flex;gap:16px;align-items:center;padding:7px 14px;background:#f7f5f2;border-bottom:1px solid #e0dcd6;font-size:11px;color:#999;flex-wrap:wrap">
            <span><kbd style="background:#e8e4de;border:1px solid #ccc;border-radius:3px;padding:1px 5px;font-size:10px;font-family:monospace">Enter</kbd>&ensp;nächstes Feld</span>
            <span><kbd style="background:#e8e4de;border:1px solid #ccc;border-radius:3px;padding:1px 5px;font-size:10px;font-family:monospace">↑↓</kbd>&ensp;Vorschläge navigieren</span>
            <span><kbd style="background:#e8e4de;border:1px solid #ccc;border-radius:3px;padding:1px 5px;font-size:10px;font-family:monospace">Backspace</kbd>&ensp;Zeile löschen (leeres Feld)</span>
        </div>

        <div class="table-wrap" id="items-table-wrap">
            <table id="items-table" data-col-group="einkauf-items-edit">
                <thead>
                    <tr>
                        <th style="width:32px"></th>
                        <th style="width:28px;text-align:center;color:#999;font-size:11px;font-weight:400">#</th>
                        <th style="width:110px">Artikel-Nr.</th>
                        <th>Bezeichnung</th>
                        <th class="text-right" style="width:10%">Menge *</th>
                        <th class="text-right" style="width:15%">EK-Preis (€) *</th>
                        <th class="text-right" style="width:9%">Zeile</th>
                        <th style="width:9%">Notiz</th>
                        <th style="width:32px"></th>
                    </tr>
                </thead>
                <tbody id="items-body">
                    @foreach($purchaseOrder->items as $i => $item)
                    @php
                        $artnr    = $item->product->artikelnummer ?? '';
                        $name     = $item->product->produktname ?? '—';
                        $priceEur = number_format($item->unit_purchase_milli / 1000000, 4, '.', '');
                        $resolved = !empty($item->product_id);
                    @endphp
                    <tr data-row="{{ $i }}" data-item-id="{{ $item->id }}">
                        <td class="drag-handle" style="cursor:grab;padding:4px 8px;color:var(--c-muted,#9ca3af);text-align:center;user-select:none" title="Verschieben">⠿</td>
                        <td class="pos-num" style="text-align:center;color:#999;font-size:11px;padding:4px 4px;white-space:nowrap">{{ $i + 1 }}</td>
                        <td style="position:relative;padding-right:4px;min-width:80px">
                            <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $item->product_id }}" class="product-id-input">
                            <input type="text" class="artnr-input"
                                   value="{{ $artnr }}"
                                   autocomplete="new-password"
                                   placeholder="{{ $i === 0 ? 'Artikel-Nr. / Name …' : '' }}"
                                   style="width:100%;border:none;border-bottom:1.5px solid {{ $resolved ? '#1a6ef5' : '#ccc' }};background:transparent;padding:5px 0;font-size:13px;outline:none;font-family:inherit;color:inherit">
                        </td>
                        <td class="product-name-cell" style="color:{{ $resolved ? 'inherit' : '#bbb' }};font-size:13px;padding:5px 8px 5px 4px;min-width:140px">
                            @if($resolved)
                                {{ $name }}
                            @else
                                <em style="font-style:italic">—</em>
                            @endif
                        </td>
                        <td>
                            <input type="number" name="items[{{ $i }}][qty]" value="{{ $item->qty }}"
                                   class="input input-sm text-right qty-input" required min="0.001" step="any"
                                   oninput="recalcRow(this)">
                        </td>
                        <td>
                            <input type="text" class="input input-sm text-right price-eur-input"
                                   value="{{ $priceEur }}" placeholder="0.0000" oninput="recalcRow(this)">
                            <input type="hidden" name="items[{{ $i }}][unit_purchase_milli]" class="price-milli-input" value="{{ $item->unit_purchase_milli }}">
                        </td>
                        <td class="text-right line-total" style="font-size:.85rem;color:var(--c-muted)">—</td>
                        <td>
                            <input type="text" name="items[{{ $i }}][notes]" value="{{ $item->notes }}"
                                   class="input input-sm" placeholder="">
                        </td>
                        <td>
                            <button type="button" class="btn btn-xs btn-danger" onclick="removeRow(this)">×</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @error('items') <div class="text-danger text-sm p-3">{{ $message }}</div> @enderror

        <div style="padding:10px 16px;border-top:1px solid var(--c-border);display:flex;justify-content:flex-end;gap:16px;font-weight:600">
            <span>Gesamt:</span>
            <span id="grand-total">0,00 €</span>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('admin.einkauf.show', $purchaseOrder) }}" class="btn btn-outline">Abbrechen</a>
        <button type="submit" class="btn btn-primary">Änderungen speichern</button>
    </div>
</form>

{{-- Fixed dropdown portal --}}
<div id="product-dropdown-portal" class="dropdown-portal" style="display:none;position:fixed;z-index:9999;max-height:280px;overflow-y:auto;min-width:360px"></div>

<style>
.artnr-input.is-invalid { border-bottom-color: var(--c-danger, #dc2626) !important; }
/* Schnellerfassung: blaue aktive Auswahl im Dropdown */
.product-dropdown-item.active { background: #1a6ef5 !important; color: #fff !important; }
.product-dropdown-item.active .dd-artnr,
.product-dropdown-item.active .dd-unit,
.product-dropdown-item.active .ek { opacity: .85; color: #fff !important; }
</style>

<script>
const SEARCH_URL  = '{{ route('admin.einkauf.product-search') }}';
const FILTER_URL  = '{{ route('admin.einkauf.supplier-filter') }}';
const IMPORT_URL  = '{{ route('admin.einkauf.import-wawi') }}';
const CSRF_TOKEN  = '{{ csrf_token() }}';
let rowIndex      = {{ $purchaseOrder->items->count() }};
let searchTimers  = {};
let activeSearchInput = null;
let hidePortalTimer   = null;

// ── Dropdown portal ──────────────────────────────────────────────────────────

const portal = document.getElementById('product-dropdown-portal');

function showPortal(inputEl, results) {
    clearTimeout(hidePortalTimer);
    activeSearchInput = inputEl;
    positionPortal(inputEl);
    portal.style.display = 'block';

    if (!results.length) { hidePortal(); return; }

    portal.innerHTML = results.map(p => {
        const ekText = p.price_milli > 0
            ? (p.price_milli / 1_000_000).toFixed(4).replace('.', ',') + ' €' : '';
        const artnr    = escHtml(p.artikelnummer || '');
        const namePart = escHtml(p.label.replace(/\s*\[.*/, '').replace(/\s*·.*/, '').trim());

        if (p.wawi_id && !p.id) {
            return `<div class="product-dropdown-item" style="justify-content:flex-start;gap:10px"
                        data-wawi-id="${p.wawi_id}"
                        data-label="${escHtml(p.label)}"
                        data-artnr="${artnr}"
                        data-milli="${p.price_milli}">
                <span class="dd-artnr" style="font-family:monospace;font-size:11px;color:var(--c-muted);min-width:64px;flex-shrink:0">${artnr}</span>
                <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${namePart}</span>
                <span class="wawi-badge" style="flex-shrink:0">WaWi</span>
            </div>`;
        }
        return `<div class="product-dropdown-item" style="justify-content:flex-start;gap:10px"
                    data-id="${p.id}"
                    data-label="${escHtml(p.label)}"
                    data-artnr="${artnr}"
                    data-milli="${p.price_milli}">
            <span class="dd-artnr" style="font-family:monospace;font-size:11px;color:var(--c-muted);min-width:64px;flex-shrink:0">${artnr}</span>
            <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${namePart}</span>
            ${ekText ? `<span class="ek" style="flex-shrink:0">EK: ${ekText}</span>` : ''}
        </div>`;
    }).join('');

    portal.querySelectorAll('.product-dropdown-item').forEach(item => {
        item.addEventListener('mousedown', e => {
            e.preventDefault();
            if (item.dataset.wawiId && !item.dataset.id) {
                importWawi(item.dataset.wawiId, item.dataset.label, item.dataset.milli, item.dataset.artnr);
            } else {
                selectProduct(item);
            }
        });
        item.addEventListener('mouseenter', () => {
            portal.querySelectorAll('.product-dropdown-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
        });
    });
}

function importWawi(wawiId, label, priceMilli, artnr) {
    const savedInput = activeSearchInput;
    if (savedInput) savedInput.value = '…';
    hidePortal();
    fetch(IMPORT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
        body: JSON.stringify({ wawi_id: wawiId }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.id && savedInput) {
            activeSearchInput = savedInput;
            selectProduct({ dataset: {
                id: String(data.id),
                label: data.label || label,
                artnr: data.artikelnummer || artnr || '',
                milli: String(priceMilli),
            }});
        } else if (savedInput) {
            savedInput.value = artnr || '';
        }
    })
    .catch(() => { if (savedInput) savedInput.value = ''; });
}

function positionPortal(inputEl) {
    const rect  = inputEl.getBoundingClientRect();
    const maxH  = 280;
    const below = window.innerHeight - rect.bottom;
    portal.style.left      = rect.left + 'px';
    portal.style.width     = Math.max(rect.width + 200, 360) + 'px';
    portal.style.maxHeight = maxH + 'px';
    if (below < maxH && rect.top > below) {
        portal.style.top    = (rect.top - Math.min(maxH, rect.top)) + 'px';
        portal.style.bottom = '';
    } else {
        portal.style.top    = rect.bottom + 'px';
        portal.style.bottom = '';
    }
}

function hidePortal() {
    clearTimeout(hidePortalTimer);
    portal.style.display  = 'none';
    activeSearchInput     = null;
}

function setResolved(tr, artnr, namePart) {
    const input    = tr.querySelector('.artnr-input');
    const nameCell = tr.querySelector('.product-name-cell');
    if (input) {
        input.value = artnr;
        input.style.borderBottomColor = '#1a6ef5';
        input.classList.remove('is-invalid');
    }
    if (nameCell) {
        nameCell.innerHTML = escHtml(namePart);
        nameCell.style.color   = '';
        nameCell.style.fontStyle = '';
    }
}

function clearResolved(tr) {
    tr.querySelector('.product-id-input').value = '';
    const input    = tr.querySelector('.artnr-input');
    const nameCell = tr.querySelector('.product-name-cell');
    if (input) input.style.borderBottomColor = '#ccc';
    if (nameCell) { nameCell.innerHTML = '<em style="font-style:italic">—</em>'; nameCell.style.color = '#bbb'; }
}

function selectProduct(item) {
    if (!activeSearchInput) return;
    const tr      = activeSearchInput.closest('tr');
    const artnr   = item.dataset.artnr || '';
    const namePart = item.dataset.label.replace(/\s*\[.*/, '').replace(/\s*·.*/, '').trim();

    tr.querySelector('.product-id-input').value = item.dataset.id || '';
    setResolved(tr, artnr, namePart);

    if (parseInt(item.dataset.milli) > 0) {
        const eur = (parseInt(item.dataset.milli) / 1_000_000).toFixed(4);
        tr.querySelector('.price-eur-input').value   = eur;
        tr.querySelector('.price-milli-input').value = item.dataset.milli;
    }
    recalcRow(tr.querySelector('.price-eur-input'));
    hidePortal();
    tr.querySelector('.qty-input')?.select();

    // Auto-append empty row when selecting product in the last row
    const rows = document.querySelectorAll('#items-body tr');
    if (tr === rows[rows.length - 1]) addItemRow(false);
}

document.addEventListener('mousedown', e => {
    if (!portal.contains(e.target) && e.target !== activeSearchInput) hidePortal();
});
window.addEventListener('scroll', () => {
    if (activeSearchInput && portal.style.display !== 'none') positionPortal(activeSearchInput);
}, { passive: true });

// ── Supplier filter ───────────────────────────────────────────────────────────

function getSupplier() { return document.getElementById('supplier-select').value || ''; }

document.getElementById('supplier-select').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    document.getElementById('filter-own-cb').checked = opt.dataset.filter === '1';
});

document.getElementById('filter-own-cb').addEventListener('change', function () {
    const supplierId = getSupplier();
    if (!supplierId) return;
    fetch(FILTER_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
        body: JSON.stringify({ supplier_id: supplierId, enabled: this.checked }),
    }).catch(() => {});
    const opt = document.getElementById('supplier-select').options[document.getElementById('supplier-select').selectedIndex];
    if (opt) opt.dataset.filter = this.checked ? '1' : '0';
});

// ── Row management ────────────────────────────────────────────────────────────

function updatePositionNumbers() {
    document.querySelectorAll('#items-body tr').forEach((tr, i) => {
        const cell = tr.querySelector('.pos-num');
        if (cell) cell.textContent = i + 1;
    });
}

function addItemRow(focusSearch = true) {
    const tbody = document.getElementById('items-body');
    const tr    = document.createElement('tr');
    tr.dataset.row = rowIndex;
    tr.innerHTML   = rowTemplate(rowIndex);
    tbody.appendChild(tr);
    syncNewRowCells(tr);
    bindRowSearch(tr);
    rowIndex++;
    updatePositionNumbers();
    updateGrandTotal();
    if (focusSearch) tr.querySelector('.artnr-input')?.focus();
    return tr;
}

function syncNewRowCells(tr) {
    const thead = document.getElementById('items-table')?.querySelector('thead');
    if (!thead) return;
    const ths = [...thead.querySelectorAll('th')];
    if (!ths.some((th, i) => th.dataset.colOrigIdx !== undefined && parseInt(th.dataset.colOrigIdx) !== i)) return;
    const cells = [...tr.cells];
    ths.forEach(th => {
        const oi = parseInt(th.dataset.colOrigIdx);
        if (!isNaN(oi) && cells[oi]) tr.appendChild(cells[oi]);
    });
}

function removeRow(btnOrTr) {
    const tr   = (btnOrTr instanceof HTMLTableRowElement) ? btnOrTr : btnOrTr.closest('tr');
    const rows = [...document.querySelectorAll('#items-body tr')];
    const idx  = rows.indexOf(tr);
    tr.remove();
    updatePositionNumbers();
    updateGrandTotal();
    hidePortal();
    // Focus previous row's artnr or next row if first was deleted
    const remaining = [...document.querySelectorAll('#items-body tr')];
    const focusIdx  = Math.max(0, Math.min(idx, remaining.length - 1));
    remaining[focusIdx]?.querySelector('.artnr-input')?.focus();
}

function rowTemplate(i) {
    return `
        <td class="drag-handle" style="cursor:grab;padding:4px 8px;color:var(--c-muted,#9ca3af);text-align:center;user-select:none" title="Verschieben">⠿</td>
        <td class="pos-num" style="text-align:center;color:#999;font-size:11px;padding:4px 4px;white-space:nowrap">${i + 1}</td>
        <td style="position:relative;padding-right:4px;min-width:80px">
            <input type="hidden" name="items[${i}][product_id]" class="product-id-input" value="">
            <input type="text" class="artnr-input"
                   placeholder="Artikel-Nr. / Name …"
                   autocomplete="new-password"
                   style="width:100%;border:none;border-bottom:1.5px solid #ccc;background:transparent;padding:5px 0;font-size:13px;outline:none;font-family:inherit;color:inherit">
        </td>
        <td class="product-name-cell" style="color:#bbb;font-size:13px;padding:5px 8px 5px 4px;min-width:140px"><em style="font-style:italic">—</em></td>
        <td>
            <input type="number" name="items[${i}][qty]" value="1"
                   class="input input-sm text-right qty-input" required min="0.001" step="any"
                   oninput="recalcRow(this)">
        </td>
        <td>
            <input type="text" class="input input-sm text-right price-eur-input" value="" placeholder="0.0000"
                   oninput="recalcRow(this)">
            <input type="hidden" name="items[${i}][unit_purchase_milli]" class="price-milli-input" value="0">
        </td>
        <td class="text-right line-total" style="font-size:.85rem;color:var(--c-muted)">—</td>
        <td><input type="text" name="items[${i}][notes]" class="input input-sm" placeholder=""></td>
        <td><button type="button" class="btn btn-xs btn-danger" onclick="removeRow(this)">×</button></td>
    `;
}

// ── Search binding ────────────────────────────────────────────────────────────

function bindRowSearch(tr) {
    const searchInput = tr.querySelector('.artnr-input');
    const qtyInput    = tr.querySelector('.qty-input');

    // ── Article field ──────────────────────────────────────────────────────────

    searchInput.addEventListener('input', function () {
        const q = this.value.trim();
        // Clear resolved state when user edits the field
        if (tr.querySelector('.product-id-input').value) clearResolved(tr);

        clearTimeout(searchTimers[tr.dataset.row]);
        if (q.length < 2) { hidePortal(); return; }

        searchTimers[tr.dataset.row] = setTimeout(() => {
            const filterOwn = document.getElementById('filter-own-cb').checked ? '1' : '0';
            fetch(`${SEARCH_URL}?q=${encodeURIComponent(q)}&supplier_id=${getSupplier()}&filter_own=${filterOwn}`)
                .then(r => r.json())
                .then(results => {
                    if (document.activeElement !== searchInput) return;
                    // Auto-resolve on exact article number match
                    if (results.length > 0 && results[0].artikelnummer === q && results[0].id) {
                        activeSearchInput = searchInput;
                        selectProduct({ dataset: {
                            id:    String(results[0].id),
                            label: results[0].label,
                            artnr: results[0].artikelnummer,
                            milli: String(results[0].price_milli),
                        }});
                        return;
                    }
                    showPortal(searchInput, results);
                });
        }, 250);
    });

    searchInput.addEventListener('focus', function () {
        // Re-open dropdown if field has content but product not yet resolved
        if (this.value.trim().length >= 2 && !tr.querySelector('.product-id-input').value) {
            this.dispatchEvent(new Event('input'));
        }
    });

    searchInput.addEventListener('blur', () => {
        hidePortalTimer = setTimeout(hidePortal, 150);
    });

    searchInput.addEventListener('keydown', function (e) {
        // ── Dropdown navigation ──────────────────────────────────────────────
        if (portal.style.display !== 'none') {
            const items  = portal.querySelectorAll('.product-dropdown-item');
            const active = portal.querySelector('.product-dropdown-item.active');

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const next = active ? active.nextElementSibling : items[0];
                if (next) { active?.classList.remove('active'); next.classList.add('active'); }
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prev = active ? active.previousElementSibling : items[items.length - 1];
                if (prev) { active?.classList.remove('active'); prev.classList.add('active'); }
                return;
            }
            if (e.key === 'Enter' || e.key === 'Tab') {
                const sel = portal.querySelector('.product-dropdown-item.active')
                    || portal.querySelector('.product-dropdown-item');
                if (sel) {
                    e.preventDefault();
                    if (sel.dataset.wawiId && !sel.dataset.id) {
                        importWawi(sel.dataset.wawiId, sel.dataset.label, sel.dataset.milli, sel.dataset.artnr);
                    } else {
                        activeSearchInput = searchInput;
                        selectProduct(sel);
                    }
                }
                return;
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                hidePortal();
                return;
            }
        }

        // ── No dropdown ──────────────────────────────────────────────────────
        if (e.key === 'Enter' || e.key === 'Tab') {
            const productId = tr.querySelector('.product-id-input').value;
            if (productId) {
                e.preventDefault();
                tr.querySelector('.qty-input')?.select();
            }
            return;
        }

        // Backspace on empty article field → delete row (if not the first row)
        if (e.key === 'Backspace' && this.value === '') {
            const rows = [...document.querySelectorAll('#items-body tr')];
            const idx  = rows.indexOf(tr);
            if (idx > 0) {
                e.preventDefault();
                tr.remove();
                updatePositionNumbers();
                updateGrandTotal();
                hidePortal();
                const remaining = [...document.querySelectorAll('#items-body tr')];
                remaining[Math.min(idx - 1, remaining.length - 1)]?.querySelector('.artnr-input')?.focus();
            }
        }
    });

    // ── Quantity field ─────────────────────────────────────────────────────────

    qtyInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            if (!tr.querySelector('.product-id-input')?.value) return;
            const rows  = document.querySelectorAll('#items-body tr');
            const allR  = [...rows];
            if (tr === allR[allR.length - 1]) {
                addItemRow(true);
            } else {
                allR[allR.indexOf(tr) + 1]?.querySelector('.artnr-input')?.focus();
            }
        }
        if (e.key === 'Backspace' && this.value === '') {
            e.preventDefault();
            searchInput.focus();
            searchInput.select();
        }
    });

    // ── Price + notes: Enter → next row ────────────────────────────────────────

    [tr.querySelector('.price-eur-input'), tr.querySelector('input[name*="[notes]"]')]
        .forEach(input => {
            if (!input) return;
            input.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                if (!tr.querySelector('.product-id-input')?.value) return;
                const rows = document.querySelectorAll('#items-body tr');
                const allR = [...rows];
                if (tr === allR[allR.length - 1]) {
                    addItemRow(true);
                } else {
                    allR[allR.indexOf(tr) + 1]?.querySelector('.artnr-input')?.focus();
                }
            });
        });
}

// ── Price calculation ─────────────────────────────────────────────────────────

function recalcRow(inputEl) {
    const tr = inputEl.closest('tr');
    if (!tr) return;
    const eur = parseFloat(tr.querySelector('.price-eur-input').value.replace(',', '.')) || 0;
    const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
    tr.querySelector('.price-milli-input').value = Math.round(eur * 1_000_000);
    tr.querySelector('.line-total').textContent  = (qty * eur) > 0
        ? (qty * eur).toFixed(2).replace('.', ',') + ' €' : '—';
    updateGrandTotal();
}

function updateGrandTotal() {
    let sum = 0;
    document.querySelectorAll('#items-body tr').forEach(tr => {
        sum += (parseFloat(tr.querySelector('.price-eur-input')?.value?.replace(',', '.') || 0))
             * (parseFloat(tr.querySelector('.qty-input')?.value || 0));
    });
    document.getElementById('grand-total').textContent = sum.toFixed(2).replace('.', ',') + ' €';
}

function escHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#items-body tr').forEach(tr => {
        bindRowSearch(tr);
        const priceEur = tr.querySelector('.price-eur-input');
        if (priceEur) recalcRow(priceEur);
    });

    // Init filter checkbox from current supplier selection
    const sel = document.getElementById('supplier-select');
    if (sel.value) {
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('filter-own-cb').checked = opt?.dataset.filter === '1';
    }

    // Form submit: strip empty rows, validate
    document.getElementById('po-form').addEventListener('submit', function (e) {
        document.querySelectorAll('#items-body tr').forEach(tr => {
            if (!tr.querySelector('.product-id-input')?.value) tr.remove();
        });
        let ok = true;
        document.querySelectorAll('#items-body tr').forEach(tr => {
            if (!tr.querySelector('.product-id-input')?.value) {
                tr.querySelector('.artnr-input')?.classList.add('is-invalid');
                ok = false;
            }
        });
        if (!ok) { e.preventDefault(); alert('Bitte bei jeder Position ein Produkt aus der Suche auswählen.'); }
    });

    // ── Drag-and-drop reordering ──────────────────────────────────────────────
    const REORDER_URL  = '{{ route('admin.einkauf.items.reorder', $purchaseOrder) }}';
    const REORDER_CSRF = '{{ csrf_token() }}';
    (function loadSortable() {
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js';
        s.onload = function () {
            const tbody = document.getElementById('items-body');
            if (!tbody) return;
            Sortable.create(tbody, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function () {
                    updatePositionNumbers();
                    const ids = [...tbody.querySelectorAll('tr[data-item-id]')]
                        .map(tr => parseInt(tr.dataset.itemId));
                    if (!ids.length) return;
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

    // Always append one empty row at page load
    addItemRow(false);
});
</script>

@endsection
