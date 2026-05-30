@extends('admin.layout')

@section('title', 'Bestellung #' . $order->id . ' bearbeiten')

@section('actions')
    <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline btn-sm">← Zurück</a>
    <a href="{{ route('admin.orders.return-form', $order) }}" class="btn btn-outline btn-sm" target="_blank">↩ Rückgabeformular</a>
@endsection

@section('content')

@if(session('success'))<div class="alert alert-success" style="margin-bottom:12px">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger" style="margin-bottom:12px">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" style="margin-bottom:12px">{{ $errors->first() }}</div>@endif

{{-- ── WAWI-Import ── --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header">Artikel aus WAWI-Auftrag übernehmen</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.orders.wawi-import', $order) }}"
              style="display:flex;gap:8px;align-items:flex-end">
            @csrf
            <div class="form-group" style="margin:0">
                <label style="font-size:12px;color:#6b7280">WAWI Auftragsnummer</label>
                <input type="text" name="wawi_auftragsnr" class="form-control"
                       placeholder="z.B. A250550049" style="width:200px"
                       value="{{ old('wawi_auftragsnr') }}">
            </div>
            <button type="submit" class="btn btn-outline btn-sm">Artikel übernehmen</button>
        </form>
    </div>
</div>

{{-- ── Order meta ── --}}
<div class="meta-grid" style="margin-bottom:16px">
    <div class="meta-item">
        <label>Bestell-ID</label>
        <div class="val">#{{ $order->id }}</div>
    </div>
    <div class="meta-item">
        <label>Status</label>
        <div class="val"><span class="badge badge-{{ $order->status }}">{{ $order->status }}</span></div>
    </div>
    <div class="meta-item">
        <label>Kunde</label>
        <div class="val">
            {{ $order->customer?->first_name }} {{ $order->customer?->last_name }}
            <span class="text-muted" style="font-size:12px"> · {{ $order->customer?->customer_number }}</span>
        </div>
    </div>
</div>

{{-- ── Artikel bearbeiten ── --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header" style="font-weight:600">Positionen bearbeiten</div>
    <form method="POST" action="{{ route('admin.orders.items.update', $order) }}">
        @csrf
        <div class="table-wrap">
            <table id="order-items-edit-table">
                <thead>
                    <tr>
                        <th style="width:32px" data-no-sort data-no-filter data-no-resize data-no-reorder>
                            <input type="checkbox" id="check-all" title="Alle markieren">
                        </th>
                        <th>Artikel-Nr.</th>
                        <th>Bezeichnung</th>
                        <th style="text-align:right">EP (brutto)</th>
                        <th style="text-align:center;width:110px">Menge</th>
                        <th style="text-align:right">Gesamt</th>
                        <th style="width:36px" data-no-sort data-no-filter data-no-resize data-no-reorder></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($order->items as $item)
                    <tr>
                        <td>
                            <input type="checkbox" name="remove[]" value="{{ $item->id }}" class="remove-check">
                        </td>
                        <td><code>{{ $item->artikelnummer_snapshot }}</code></td>
                        <td>{{ $item->product_name_snapshot }}</td>
                        <td style="text-align:right">
                            {{ number_format($item->unit_price_gross_milli / 1_000_000, 2, ',', '.') }} €
                        </td>
                        <td style="text-align:center">
                            <input type="number"
                                   name="qty[{{ $item->id }}]"
                                   value="{{ $item->qty }}"
                                   min="0" max="9999"
                                   style="width:70px;text-align:center;padding:3px 6px">
                        </td>
                        <td style="text-align:right">
                            {{ number_format(($item->unit_price_gross_milli * $item->qty) / 1_000_000, 2, ',', '.') }} €
                        </td>
                        <td style="text-align:center">
                            <button type="button" class="btn-row-delete" data-item-id="{{ $item->id }}"
                                    title="Position entfernen"
                                    style="background:none;border:none;cursor:pointer;color:var(--c-danger,#dc2626);font-size:16px;padding:2px 6px;line-height:1">✕</button>
                        </td>
                    </tr>
                @empty
                    <tr id="empty-row"><td colspan="7" style="color:var(--c-muted);text-align:center">Keine Positionen.</td></tr>
                @endforelse

                {{-- Inline add row (always last, excluded from sort/filter) --}}
                <tr id="add-item-row" data-no-sort data-no-filter>
                    <td></td>
                    {{-- Qty first (Artikel-Nr. column) --}}
                    <td style="padding:5px 6px;vertical-align:top">
                        <input type="number" id="add-qty" value="1" min="1" max="9999"
                               style="width:70px;text-align:center;padding:3px 6px;font-size:12px"
                               title="Menge — Produkt auswählen, Enter = sofort hinzufügen">
                    </td>
                    {{-- Product search (Bezeichnung + EP columns) --}}
                    <td colspan="3" style="position:relative;padding:5px 6px">
                        <input type="text" id="product-search" autocomplete="off"
                               placeholder="Artikel suchen und Enter drücken …"
                               style="width:100%;box-sizing:border-box;font-size:12px">
                        <input type="hidden" id="product-id-hidden">
                        <div id="product-search-results" class="dropdown-portal"
                             style="display:none;position:absolute;z-index:200;left:0;right:0;top:100%;
                                    margin-top:2px;max-height:260px;overflow-y:auto"></div>
                        <div id="add-status-msg"
                             style="font-size:11px;min-height:14px;margin-top:2px"></div>
                    </td>
                    {{-- Button (Gesamt column) --}}
                    <td style="padding:5px 6px;vertical-align:top;white-space:nowrap">
                        <button type="button" id="add-item-btn" class="btn btn-primary btn-sm">+ Hinzufügen</button>
                    </td>
                    <td></td>
                </tr>
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid var(--c-border)">
                        <td colspan="4" style="text-align:right;padding-right:8px;font-size:13px;color:var(--c-muted)">Gesamt VPE:</td>
                        <td style="text-align:center;font-weight:600" id="tfoot-vpe">{{ $order->items->sum('qty') }}</td>
                        <td colspan="2"></td>
                    </tr>
                    <tr style="font-weight:600;border-top:1px solid var(--c-border)">
                        <td colspan="5" style="text-align:right;padding-right:8px">Gesamt brutto:</td>
                        <td style="text-align:right">
                            <span id="tfoot-gross">{{ number_format($order->total_gross_milli / 1_000_000, 2, ',', '.') }} €</span>
                            @if($order->total_pfand_brutto_milli > 0)
                                <br><span id="tfoot-pfand" class="text-muted" style="font-weight:normal;font-size:12px">
                                    + {{ number_format($order->total_pfand_brutto_milli / 1_000_000, 2, ',', '.') }} € Pfand
                                </span>
                            @endif
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-body" style="border-top:1px solid var(--c-border)">
            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Notizen (für den Fahrer)</label>
            <textarea name="notes" rows="12" style="width:100%;resize:vertical;font-size:13px"
                      placeholder="Lieferhinweise, Sonderwünsche …">{{ old('notes', $order->notes) }}</textarea>
        </div>
        <div class="card-body" style="display:flex;gap:.5rem;align-items:center;justify-content:space-between;border-top:1px solid var(--c-border)">
            <p class="text-muted" style="margin:0;font-size:12px">
                Menge = 0 → Position wird entfernt. ✕ löscht die Position sofort.
            </p>
            <button type="submit" class="btn btn-primary">Änderungen speichern</button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
// "Alle markieren" Checkbox
document.getElementById('check-all').addEventListener('change', function() {
    document.querySelectorAll('.remove-check').forEach(cb => cb.checked = this.checked);
});

// ── AJAX Artikel hinzufügen ──
(function () {
    const searchInput  = document.getElementById('product-search');
    const hiddenId     = document.getElementById('product-id-hidden');
    const resultsBox   = document.getElementById('product-search-results');
    const statusMsg    = document.getElementById('add-status-msg');
    const qtyInput     = document.getElementById('add-qty');
    const addBtn       = document.getElementById('add-item-btn');
    const addUrl       = '{{ route('admin.orders.items.add', $order) }}';
    const csrfToken    = document.querySelector('meta[name="csrf-token"]').content;

    let debounceTimer = null;
    let activeIndex   = -1;
    let currentItems  = [];

    // Portal: move dropdown to <body> so .table-wrap overflow-x:auto doesn't clip it
    document.body.appendChild(resultsBox);
    resultsBox.style.position = 'fixed';
    resultsBox.style.right    = 'auto';
    resultsBox.style.margin   = '0';

    function positionDropdown() {
        var rect = searchInput.getBoundingClientRect();
        resultsBox.style.top   = (rect.bottom + 2) + 'px';
        resultsBox.style.left  = rect.left + 'px';
        resultsBox.style.width = rect.width + 'px';
    }

    document.addEventListener('scroll', function () {
        if (resultsBox.style.display !== 'none') positionDropdown();
    }, true);

    function fmt(milli) {
        return (milli / 1_000_000).toFixed(2).replace('.', ',') + ' €';
    }

    function escHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function setStatus(msg, color) {
        statusMsg.textContent  = msg;
        statusMsg.style.color  = color || 'var(--c-muted)';
    }

    function closeResults() {
        resultsBox.style.display = 'none';
        resultsBox.innerHTML = '';
        activeIndex = -1;
        currentItems = [];
    }

    function setActive(idx) {
        resultsBox.querySelectorAll('.product-dropdown-item').forEach(function (r, i) {
            r.classList.toggle('active', i === idx);
        });
        activeIndex = idx;
    }

    function renderResults(items) {
        currentItems = items;
        activeIndex  = -1;
        resultsBox.innerHTML = '';

        if (items.length === 0) {
            resultsBox.innerHTML = '<div class="product-dropdown-empty">Keine Treffer</div>';
            positionDropdown();
            resultsBox.style.display = 'block';
            return;
        }

        items.forEach(function (p, idx) {
            const row = document.createElement('div');
            row.className = 'product-dropdown-item';
            row.innerHTML = escHtml(p.label) +
                (p.price_milli ? '<span style="float:right;color:var(--c-muted,#64748b)">' + fmt(p.price_milli) + '</span>' : '');
            // Click: immediately add
            row.addEventListener('mousedown', function (e) { e.preventDefault(); selectAndAdd(p); });
            row.addEventListener('mouseover', function () { setActive(idx); });
            resultsBox.appendChild(row);
        });

        positionDropdown();
        resultsBox.style.display = 'block';
    }

    // Select a product and immediately submit the add
    function selectAndAdd(p) {
        hiddenId.value     = p.id;
        searchInput.value  = p.label;
        closeResults();
        submitAdd();
    }

    function submitAdd() {
        const productId = hiddenId.value;
        const qty       = parseInt(qtyInput.value, 10);

        if (!productId) {
            setStatus('⚠ Bitte ein Produkt aus der Liste auswählen.', '#dc2626');
            searchInput.focus();
            return;
        }
        if (!qty || qty < 1) {
            setStatus('⚠ Menge ungültig.', '#dc2626');
            qtyInput.focus();
            return;
        }

        addBtn.disabled = true;
        setStatus('…', '#64748b');

        fetch(addUrl, {
            method: 'POST',
            headers: {
                'Content-Type':     'application/json',
                'Accept':           'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':     csrfToken,
            },
            body: JSON.stringify({ product_id: productId, qty: qty }),
        })
        .then(r => r.json())
        .then(function (data) {
            if (data.success) {
                insertRow(data.item);
                updateTotals(data.totals);
                updateVpe();
                resetForm();
                setStatus('✓ Hinzugefügt', '#16a34a');
            } else {
                setStatus('⚠ ' + (data.message || 'Fehler'), '#dc2626');
            }
        })
        .catch(function () {
            setStatus('⚠ Netzwerkfehler', '#dc2626');
        })
        .finally(function () {
            addBtn.disabled = false;
        });
    }

    function insertRow(item) {
        const emptyRow = document.getElementById('empty-row');
        if (emptyRow) emptyRow.remove();

        const tbody  = document.querySelector('#order-items-edit-table tbody');
        const addRow = document.getElementById('add-item-row');
        const gross  = (item.unit_price_gross_milli / 1_000_000).toFixed(2).replace('.', ',');
        const total  = (item.unit_price_gross_milli * item.qty / 1_000_000).toFixed(2).replace('.', ',');

        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td><input type="checkbox" name="remove[]" value="' + item.id + '" class="remove-check"></td>' +
            '<td><code>' + escHtml(item.artikelnummer_snapshot) + '</code></td>' +
            '<td>' + escHtml(item.product_name_snapshot) + '</td>' +
            '<td style="text-align:right">' + gross + ' €</td>' +
            '<td style="text-align:center"><input type="number" name="qty[' + item.id + ']" value="' + item.qty + '" min="0" max="9999" style="width:70px;text-align:center;padding:3px 6px"></td>' +
            '<td style="text-align:right">' + total + ' €</td>' +
            '<td style="text-align:center"><button type="button" class="btn-row-delete" data-item-id="' + item.id + '" title="Position entfernen" style="background:none;border:none;cursor:pointer;color:var(--c-danger,#dc2626);font-size:16px;padding:2px 6px;line-height:1">✕</button></td>';

        tbody.insertBefore(tr, addRow);
    }

    function updateVpe() {
        let total = 0;
        document.querySelectorAll('#order-items-edit-table tbody input[name^="qty["]').forEach(function (input) {
            total += Math.max(0, parseInt(input.value, 10) || 0);
        });
        const el = document.getElementById('tfoot-vpe');
        if (el) el.textContent = total;
    }

    document.querySelector('#order-items-edit-table tbody')
        .addEventListener('input', function (e) {
            if (e.target.matches('input[name^="qty["]')) updateVpe();
        });

    function updateTotals(totals) {
        const grossEl = document.getElementById('tfoot-gross');
        if (grossEl) grossEl.textContent = (totals.gross_milli / 1_000_000).toFixed(2).replace('.', ',') + ' €';
        const pfandEl = document.getElementById('tfoot-pfand');
        if (pfandEl && totals.pfand_milli > 0) {
            pfandEl.textContent = '+ ' + (totals.pfand_milli / 1_000_000).toFixed(2).replace('.', ',') + ' € Pfand';
        }
    }

    function resetForm() {
        hiddenId.value    = '';
        searchInput.value = '';
        qtyInput.value    = '1';
        qtyInput.focus();
        qtyInput.select();
    }

    searchInput.addEventListener('input', function () {
        const q = this.value.trim();
        hiddenId.value = '';
        clearTimeout(debounceTimer);
        if (q.length < 2) { closeResults(); return; }

        debounceTimer = setTimeout(function () {
            fetch('/admin/einkauf/api/product-search?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(items => renderResults(items.filter(p => p.id !== null)))
                .catch(() => closeResults());
        }, 200);
    });

    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown')  { e.preventDefault(); setActive(Math.min(activeIndex + 1, currentItems.length - 1)); }
        else if (e.key === 'ArrowUp')   { e.preventDefault(); setActive(Math.max(activeIndex - 1, 0)); }
        else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIndex >= 0) {
                selectAndAdd(currentItems[activeIndex]);
            } else if (currentItems.length > 0) {
                selectAndAdd(currentItems[0]); // auto-pick first result
            } else if (hiddenId.value) {
                submitAdd();
            }
        }
        else if (e.key === 'Escape')    { closeResults(); }
    });

    searchInput.addEventListener('blur', function () { setTimeout(closeResults, 150); });

    // Enter on qty field moves focus to product search
    qtyInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); searchInput.focus(); searchInput.select(); }
    });

    addBtn.addEventListener('click', submitAdd);

    // ── Per-row delete (✕ button) ──
    document.querySelector('#order-items-edit-table tbody')
        .addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-row-delete');
            if (!btn) return;
            const itemId = btn.dataset.itemId;
            const row    = btn.closest('tr');

            btn.disabled = true;
            fetch('/admin/orders/{{ $order->id }}/items/' + itemId, {
                method:  'DELETE',
                headers: {
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':     csrfToken,
                },
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    row.remove();
                    updateTotals(data.totals);
                    updateVpe();
                } else {
                    btn.disabled = false;
                }
            })
            .catch(function () { btn.disabled = false; });
        });
})();

// ── AdminTable init ──
new AdminTable('order-items-edit-table', {
    tableKey:     'order-items-edit',
    skipLastRows: 1,
});
</script>
@endpush
