/**
 * AdminTable — sortable, filterable, resizable, reorderable table
 * with per-user column preference persistence.
 *
 * Usage:
 *   new AdminTable('my-table-id', { tableKey: 'my-table', skipLastRows: 1 });
 */
class AdminTable {
    constructor(tableId, options = {}) {
        this.table      = document.getElementById(tableId);
        if (!this.table) return;

        this.tableKey   = options.tableKey || tableId;
        this.skipLastRows = options.skipLastRows || 0;

        this.sortCol    = null;
        this.sortDir    = 'asc';
        this.prefs      = { columnOrder: [], columnWidths: {} };
        this.saveTimer  = null;

        this._init();
    }

    _init() {
        this._assignColIds();   // stable data-col="col-N" on every <th>
        this._addFilterRow();
        this._makeSortable();
        this._makeResizable();
        this._makeReorderable();
        this._loadPrefs();
    }

    // ── Stable column identifiers ─────────────────────────────────────────────
    // Assign data-col="col-N" to each header that doesn't already have one.
    // This never changes (survives reorders) so prefs keys stay stable.

    _assignColIds() {
        const headerRow = this.table.tHead?.rows[0];
        if (!headerRow) return;
        Array.from(headerRow.cells).forEach((th, i) => {
            if (!th.dataset.col) th.dataset.col = 'col-' + i;
        });
    }

    _colId(th) {
        return th.dataset.col;
    }

    // ── Filter ────────────────────────────────────────────────────────────────

    _addFilterRow() {
        const thead = this.table.tHead;
        if (!thead) return;
        const headerRow = thead.rows[0];
        const filterRow = thead.insertRow(1);
        filterRow.className = 'table-filter-row';

        Array.from(headerRow.cells).forEach((th, i) => {
            const td = filterRow.insertCell(i);
            if (th.dataset.noFilter !== undefined || th.style.width === '32px') return;
            const inp = document.createElement('input');
            inp.type = 'text';
            inp.placeholder = '…';
            inp.addEventListener('input', () => this._applyFilter());
            td.appendChild(inp);
        });
    }

    _applyFilter() {
        const thead  = this.table.tHead;
        const filterRow = thead.rows[1];
        if (!filterRow) return;
        const inputs = Array.from(filterRow.cells).map(td => {
            const inp = td.querySelector('input');
            return inp ? inp.value.trim().toLowerCase() : '';
        });

        const tbody = this.table.tBodies[0];
        if (!tbody) return;
        const rows = Array.from(tbody.rows);
        const dataRows = this.skipLastRows ? rows.slice(0, -this.skipLastRows) : rows;

        dataRows.forEach(row => {
            const visible = inputs.every((term, i) => {
                if (!term) return true;
                const cell = row.cells[i];
                return cell ? cell.textContent.toLowerCase().includes(term) : true;
            });
            row.style.display = visible ? '' : 'none';
        });
    }

    // ── Sort ──────────────────────────────────────────────────────────────────

    _makeSortable() {
        const thead = this.table.tHead;
        if (!thead) return;
        const headerRow = thead.rows[0];

        Array.from(headerRow.cells).forEach((th) => {
            if (th.dataset.noSort !== undefined) return;
            th.style.cursor = 'pointer';
            th.style.userSelect = 'none';
            const arrow = document.createElement('span');
            arrow.className = 'sort-arrow';
            arrow.style.cssText = 'margin-left:4px;opacity:.4;font-size:10px';
            arrow.textContent = '⇅';
            th.appendChild(arrow);

            th.addEventListener('click', (e) => {
                if (e.target.classList.contains('col-resize-handle')) return;
                // Use th.cellIndex (live) so sort works correctly after reorder
                this._sortBy(th.cellIndex, th, arrow);
            });
        });
    }

    _sortBy(colIdx, th, arrow) {
        if (this.sortCol === colIdx) {
            this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortCol = colIdx;
            this.sortDir = 'asc';
        }

        this.table.tHead.rows[0].querySelectorAll('.sort-arrow').forEach(a => {
            a.textContent = '⇅'; a.style.opacity = '.4';
        });
        arrow.textContent = this.sortDir === 'asc' ? '↑' : '↓';
        arrow.style.opacity = '1';

        const tbody = this.table.tBodies[0];
        if (!tbody) return;
        const rows = Array.from(tbody.rows);
        const dataRows = this.skipLastRows ? rows.slice(0, -this.skipLastRows) : rows;
        const lastRows = this.skipLastRows ? rows.slice(-this.skipLastRows) : [];

        dataRows.sort((a, b) => {
            const aCell = a.cells[colIdx];
            const bCell = b.cells[colIdx];
            const aText = (aCell?.dataset.sort ?? aCell?.textContent ?? '').trim();
            const bText = (bCell?.dataset.sort ?? bCell?.textContent ?? '').trim();
            const aNum  = parseFloat(aText.replace(/[^0-9.,\-]/g, '').replace(',', '.'));
            const bNum  = parseFloat(bText.replace(/[^0-9.,\-]/g, '').replace(',', '.'));
            let cmp;
            if (!isNaN(aNum) && !isNaN(bNum)) {
                cmp = aNum - bNum;
            } else {
                cmp = aText.localeCompare(bText, 'de');
            }
            return this.sortDir === 'asc' ? cmp : -cmp;
        });

        dataRows.forEach(r => tbody.appendChild(r));
        lastRows.forEach(r => tbody.appendChild(r));
    }

    // ── Resize ────────────────────────────────────────────────────────────────

    _makeResizable() {
        const headerRow = this.table.tHead?.rows[0];
        if (!headerRow) return;

        Array.from(headerRow.cells).forEach((th) => {
            if (th.dataset.noResize !== undefined) return;
            th.style.position = 'relative';
            th.style.overflow = 'hidden';

            const handle = document.createElement('div');
            handle.className = 'col-resize-handle';
            handle.style.cssText =
                'position:absolute;right:0;top:0;bottom:0;width:5px;cursor:col-resize;z-index:1';
            th.appendChild(handle);

            let startX, startW;
            handle.addEventListener('mousedown', (e) => {
                e.preventDefault();
                startX = e.pageX;
                startW = th.offsetWidth;

                const onMove = (e) => {
                    th.style.width = Math.max(40, startW + e.pageX - startX) + 'px';
                };
                const onUp = () => {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    // Use stable data-col id so width survives reorders
                    this.prefs.columnWidths[this._colId(th)] = th.style.width;
                    this._savePrefs();
                };
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        });
    }

    // ── Reorder ───────────────────────────────────────────────────────────────

    _makeReorderable() {
        const headerRow = this.table.tHead?.rows[0];
        if (!headerRow) return;

        let dragColId = null; // stable data-col value of dragged header

        Array.from(headerRow.cells).forEach((th) => {
            if (th.dataset.noReorder !== undefined) return;
            th.draggable = true;
            th.style.cursor = 'grab';

            th.addEventListener('dragstart', () => {
                dragColId = this._colId(th); // capture stable id
                th.style.opacity = '.5';
            });
            th.addEventListener('dragend', () => { th.style.opacity = ''; });
            th.addEventListener('dragover', (e) => e.preventDefault());
            th.addEventListener('drop', (e) => {
                e.preventDefault();
                if (!dragColId || dragColId === this._colId(th)) return;
                // Find current cell indices by data-col (live lookup)
                const hRow = this.table.tHead.rows[0];
                const cells = Array.from(hRow.cells);
                const fromIdx = cells.findIndex(c => this._colId(c) === dragColId);
                const toIdx   = th.cellIndex; // live current position
                if (fromIdx < 0) return;
                this._swapColumns(fromIdx, toIdx);
                dragColId = null;
            });
        });
    }

    _swapColumns(fromIdx, toIdx) {
        Array.from(this.table.rows).forEach(row => {
            const cells = Array.from(row.cells);
            if (!cells[fromIdx] || !cells[toIdx]) return;
            const ref = fromIdx < toIdx ? cells[toIdx].nextSibling : cells[toIdx];
            row.insertBefore(cells[fromIdx], ref);
        });

        // Save order as stable data-col ids in their new visual sequence
        this.prefs.columnOrder = Array.from(this.table.tHead.rows[0].cells)
            .map(th => this._colId(th));
        this._savePrefs();
    }

    // ── Persistence ───────────────────────────────────────────────────────────

    _loadPrefs() {
        fetch(`/admin/api/table-prefs/${encodeURIComponent(this.tableKey)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(r => r.json())
        .then(prefs => {
            if (!prefs || typeof prefs !== 'object') return;
            this.prefs = { columnOrder: [], columnWidths: {}, ...prefs };
            this._applyPrefs();
        })
        .catch(() => {});
    }

    _applyPrefs() {
        const headerRow = this.table.tHead?.rows[0];
        if (!headerRow) return;

        // Restore widths — match by stable data-col id
        Object.entries(this.prefs.columnWidths || {}).forEach(([colId, width]) => {
            Array.from(headerRow.cells).forEach(th => {
                if (this._colId(th) === colId) th.style.width = width;
            });
        });

        // Restore column order
        const order = this.prefs.columnOrder || [];
        if (!order.length) return;

        order.forEach((colId, targetIdx) => {
            const hRow    = this.table.tHead.rows[0];
            const cells   = Array.from(hRow.cells);
            const fromIdx = cells.findIndex(th => this._colId(th) === colId);
            if (fromIdx < 0 || fromIdx === targetIdx) return;
            Array.from(this.table.rows).forEach(row => {
                const rowCells = Array.from(row.cells);
                if (!rowCells[fromIdx]) return;
                row.insertBefore(rowCells[fromIdx], rowCells[targetIdx] ?? null);
            });
        });
    }

    _savePrefs() {
        clearTimeout(this.saveTimer);
        this.saveTimer = setTimeout(() => {
            fetch(`/admin/api/table-prefs/${encodeURIComponent(this.tableKey)}`, {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ preferences: this.prefs }),
            }).catch(() => {});
        }, 400);
    }
}
