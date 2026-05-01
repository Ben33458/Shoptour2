/**
 * Admin Table Enhance
 * ───────────────────
 * Fügt allen Admin-Tabellen automatisch folgende Features hinzu:
 *   • Suche: Filtert sichtbare Zeilen der aktuellen Seite
 *   • Sortierung: Klick auf Spaltentitel → auf-/absteigend
 *   • Spaltenbreite: Zieh-Handle am rechten Rand jeder Spaltenüberschrift
 *   • Spaltenreihenfolge: Drag-and-Drop der Spaltenüberschriften
 *
 * Breiten + Reihenfolge werden im localStorage gespeichert.
 * Keys verwenden data-col-orig-idx (stabile Original-Indices) als Schlüssel,
 * damit Breiten auch nach Reorder dem richtigen Inhalt zugeordnet bleiben.
 *
 * Wird NICHT angewendet auf:
 *   - Tabellen innerhalb von <form> (außer data-col-group ist gesetzt)
 *   - Spalten mit <a>-Sort-Links (server-seitige Sortierung)
 *   - Seiten mit .filter-bar (nur Sortierung wird ergänzt)
 */
(function () {
    'use strict';

    // ── CSS ───────────────────────────────────────────────────────────────────
    var style = document.createElement('style');
    style.textContent = [
        '.col-resize-handle{',
        '  position:absolute;right:0;top:15%;bottom:15%;width:5px;',
        '  cursor:col-resize;z-index:2;user-select:none;',
        '  border-right:2px solid var(--c-border,#e2e8f0);transition:border-color .12s;',
        '}',
        '.col-resize-handle:hover,.col-resize-handle.col-resize-active{',
        '  border-right-color:var(--c-primary,#3b82f6);border-right-width:3px;',
        '}',
        'thead th.col-drop-before{box-shadow:-3px 0 0 0 var(--c-primary,#3b82f6) inset;}',
        'thead th.col-drop-after {box-shadow: 3px 0 0 0 var(--c-primary,#3b82f6) inset;}',
        'thead th.col-dragging{opacity:.4;}',
    ].join('');
    document.head.appendChild(style);

    // ── Helpers ───────────────────────────────────────────────────────────────

    var SKIP_HEADER_TEXTS = ['aktionen', 'aktion', ''];

    function cellText(cell) { return cell ? cell.textContent.trim() : ''; }

    function parseValue(raw) {
        var dm = raw.match(/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/);
        if (dm) {
            var y = parseInt(dm[3], 10);
            if (y < 100) y += 2000;
            return { type: 'date', val: new Date(y, parseInt(dm[2], 10) - 1, parseInt(dm[1], 10)) };
        }
        var clean = raw.replace(/[^\d,.\-]/g, '');
        if (/^\-?\d{1,3}(\.\d{3})*(,\d+)?$/.test(clean)) {
            clean = clean.replace(/\./g, '').replace(',', '.');
        } else {
            clean = clean.replace(',', '.');
        }
        var num = parseFloat(clean);
        if (!isNaN(num)) return { type: 'number', val: num };
        return { type: 'string', val: raw.toLowerCase() };
    }

    function compareValues(aRaw, bRaw, dir) {
        var a = parseValue(aRaw), b = parseValue(bRaw);
        if (a.type === b.type) {
            var r = a.type === 'date'   ? a.val - b.val
                  : a.type === 'number' ? a.val - b.val
                  : a.val.localeCompare(b.val, 'de', { sensitivity: 'base' });
            return dir === 'asc' ? r : -r;
        }
        if (a.type === 'number' && b.type === 'string') return dir === 'asc' ? -1 :  1;
        if (a.type === 'string' && b.type === 'number') return dir === 'asc' ?  1 : -1;
        return 0;
    }

    function getDataRows(tbody) {
        return Array.from(tbody.querySelectorAll('tr')).filter(function (r) {
            return r.cells.length > 1;
        });
    }

    // ── Column reorder (DOM) ──────────────────────────────────────────────────

    function moveColumn(table, fromIdx, toIdx) {
        if (toIdx === fromIdx || toIdx === fromIdx + 1) return;
        Array.from(table.querySelectorAll('tr')).forEach(function (row) {
            var cells = Array.from(row.cells);
            if (fromIdx >= cells.length) return;
            var moving = cells[fromIdx];
            row.removeChild(moving);
            var refIdx = toIdx > fromIdx ? toIdx - 1 : toIdx;
            var fresh  = Array.from(row.cells);
            if (refIdx >= fresh.length) {
                row.appendChild(moving);
            } else {
                row.insertBefore(moving, fresh[refIdx]);
            }
        });
    }

    function saveColOrder(thead, storageKey) {
        var order = Array.from(thead.querySelectorAll('th')).map(function (th) {
            return parseInt(th.dataset.colOrigIdx || '0', 10);
        });
        try { localStorage.setItem(storageKey, JSON.stringify(order)); } catch (e) {}
    }

    function applyColOrder(table, order) {
        Array.from(table.querySelectorAll('tr')).forEach(function (row) {
            var cells = Array.from(row.cells);
            order.forEach(function (origIdx) {
                var cell = cells[origIdx];
                if (cell) row.appendChild(cell);
            });
        });
    }

    // ── initColResize ─────────────────────────────────────────────────────────
    // NOTE: Caller must assign data-col-orig-idx to all <th> elements BEFORE
    // calling this function, and must call initColReorder BEFORE initColResize
    // so that columns are already in their final positions when widths are read.

    function initColResize(table, thead, widthKey) {
        var saved = {};
        try { saved = JSON.parse(localStorage.getItem(widthKey) || '{}'); } catch (e) {}

        // Restore widths using data-col-orig-idx as stable key
        if (Object.keys(saved).length) {
            Array.from(thead.querySelectorAll('th')).forEach(function (th) {
                var oi = th.dataset.colOrigIdx;
                if (oi !== undefined && saved[oi] != null && !th.classList.contains('tbl-filler')) {
                    th.style.width = saved[oi] + 'px';
                }
            });
            table.style.tableLayout = 'fixed';
        }

        function saveWidths() {
            var widths = {};
            Array.from(thead.querySelectorAll('th')).forEach(function (th) {
                var oi = th.dataset.colOrigIdx;
                if (oi !== undefined && !th.classList.contains('tbl-filler')) {
                    widths[oi] = th.offsetWidth;
                }
            });
            try { localStorage.setItem(widthKey, JSON.stringify(widths)); } catch (e) {}
        }

        function lockWidths() {
            if (table.style.tableLayout === 'fixed') return;
            Array.from(thead.querySelectorAll('th')).forEach(function (th) {
                if (!th.classList.contains('tbl-filler')) {
                    th.style.width = th.offsetWidth + 'px';
                }
            });
            table.style.tableLayout = 'fixed';
        }

        var resizeDragging = false;

        Array.from(thead.querySelectorAll('th')).forEach(function (th) {
            if (th.classList.contains('tbl-filler')) return;

            th.style.position     = 'relative';
            th.style.overflow     = 'hidden';
            th.style.textOverflow = 'ellipsis';
            th.style.whiteSpace   = 'nowrap';

            var handle = document.createElement('div');
            handle.className = 'col-resize-handle';
            handle.title = 'Spaltenbreite anpassen';
            th.appendChild(handle);
            th._resizeHandle = handle;

            handle.addEventListener('mousedown', function (e) {
                e.preventDefault();
                e.stopPropagation();
                resizeDragging = true;
                lockWidths();

                var startX = e.clientX;
                var startW = th.offsetWidth;

                handle.classList.add('col-resize-active');
                document.body.style.cursor     = 'col-resize';
                document.body.style.userSelect = 'none';

                function onMove(ev) {
                    th.style.width = Math.max(40, startW + ev.clientX - startX) + 'px';
                }
                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    handle.classList.remove('col-resize-active');
                    document.body.style.cursor     = '';
                    document.body.style.userSelect = '';
                    resizeDragging = false;
                    saveWidths();
                }
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });

            th._isResizeDragging = function () { return resizeDragging; };
        });
    }

    // ── initColReorder ────────────────────────────────────────────────────────
    // NOTE: data-col-orig-idx must be assigned by caller before this is called.

    function initColReorder(table, thead, orderKey) {
        // Restore saved order
        var savedOrder = [];
        try { savedOrder = JSON.parse(localStorage.getItem(orderKey) || '[]'); } catch (e) {}
        if (savedOrder.length && savedOrder.length === thead.querySelectorAll('th').length) {
            applyColOrder(table, savedOrder);
        }

        var dragSrcTh = null;

        function currentThs() { return Array.from(thead.querySelectorAll('th')); }

        currentThs().forEach(function (th) {
            if (th.classList.contains('tbl-filler')) return;

            th.draggable = true;

            th.addEventListener('dragstart', function (e) {
                if (th._isResizeDragging && th._isResizeDragging()) {
                    e.preventDefault(); return;
                }
                dragSrcTh = th;
                th.classList.add('col-dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', '');
            });

            th.addEventListener('dragend', function () {
                th.classList.remove('col-dragging');
                currentThs().forEach(function (t) {
                    t.classList.remove('col-drop-before', 'col-drop-after');
                });
                dragSrcTh = null;
            });

            th.addEventListener('dragover', function (e) {
                if (!dragSrcTh || dragSrcTh === th) return;
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                currentThs().forEach(function (t) {
                    t.classList.remove('col-drop-before', 'col-drop-after');
                });
                var rect = th.getBoundingClientRect();
                th.classList.add(e.clientX < rect.left + rect.width / 2 ? 'col-drop-before' : 'col-drop-after');
            });

            th.addEventListener('dragleave', function () {
                th.classList.remove('col-drop-before', 'col-drop-after');
            });

            th.addEventListener('drop', function (e) {
                e.preventDefault();
                if (!dragSrcTh || dragSrcTh === th) return;
                th.classList.remove('col-drop-before', 'col-drop-after');

                var ths     = currentThs();
                var fromIdx = ths.indexOf(dragSrcTh);
                var dropIdx = ths.indexOf(th);
                var rect    = th.getBoundingClientRect();
                var toIdx   = e.clientX < rect.left + rect.width / 2 ? dropIdx : dropIdx + 1;

                moveColumn(table, fromIdx, toIdx);
                saveColOrder(thead, orderKey);
            });
        });
    }

    // ── assignOrigIdx — must be called before initColReorder / initColResize ──

    function assignOrigIdx(thead) {
        Array.from(thead.querySelectorAll('th')).forEach(function (th, i) {
            if (th.dataset.colOrigIdx === undefined) th.dataset.colOrigIdx = i;
        });
    }

    // ── initTable ─────────────────────────────────────────────────────────────

    function initTable(table, tableIdx) {
        var thead = table.querySelector('thead');
        var tbody = table.querySelector('tbody');
        if (!thead || !tbody) return;

        var ths = Array.from(thead.querySelectorAll('th'));
        if (!ths.length) return;

        var card      = table.closest('.card');
        var tableWrap = table.closest('.table-wrap') || table.parentNode;

        /* ── Search ──────────────────────────────────────────────── */
        var hasFilterBar = (card && card.querySelector('.filter-bar'))
                        || tableWrap.parentNode.querySelector('.filter-bar');

        if (!hasFilterBar) {
            var bar   = document.createElement('div');
            bar.className = 'tbl-enhance-bar';
            var input = document.createElement('input');
            input.type        = 'search';
            input.placeholder = 'Suchen …';
            input.setAttribute('aria-label', 'Tabelle durchsuchen');
            input.className   = 'tbl-enhance-search';

            if (card && card.querySelector('.pagination a')) {
                var hint = document.createElement('span');
                hint.className   = 'tbl-enhance-hint';
                hint.textContent = 'Filtert nur diese Seite';
                bar.appendChild(input);
                bar.appendChild(hint);
            } else {
                bar.appendChild(input);
            }
            tableWrap.parentNode.insertBefore(bar, tableWrap);

            input.addEventListener('input', function () {
                var term = this.value.toLowerCase();
                getDataRows(tbody).forEach(function (row) {
                    row.style.display = (!term || row.textContent.toLowerCase().includes(term)) ? '' : 'none';
                });
            });
        }

        /* ── Sort ────────────────────────────────────────────────── */
        var sortState = { thEl: null, dir: 'asc' };

        ths.forEach(function (th) {
            if (th.querySelector('a')) return;
            var label = th.textContent.trim();
            if (SKIP_HEADER_TEXTS.indexOf(label.toLowerCase()) !== -1) return;

            th.dataset.enhLabel = label;
            th.classList.add('tbl-sortable');
            th.title = 'Klicken zum Sortieren';

            th.addEventListener('click', function () {
                if (sortState.thEl === th) {
                    sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
                } else {
                    sortState.thEl = th;
                    sortState.dir  = 'asc';
                }

                Array.from(thead.querySelectorAll('th')).forEach(function (t) {
                    if (!t.dataset.enhLabel) return;
                    t.textContent = t.dataset.enhLabel;
                    t.classList.remove('tbl-sort-asc', 'tbl-sort-desc');
                    if (t._resizeHandle) t.appendChild(t._resizeHandle);
                });

                var dir = sortState.dir;
                th.textContent = label + (dir === 'asc' ? ' ↑' : ' ↓');
                th.classList.add(dir === 'asc' ? 'tbl-sort-asc' : 'tbl-sort-desc');
                if (th._resizeHandle) th.appendChild(th._resizeHandle);

                var idx = Array.from(thead.querySelectorAll('th')).indexOf(th);

                var rows = getDataRows(tbody);
                rows.sort(function (a, b) {
                    return compareValues(cellText(a.cells[idx]), cellText(b.cells[idx]), dir);
                });
                rows.forEach(function (row) { tbody.appendChild(row); });
            });
        });

        /* ── Filler column ───────────────────────────────────────── */
        if (!thead.querySelector('th.tbl-filler')) {
            Array.from(thead.querySelectorAll('tr')).forEach(function (tr) {
                var f = document.createElement('th'); f.className = 'tbl-filler'; tr.appendChild(f);
            });
            Array.from(tbody.querySelectorAll('tr')).forEach(function (row) {
                var f = document.createElement('td'); f.className = 'tbl-filler'; row.appendChild(f);
            });
            ths = Array.from(thead.querySelectorAll('th'));
        }

        /* ── Column resize + reorder ─────────────────────────────── */
        var base = table._colKeyBase || ('kolabri-col:' + location.pathname + ':' + tableIdx);

        // Assign stable indices, then reorder (restores saved order), then widths
        assignOrigIdx(thead);
        initColReorder(table, thead, base + ':o');
        initColResize (table, thead, base + ':w');
    }

    // ── initColResizeReorderOnly (form tables opted-in via data-col-group) ────

    function initColResizeReorderOnly(table) {
        var group = table.dataset.colGroup;
        var thead = table.querySelector('thead');
        var tbody = table.querySelector('tbody');
        if (!thead || !group) return;

        // Add filler if missing
        if (!thead.querySelector('th.tbl-filler')) {
            Array.from(thead.querySelectorAll('tr')).forEach(function (tr) {
                var f = document.createElement('th'); f.className = 'tbl-filler'; tr.appendChild(f);
            });
            if (tbody) {
                Array.from(tbody.querySelectorAll('tr')).forEach(function (row) {
                    var f = document.createElement('td'); f.className = 'tbl-filler'; row.appendChild(f);
                });
            }
        }

        var base = 'kolabri-col:' + group;

        // Assign stable indices, then reorder, then widths (same order as initTable)
        assignOrigIdx(thead);
        initColReorder(table, thead, base + ':o');
        initColResize (table, thead, base + ':w');
    }

    // ── Bootstrap ─────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        // Non-form tables: full treatment (search + sort + resize + reorder)
        var tableIdx = 0;
        document.querySelectorAll('.content table').forEach(function (table) {
            if (table.closest('form')) return;
            if (table.parentNode.closest('table, td, th')) return;
            if (!table.querySelector('thead')) return;
            table._colKeyBase = table.dataset.colGroup
                ? 'kolabri-col:' + table.dataset.colGroup
                : null;
            initTable(table, tableIdx++);
        });

        // Form tables with data-col-group: resize + reorder only
        document.querySelectorAll('.content form table[data-col-group]').forEach(function (table) {
            if (table.parentNode.closest('table, td, th')) return;
            if (!table.querySelector('thead')) return;
            initColResizeReorderOnly(table);
        });
    });
}());
