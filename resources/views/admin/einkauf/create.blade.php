@extends('admin.layout')

@section('title', 'Neue Einkaufsbestellung')

@section('content')

@if(session('error'))
    <div class="alert alert-danger mb-3">{{ session('error') }}</div>
@endif

<div class="mb-3">
    <a href="{{ route('admin.einkauf.index') }}" class="text-sm text-muted">&larr; Zurück zur Übersicht</a>
    <h2 class="mt-1">Neue Einkaufsbestellung</h2>
</div>

<form method="POST" action="{{ route('admin.einkauf.store') }}" id="po-form">
    @csrf

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
                                    {{ old('supplier_id', request('supplier_id')) == $s->id ? 'selected' : '' }}>
                                {{ $s->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('supplier_id') <div class="text-danger text-xs mt-1">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="text-sm font-bold">Lager *</label>
                    <select name="warehouse_id" class="input" required>
                        <option value="">— Lager wählen —</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('warehouse_id') <div class="text-danger text-xs mt-1">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="text-sm font-bold">Bestelldatum</label>
                    <input type="date" name="ordered_at" value="{{ old('ordered_at', now()->toDateString()) }}" class="input">
                </div>
                <div>
                    <label class="text-sm font-bold">Erw. Lieferdatum</label>
                    <input type="date" name="expected_at" value="{{ old('expected_at') }}" class="input">
                </div>
            </div>
            <div class="mt-3">
                <label class="text-sm font-bold">Notiz</label>
                <textarea name="notes" class="input" rows="2" placeholder="Optionale Bemerkungen...">{{ old('notes') }}</textarea>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px">
            <span>Positionen</span>
            <div style="display:flex;align-items:center;gap:16px">
                {{-- Filter-Checkbox --}}
                <label id="filter-label" style="display:none;align-items:center;gap:6px;font-size:.85rem;cursor:pointer;font-weight:normal">
                    <input type="checkbox" id="filter-own-cb" style="cursor:pointer">
                    Nur Lieferanten-Produkte
                </label>
                <button type="button" class="btn btn-sm btn-outline" onclick="addItemRow()">+ Position</button>
            </div>
        </div>
        <div class="table-wrap" id="items-table-wrap">
            <table id="items-table">
                <thead>
                    <tr>
                        <th style="width:42%">Produkt *</th>
                        <th class="text-right" style="width:12%">Menge *</th>
                        <th class="text-right" style="width:18%">EK-Preis (€) *</th>
                        <th class="text-right" style="width:14%">Zeile</th>
                        <th style="width:9%">Notiz</th>
                        <th style="width:5%"></th>
                    </tr>
                </thead>
                <tbody id="items-body">
                    @if(!empty($prefillItems))
                        @foreach($prefillItems as $i => $item)
                        <tr data-row="{{ $i }}">
                            @php $priceEur = isset($item['unit_purchase_milli']) ? number_format($item['unit_purchase_milli'] / 1000000, 4, '.', '') : '' @endphp
                            <td style="position:static">
                                <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $item['product_id'] ?? '' }}" class="product-id-input">
                                <input type="text" class="input input-sm product-search-input"
                                       placeholder="Produkt suchen..."
                                       value="{{ $item['product_label'] ?? ('Produkt #' . ($item['product_id'] ?? '')) }}"
                                       autocomplete="new-password">
                            </td>
                            <td>
                                <input type="number" name="items[{{ $i }}][qty]" value="{{ $item['qty'] ?? 1 }}"
                                       class="input input-sm text-right qty-input" required min="0.001" step="any"
                                       oninput="recalcRow(this)">
                            </td>
                            <td>
                                <input type="text" class="input input-sm text-right price-eur-input"
                                       value="{{ $priceEur }}" placeholder="0.0000"
                                       oninput="recalcRow(this)">
                                <input type="hidden" name="items[{{ $i }}][unit_purchase_milli]" class="price-milli-input" value="{{ $item['unit_purchase_milli'] ?? 0 }}">
                            </td>
                            <td class="text-right line-total" style="font-size:.85rem;color:var(--c-muted)">—</td>
                            <td>
                                <input type="text" name="items[{{ $i }}][notes]" value="{{ $item['notes'] ?? '' }}"
                                       class="input input-sm" placeholder="">
                            </td>
                            <td>
                                <button type="button" class="btn btn-xs btn-danger" onclick="removeRow(this)">×</button>
                            </td>
                        </tr>
                        @endforeach
                    @endif
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
        <a href="{{ route('admin.einkauf.index') }}" class="btn btn-outline">Abbrechen</a>
        <button type="submit" class="btn btn-primary" id="submit-btn">Bestellung erstellen</button>
    </div>
</form>

{{-- Fixed dropdown portal rendered outside any overflow container --}}
<div id="product-dropdown-portal" class="dropdown-portal" style="display:none;position:fixed;z-index:9999;max-height:260px;overflow-y:auto;min-width:320px"></div>

<script>
const SEARCH_URL    = '{{ route('admin.einkauf.product-search') }}';
const FILTER_URL    = '{{ route('admin.einkauf.supplier-filter') }}';
const IMPORT_URL    = '{{ route('admin.einkauf.import-wawi') }}';
const CSRF_TOKEN    = '{{ csrf_token() }}';
let rowIndex        = {{ !empty($prefillItems) ? count($prefillItems) : 0 }};
let searchTimers    = {};
let hidePortalTimer = null;
let activeSearchInput = null;

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
            ? (p.price_milli / 1000000).toFixed(4).replace('.', ',') + ' €' : '';
        if (p.wawi_id && !p.id) {
            return `<div class="product-dropdown-item product-dropdown-wawi" data-wawi-id="${p.wawi_id}" data-label="${escHtml(p.label)}" data-milli="${p.price_milli}">
                <span style="flex:1;color:var(--c-muted,#888)">${escHtml(p.label)}</span>
                <span class="wawi-badge">WaWi</span>
            </div>`;
        }
        return `<div class="product-dropdown-item" data-id="${p.id}" data-label="${escHtml(p.label)}" data-milli="${p.price_milli}">
            <span>${escHtml(p.label)}</span>
            ${ekText ? `<span class="ek">EK: ${ekText}</span>` : ''}
        </div>`;
    }).join('');

    portal.querySelectorAll('.product-dropdown-item:not(.product-dropdown-wawi)').forEach(item => {
        item.addEventListener('mousedown', e => { e.preventDefault(); selectProduct(item); });
    });
    portal.querySelectorAll('.product-dropdown-wawi').forEach(item => {
        item.addEventListener('mousedown', e => {
            e.preventDefault();
            importWawi(item.dataset.wawiId, item.dataset.label, item.dataset.milli);
        });
    });
}

function importWawi(wawiId, label, priceMilli) {
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
            selectProduct({ dataset: { id: data.id, label: data.label, milli: priceMilli } });
        } else if (savedInput) {
            savedInput.value = label;
        }
    })
    .catch(() => { if (savedInput) savedInput.value = ''; });
}

function hidePortal() {
    clearTimeout(hidePortalTimer);
    portal.style.display = 'none';
    activeSearchInput = null;
}

function selectProduct(item) {
    if (!activeSearchInput) return;
    const tr = activeSearchInput.closest('tr');
    tr.querySelector('.product-id-input').value  = item.dataset.id;
    activeSearchInput.value                       = item.dataset.label;
    activeSearchInput.classList.remove('is-invalid');

    if (parseInt(item.dataset.milli) > 0) {
        const eur = (parseInt(item.dataset.milli) / 1000000).toFixed(4);
        tr.querySelector('.price-eur-input').value   = eur;
        tr.querySelector('.price-milli-input').value = item.dataset.milli;
    }
    recalcRow(tr.querySelector('.price-eur-input'));
    hidePortal();
    tr.querySelector('.qty-input')?.select();
}

// Close when clicking outside
document.addEventListener('mousedown', e => {
    if (!portal.contains(e.target) && e.target !== activeSearchInput) {
        hidePortal();
    }
});

function positionPortal(inputEl) {
    const rect = inputEl.getBoundingClientRect();
    const maxH  = 260;
    const below = window.innerHeight - rect.bottom;
    portal.style.left      = rect.left + 'px';
    portal.style.width     = Math.max(rect.width, 340) + 'px';
    portal.style.maxHeight = maxH + 'px';
    if (below < maxH && rect.top > below) {
        portal.style.top    = (rect.top - Math.min(maxH, rect.top)) + 'px';
        portal.style.bottom = '';
    } else {
        portal.style.top    = rect.bottom + 'px';
        portal.style.bottom = '';
    }
}

window.addEventListener('scroll', () => {
    if (activeSearchInput && portal.style.display !== 'none') positionPortal(activeSearchInput);
}, { passive: true });

// ── Supplier & filter checkbox ────────────────────────────────────────────────

function getSupplier() {
    return document.getElementById('supplier-select').value || '';
}

document.getElementById('supplier-select').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const filterOwn = opt.dataset.filter === '1';
    const cb = document.getElementById('filter-own-cb');
    cb.checked = filterOwn;
    document.getElementById('filter-label').style.display = this.value ? 'flex' : 'none';
});

document.getElementById('filter-own-cb').addEventListener('change', function () {
    const supplierId = getSupplier();
    if (!supplierId) return;

    fetch(FILTER_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
        body: JSON.stringify({ supplier_id: supplierId, enabled: this.checked }),
    }).catch(() => {});

    // Update the option's data-filter so it persists if supplier is re-selected
    const opt = document.getElementById('supplier-select').options[document.getElementById('supplier-select').selectedIndex];
    opt.dataset.filter = this.checked ? '1' : '0';
});

// ── Row management ────────────────────────────────────────────────────────────

function addItemRow() {
    const tbody = document.getElementById('items-body');
    const tr    = document.createElement('tr');
    tr.dataset.row = rowIndex;
    tr.innerHTML   = rowTemplate(rowIndex);
    tbody.appendChild(tr);
    bindRowSearch(tr);
    rowIndex++;
    updateGrandTotal();
    tr.querySelector('.product-search-input').focus();
}

function removeRow(btn) {
    btn.closest('tr').remove();
    updateGrandTotal();
    hidePortal();
}

function rowTemplate(i) {
    return `
        <td>
            <input type="hidden" name="items[${i}][product_id]" class="product-id-input" value="">
            <input type="text" class="input input-sm product-search-input" placeholder="Produkt suchen…" autocomplete="new-password">
        </td>
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
    const searchInput = tr.querySelector('.product-search-input');

    searchInput.addEventListener('input', function () {
        const q = this.value.trim();
        const rowKey = tr.dataset.row;
        clearTimeout(searchTimers[rowKey]);
        if (q.length < 2) { hidePortal(); return; }

        searchTimers[rowKey] = setTimeout(() => {
            const filterOwn = document.getElementById('filter-own-cb').checked ? '1' : '0';
            fetch(`${SEARCH_URL}?q=${encodeURIComponent(q)}&supplier_id=${getSupplier()}&filter_own=${filterOwn}`)
                .then(r => r.json())
                .then(results => {
                    if (document.activeElement === searchInput) {
                        showPortal(searchInput, results);
                    }
                });
        }, 250);
    });

    searchInput.addEventListener('focus', function () {
        if (this.value.trim().length >= 2) {
            // Re-trigger search on focus
            this.dispatchEvent(new Event('input'));
        }
    });

    searchInput.addEventListener('blur', () => { hidePortalTimer = setTimeout(hidePortal, 150); });

    searchInput.addEventListener('keydown', function (e) {
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
            if (e.key === 'Enter') {
                e.preventDefault();
                const sel = portal.querySelector('.product-dropdown-item.active:not(.product-dropdown-wawi)')
                    || portal.querySelector('.product-dropdown-item:not(.product-dropdown-wawi)');
                if (sel) { activeSearchInput = searchInput; selectProduct(sel); }
                return;
            }
            if (e.key === 'Escape') { hidePortal(); return; }
        }
    });

    // Enter on qty/price/notes → add next row if last, else move to next
    const qtyInput = tr.querySelector('.qty-input');
    [qtyInput, tr.querySelector('.price-eur-input'), tr.querySelector('input[name*="[notes]"]')]
        .forEach(input => {
            if (!input) return;
            input.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                if (!tr.querySelector('.product-id-input')?.value) return;
                const rows = document.querySelectorAll('#items-body tr');
                if (tr === rows[rows.length - 1]) {
                    addItemRow();
                } else {
                    const allRows = [...rows];
                    allRows[allRows.indexOf(tr) + 1]?.querySelector('.product-search-input')?.focus();
                }
            });
        });
}

// ── Price calculation ─────────────────────────────────────────────────────────

function recalcRow(inputEl) {
    const tr = inputEl.closest('tr');
    if (!tr) return;
    const eurInput   = tr.querySelector('.price-eur-input');
    const milliInput = tr.querySelector('.price-milli-input');
    const qtyInput   = tr.querySelector('.qty-input');
    const lineTotal  = tr.querySelector('.line-total');

    const eur   = parseFloat(eurInput.value.replace(',', '.')) || 0;
    milliInput.value = Math.round(eur * 1000000);

    const qty   = parseFloat(qtyInput.value) || 0;
    const total = qty * eur;
    lineTotal.textContent = total > 0 ? total.toFixed(2).replace('.', ',') + ' €' : '—';
    updateGrandTotal();
}

function updateGrandTotal() {
    let sum = 0;
    document.querySelectorAll('#items-body tr').forEach(tr => {
        const eur = parseFloat(tr.querySelector('.price-eur-input')?.value?.replace(',', '.') || 0);
        const qty = parseFloat(tr.querySelector('.qty-input')?.value || 0);
        sum += eur * qty;
    });
    document.getElementById('grand-total').textContent = sum.toFixed(2).replace('.', ',') + ' €';
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#items-body tr').forEach(tr => {
        bindRowSearch(tr);
        const priceEur = tr.querySelector('.price-eur-input');
        if (priceEur) recalcRow(priceEur);
    });

    if (document.querySelectorAll('#items-body tr').length === 0) {
        addItemRow();
    }

    // Init filter checkbox from pre-selected supplier (e.g. from Bestellvorschläge)
    const supplierSel = document.getElementById('supplier-select');
    if (supplierSel.value) {
        const opt = supplierSel.options[supplierSel.selectedIndex];
        document.getElementById('filter-own-cb').checked = opt.dataset.filter === '1';
        document.getElementById('filter-label').style.display = 'flex';
    }

    document.getElementById('po-form').addEventListener('submit', function (e) {
        // Leere Zeilen am Ende still entfernen
        document.querySelectorAll('#items-body tr').forEach(tr => {
            if (!tr.querySelector('.product-id-input')?.value) tr.remove();
        });
        // Verbleibende Zeilen validieren
        let ok = true;
        document.querySelectorAll('#items-body tr').forEach(tr => {
            if (!tr.querySelector('.product-id-input')?.value) {
                tr.querySelector('.product-search-input')?.classList.add('is-invalid');
                ok = false;
            }
        });
        if (!ok) {
            e.preventDefault();
            alert('Bitte bei jeder Position ein Produkt aus der Suche auswählen.');
        }
    });
});
</script>

@endsection
