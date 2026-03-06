/**
 * WP-20 – Inline-Edit für Admin-Tabellen (Excel-Stil)
 *
 * Verwendung im Blade:
 *   <script src="{{ asset('admin/inline-edit.js') }}" defer></script>
 *
 * HTML-Konventionen:
 *   <tr data-ie-url="/admin/brands/5">
 *     <td data-ie-field="name"
 *         data-ie-type="text"          <!-- text | number | money | select | checkbox -->
 *         data-ie-value="Paulaner">    <!-- aktueller Wert (für money: EUR als Float-String) -->
 *       Paulaner
 *     </td>
 *   </tr>
 *
 *   Bei type="select":
 *     data-ie-options='[{"value":"1","label":"Paulaner"},...]'
 *
 *   Bei type="money":
 *     data-ie-value="2.50"   (EUR-Wert, Server rechnet ×1 000 000 → milli)
 *     Anzeige im Cell: formatiert als "2,50 €"
 *
 *   Bei type="checkbox":
 *     data-ie-value="1" oder data-ie-value="0"
 *     Sendet direkt "1" oder "0" (kein input erforderlich — Toggle per Klick)
 *
 * Server-Antwort:
 *   JSON { "ok": true }                   → Erfolg
 *   JSON { "errors": { field: [...] } }   → Validierungsfehler
 *   Andere Fehler → Revert + console.error
 */
(function () {
    'use strict';

    // ── CSRF-Token aus dem <meta>-Tag ──────────────────────────────────────────
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // ── Formatierung ──────────────────────────────────────────────────────────
    function formatMoney(eurValue) {
        return parseFloat(eurValue).toLocaleString('de-DE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }) + ' €';
    }

    // ── Feedback-Flash ────────────────────────────────────────────────────────
    function flash(cell, success, message) {
        const el = document.createElement('span');
        el.textContent = success ? ' ✓' : (' ' + (message || '✗'));
        el.style.cssText = `color:${success ? 'var(--c-success,#2d9e52)' : 'var(--c-danger,#d94f4f)'};font-size:.8rem;margin-left:4px`;
        cell.appendChild(el);
        setTimeout(() => el.remove(), 2000);
    }

    // ── PATCH via fetch ────────────────────────────────────────────────────────
    async function patchField(url, field, value) {
        const res = await fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ [field]: value }),
        });

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
            throw new Error(firstError ?? `HTTP ${res.status}`);
        }

        return res.json();
    }

    // ── Zelle aktivieren ──────────────────────────────────────────────────────
    function activateCell(cell) {
        if (cell.dataset.ieEditing) return;

        const type      = cell.dataset.ieType   || 'text';
        const field     = cell.dataset.ieField;
        const value     = cell.dataset.ieValue  ?? '';
        const row       = cell.closest('[data-ie-url]');
        const url       = row?.dataset.ieUrl;

        if (!field || !url) return;

        // Checkbox: Toggle direkt, kein Input nötig
        if (type === 'checkbox') {
            const newVal = value === '1' ? '0' : '1';
            const originalText = cell.innerHTML;
            cell.dataset.ieValue = newVal;
            cell.innerHTML = newVal === '1' ? '✓' : '–';
            patchField(url, field, newVal)
                .then(() => flash(cell, true))
                .catch(err => {
                    cell.dataset.ieValue = value;
                    cell.innerHTML = originalText;
                    flash(cell, false, err.message);
                });
            return;
        }

        // Snapshot zum Revert
        const originalText  = cell.textContent.trim();
        const originalValue = value;

        cell.dataset.ieEditing = '1';
        cell.style.padding = '0';

        let input;

        if (type === 'select') {
            const options = JSON.parse(cell.dataset.ieOptions || '[]');
            input = document.createElement('select');
            input.style.cssText = 'width:100%;height:100%;border:none;outline:2px solid var(--c-primary,#5c8af5);background:#fff;padding:4px 6px;border-radius:4px;font-size:inherit';
            options.forEach(opt => {
                const o = document.createElement('option');
                o.value = opt.value;
                o.textContent = opt.label;
                if (String(opt.value) === String(value)) o.selected = true;
                input.appendChild(o);
            });
        } else {
            input = document.createElement('input');
            input.type  = (type === 'money' || type === 'number') ? 'number' : 'text';
            input.step  = (type === 'money') ? '0.01' : (type === 'number' ? '1' : undefined);
            input.value = value;
            input.style.cssText = 'width:100%;height:100%;border:none;outline:2px solid var(--c-primary,#5c8af5);background:#fff;padding:4px 6px;border-radius:4px;font-size:inherit;box-sizing:border-box';
        }

        cell.innerHTML = '';
        cell.appendChild(input);
        input.focus();
        if (input.select) input.select();

        // ── Speichern ──────────────────────────────────────────────────────────
        const save = async () => {
            const newValue = input.value;
            delete cell.dataset.ieEditing;
            cell.style.padding = '';

            if (String(newValue) === String(originalValue)) {
                // Unverändert — einfach Anzeige wiederherstellen
                if (type === 'money') {
                    cell.textContent = formatMoney(originalValue);
                } else if (type === 'select') {
                    const chosen = Array.from(input.options).find(o => o.selected);
                    cell.textContent = chosen?.textContent ?? originalText;
                    cell.dataset.ieValue = chosen?.value ?? originalValue;
                } else {
                    cell.textContent = originalText;
                }
                return;
            }

            try {
                await patchField(url, field, newValue);

                // Anzeige aktualisieren
                cell.dataset.ieValue = newValue;
                if (type === 'money') {
                    cell.textContent = formatMoney(newValue);
                } else if (type === 'select') {
                    const chosen = Array.from(input.options).find(o => o.selected);
                    cell.textContent = chosen?.textContent ?? newValue;
                    cell.dataset.ieValue = chosen?.value ?? newValue;
                } else {
                    cell.textContent = newValue;
                }
                flash(cell, true);
            } catch (err) {
                // Revert
                cell.dataset.ieValue = originalValue;
                if (type === 'money') {
                    cell.textContent = formatMoney(originalValue);
                } else if (type === 'select') {
                    cell.textContent = originalText;
                } else {
                    cell.textContent = originalText;
                }
                flash(cell, false, err.message);
                console.error('[inline-edit]', err);
            }
        };

        // ── Tab: zur nächsten editierbaren Zelle ──────────────────────────────
        input.addEventListener('keydown', e => {
            if (e.key === 'Tab') {
                e.preventDefault();
                const allCells = [...document.querySelectorAll('[data-ie-field]')];
                const idx = allCells.indexOf(cell);
                const next = allCells[idx + (e.shiftKey ? -1 : 1)];
                if (next) {
                    input.blur(); // triggers save
                    setTimeout(() => activateCell(next), 50);
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                input.blur(); // triggers save
            } else if (e.key === 'Escape') {
                delete cell.dataset.ieEditing;
                cell.style.padding = '';
                cell.dataset.ieValue = originalValue;
                if (type === 'money') {
                    cell.textContent = formatMoney(originalValue);
                } else {
                    cell.textContent = originalText;
                }
            }
        });

        input.addEventListener('blur', save);
    }

    // ── Initialisierung ────────────────────────────────────────────────────────
    function init() {
        document.querySelectorAll('[data-ie-field]').forEach(cell => {
            cell.style.cursor = 'text';
            cell.title = 'Klicken zum Bearbeiten';

            // Für money-Felder: initial formatieren
            if (cell.dataset.ieType === 'money' && cell.dataset.ieValue) {
                cell.textContent = formatMoney(cell.dataset.ieValue);
            }

            cell.addEventListener('click', () => activateCell(cell));
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
