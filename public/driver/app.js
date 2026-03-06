/**
 * Driver PWA – Main Application
 * WP-9: Delivery Fulfillment – item not-delivered, upload, status updates.
 *
 * Architecture:
 *   - All mutations are queued as "driver events" in IndexedDB first.
 *   - A sync attempt is made immediately when online; queued items are
 *     flushed on next manual or automatic sync when offline.
 *   - Bearer token is stored under "driver_api_token_plain" (auth overlay);
 *     "driver_token" is accepted as a legacy fallback.
 *   - File uploads go directly to /api/driver/upload (online-only).
 *     After success an 'upload' event is queued in the offline queue.
 *
 * IndexedDB schema:
 *   DB: driver_pwa  /  Store: event_queue
 *   Each record: { id (auto), client_event_id, event_type, tour_stop_id,
 *                  order_item_id, payload, synced: 0|1, created_at }
 */

'use strict';

// ── Constants ─────────────────────────────────────────────────────────────────

const DB_NAME         = 'driver_pwa';
const DB_VERSION      = 2; // v2: synced stored as 0/1 integer (booleans are not valid IDB keys)
const STORE_NAME      = 'event_queue';
const TOKEN_KEY       = 'driver_api_token_plain'; // written by auth overlay
const TOKEN_KEY_LGY   = 'driver_token';           // legacy fallback
const SYNC_STATUS_KEY = 'driver_sync_status';     // JSON blob {lastAt, lastError}
const REJECTED_LS_KEY = 'driver_sync_rejected';   // JSON array of rejected event summaries

const NOT_DELIVERED_REASONS = [
    { code: 'customer_absent', label: 'Kunde nicht anwesend' },
    { code: 'damaged',         label: 'Beschädigt / defekt' },
    { code: 'refused',         label: 'Annahme verweigert' },
    { code: 'out_of_stock',    label: 'Nicht auf Fahrzeug' },
];

// ── State ─────────────────────────────────────────────────────────────────────

let db           = null;   // IDBDatabase
let tourData     = null;   // { tour, stops } from /bootstrap
let deviceId     = getOrCreateDeviceId();
let syncing      = false;
let selectedDate = new Date().toISOString().slice(0, 10); // YYYY-MM-DD

// ── Online/offline detection ───────────────────────────────────────────────────

window.addEventListener('online', () => {
    document.body.classList.remove('offline');
    updateConnectivityIndicator();
    flushQueue();
});
window.addEventListener('offline', () => {
    document.body.classList.add('offline');
    updateConnectivityIndicator();
});
if (!navigator.onLine) document.body.classList.add('offline');

// ── Entry point ───────────────────────────────────────────────────────────────

async function main() {
    db = await openDatabase();

    updateConnectivityIndicator();
    await updatePendingCount();
    loadAndRenderRejected();
    restoreSyncStatus();
    initNoteSection();
    initRejectedPanel();

    const token = getToken();
    if (!token) {
        renderLoginPrompt();
        return;
    }

    window.__DRIVER_CONFIG__.token = token;
    await bootstrap();
    renderStops();
    document.getElementById('sync-btn').addEventListener('click', flushQueue);
}

main().catch(err => {
    console.error('[App] Fatal error:', err);
    showStatus('Schwerwiegender Fehler: ' + err.message, 'error');
});

// ── Token helper ──────────────────────────────────────────────────────────────

/** Read token from localStorage; prefers the auth-overlay key, falls back to legacy. */
function getToken() {
    return localStorage.getItem(TOKEN_KEY) || localStorage.getItem(TOKEN_KEY_LGY) || '';
}

// ── IndexedDB ─────────────────────────────────────────────────────────────────

function openDatabase() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = e => {
            const d       = e.target.result;
            const oldVer  = e.oldVersion;

            // v1 stored synced as boolean (false/true) which is not a valid IDB key.
            // Drop and recreate the store so the index works with integer 0/1.
            if (oldVer < 2 && d.objectStoreNames.contains(STORE_NAME)) {
                d.deleteObjectStore(STORE_NAME);
            }

            if (!d.objectStoreNames.contains(STORE_NAME)) {
                const store = d.createObjectStore(STORE_NAME, {
                    keyPath: 'id', autoIncrement: true,
                });
                store.createIndex('synced', 'synced', { unique: false });
            }
        };
        req.onsuccess = e => resolve(e.target.result);
        req.onerror   = e => reject(e.target.error);
    });
}

function enqueueEvent(eventType, tourStopId, payload, orderItemId = null) {
    const record = {
        client_event_id: generateUuid(),
        event_type:      eventType,
        tour_stop_id:    tourStopId,
        order_item_id:   orderItemId,
        payload,
        synced:          0, // 0 = pending, 1 = synced (booleans are not valid IDB keys)
        created_at:      new Date().toISOString(),
    };

    return new Promise((resolve, reject) => {
        const tx    = db.transaction(STORE_NAME, 'readwrite');
        const store = tx.objectStore(STORE_NAME);
        const req   = store.add(record);
        req.onsuccess = () => resolve({ ...record, id: req.result });
        req.onerror   = e => reject(e.target.error);
    });
}

function getPendingEvents() {
    return new Promise((resolve, reject) => {
        const tx    = db.transaction(STORE_NAME, 'readonly');
        const store = tx.objectStore(STORE_NAME);
        const idx   = store.index('synced');
        const req   = idx.getAll(IDBKeyRange.only(0)); // 0 = pending (not synced yet)
        req.onsuccess = e => resolve(e.target.result);
        req.onerror   = e => reject(e.target.error);
    });
}

function markEventsSynced(ids) {
    return new Promise((resolve, reject) => {
        const tx    = db.transaction(STORE_NAME, 'readwrite');
        const store = tx.objectStore(STORE_NAME);
        let pending = ids.length;
        if (pending === 0) { resolve(); return; }

        ids.forEach(id => {
            const req = store.get(id);
            req.onsuccess = e => {
                const record = e.target.result;
                if (record) {
                    record.synced = 1;
                    store.put(record);
                }
                if (--pending === 0) resolve();
            };
            req.onerror = e => reject(e.target.error);
        });
    });
}

// ── API helpers ───────────────────────────────────────────────────────────────

function apiHeaders() {
    return {
        'Authorization': 'Bearer ' + getToken(),
        'Content-Type':  'application/json',
        'Accept':        'application/json',
    };
}

async function apiFetch(method, path, body = null) {
    const opts = { method, headers: apiHeaders() };
    if (body !== null) opts.body = JSON.stringify(body);
    return fetch(window.__DRIVER_CONFIG__.apiBase + path, opts);
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────

async function bootstrap() {
    try {
        const resp = await apiFetch('GET', '/bootstrap?date=' + encodeURIComponent(selectedDate));
        if (resp.status === 401) { handleAuthError(); return; }
        if (!resp.ok) throw new Error('Bootstrap HTTP ' + resp.status);
        tourData = await resp.json();
        document.getElementById('tour-title').textContent =
            tourData.tour
                ? 'Tour ' + tourData.tour.tour_date
                : 'Keine Tour – ' + selectedDate;
    } catch (err) {
        tourData = null;
        showStatus('Bootstrap fehlgeschlagen: ' + err.message, 'error');
    }
}

// ── Sync / flush queue ────────────────────────────────────────────────────────

async function flushQueue() {
    if (syncing || !navigator.onLine) return;
    syncing = true;
    setSyncButtonState(true);
    showStatus('Synchronisiere…', 'info');

    try {
        const pending = await getPendingEvents();
        if (pending.length === 0) {
            showStatus('Nichts zu synchronisieren.', 'success');
            saveSyncStatus({ lastAt: new Date().toISOString(), lastError: null });
            await updatePendingCount();
            return;
        }

        const events = pending.map(e => ({
            client_event_id: e.client_event_id,
            event_type:      e.event_type,
            tour_stop_id:    e.tour_stop_id  ?? null,
            order_item_id:   e.order_item_id ?? null,
            payload:         e.payload       ?? {},
        }));

        const resp = await apiFetch('POST', '/sync', { device_id: deviceId, events });
        if (resp.status === 401) { handleAuthError(); return; }
        if (!resp.ok) throw new Error('Sync HTTP ' + resp.status);

        const result = await resp.json();
        await markEventsSynced(pending.map(e => e.id));

        // Surface rejected events — do NOT silently drop them
        const rejectedResults = (result.results ?? []).filter(r => r.status === 'rejected');
        handleRejectedEvents(rejectedResults, pending);

        const lastError = rejectedResults.length > 0
            ? rejectedResults.length + ' Ereignis(se) abgelehnt'
            : null;
        saveSyncStatus({ lastAt: new Date().toISOString(), lastError });

        showStatus(
            `Sync: ${result.applied} übertragen, `
            + `${result.rejected} abgelehnt, `
            + `${result.duplicates} Duplikate.`,
            result.rejected > 0 ? 'error' : 'success',
        );

        await bootstrap();
        renderStops();
    } catch (err) {
        saveSyncStatus({ lastAt: new Date().toISOString(), lastError: err.message });
        showStatus('Sync fehlgeschlagen: ' + err.message, 'error');
    } finally {
        syncing = false;
        setSyncButtonState(false);
        restoreSyncStatus();
        await updatePendingCount();
    }
}

// ── Sync status panel ─────────────────────────────────────────────────────────

function saveSyncStatus(patch) {
    const current = JSON.parse(localStorage.getItem(SYNC_STATUS_KEY) || '{}');
    localStorage.setItem(SYNC_STATUS_KEY, JSON.stringify({ ...current, ...patch }));
}

function restoreSyncStatus() {
    const status  = JSON.parse(localStorage.getItem(SYNC_STATUS_KEY) || '{}');
    const timeEl  = document.getElementById('sync-last-time');
    const errorEl = document.getElementById('sync-last-error');

    if (timeEl) {
        timeEl.textContent = status.lastAt
            ? 'Letzter Sync: ' + new Date(status.lastAt).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
            : '';
    }
    if (errorEl) {
        errorEl.textContent  = status.lastError ? '⚠ ' + status.lastError : '';
        errorEl.style.display = status.lastError ? '' : 'none';
    }
}

async function updatePendingCount() {
    if (!db) return;
    const pending = await getPendingEvents();
    const el = document.getElementById('sync-pending');
    if (el) el.textContent = pending.length + ' ausstehend';
}

// ── Rejected events ───────────────────────────────────────────────────────────

/**
 * Merge newly rejected results with any previously stored ones, persist, and render.
 * @param {Array} rejectedResults  - items from sync result.results with status === 'rejected'
 * @param {Array} sentEvents       - the original pending IDB records (for cross-referencing type)
 */
function handleRejectedEvents(rejectedResults, sentEvents) {
    if (rejectedResults.length === 0) return;

    const byClientId = Object.fromEntries(sentEvents.map(e => [e.client_event_id, e]));
    const stored     = loadStoredRejected();

    const newEntries = rejectedResults.map(r => ({
        client_event_id: r.client_event_id,
        event_type:      byClientId[r.client_event_id]?.event_type ?? '?',
        apply_error:     r.error ?? 'Unbekannter Fehler',
        rejected_at:     new Date().toISOString(),
    }));

    // Prepend newest, cap at 50 entries
    const merged = [...newEntries, ...stored].slice(0, 50);
    localStorage.setItem(REJECTED_LS_KEY, JSON.stringify(merged));
    renderRejectedEvents(merged);
}

function loadStoredRejected() {
    try { return JSON.parse(localStorage.getItem(REJECTED_LS_KEY) || '[]'); }
    catch { return []; }
}

function loadAndRenderRejected() {
    renderRejectedEvents(loadStoredRejected());
}

function renderRejectedEvents(entries) {
    const panel = document.getElementById('rejected-panel');
    const badge = document.getElementById('rejected-badge');
    const list  = document.getElementById('rejected-list');
    if (!panel || !badge || !list) return;

    if (!entries || entries.length === 0) {
        panel.classList.add('hidden');
        return;
    }

    badge.textContent = `⚠ ${entries.length} Ereignis(se) abgelehnt`;
    list.innerHTML = entries.map(e => `
        <li>
            <div class="rej-type">${escHtml(e.event_type)}</div>
            <div class="rej-id">${escHtml(e.client_event_id)}</div>
            <div class="rej-reason">${escHtml(e.apply_error)}</div>
        </li>
    `).join('');
    panel.classList.remove('hidden');
}

function initRejectedPanel() {
    const toggle = document.getElementById('rejected-toggle');
    const list   = document.getElementById('rejected-list');
    const arrow  = document.getElementById('rejected-arrow');
    if (!toggle) return;

    toggle.addEventListener('click', () => {
        const isOpen = !list.classList.contains('hidden');
        list.classList.toggle('hidden', isOpen);
        arrow.classList.toggle('open', !isOpen);
    });
}

// ── Connectivity indicator ─────────────────────────────────────────────────────

function updateConnectivityIndicator() {
    const el    = document.getElementById('connectivity');
    const label = document.getElementById('conn-label');
    if (!el) return;
    const online = navigator.onLine;
    el.className = online ? 'online' : 'offline';
    if (label) label.textContent = online ? 'Online' : 'Offline';
}

// ── General note section ──────────────────────────────────────────────────────

function initNoteSection() {
    document.getElementById('note-save-btn')?.addEventListener('click', saveNote);
    document.getElementById('note-sync-btn')?.addEventListener('click', flushQueue);
}

async function saveNote() {
    const textarea = document.getElementById('note-text');
    const text     = (textarea?.value ?? '').trim();
    if (!text) {
        showStatus('Bitte Notiztext eingeben.', 'error');
        return;
    }
    // tour_stop_id = null → general driver note (WP-7B backend supports this)
    await enqueueEvent('note', null, { text });
    if (textarea) textarea.value = '';

    const hint = document.getElementById('note-hint');
    if (hint) {
        hint.classList.remove('hidden');
        setTimeout(() => hint.classList.add('hidden'), 3000);
    }
    await updatePendingCount();
}

// ── Render ────────────────────────────────────────────────────────────────────

function renderStops() {
    const container = document.getElementById('stops-container');

    if (!tourData || !tourData.tour) {
        container.innerHTML = `
            <div class="empty-state-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                     style="width:56px;height:56px;color:#9ca3af;margin-bottom:14px">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1
                          2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25
                          0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18
                          0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21
                          11.25v7.5"/>
                </svg>
                <h2>Keine Tour für dieses Datum</h2>
                <p class="empty-date">${escHtml(selectedDate)}</p>
                <div class="date-picker-row">
                    <input type="date" id="date-picker" value="${escHtml(selectedDate)}">
                    <button id="date-reload-btn" class="btn btn-primary">Neu laden</button>
                </div>
            </div>`;
        attachDatePickerListeners();
        return;
    }

    if (!tourData.stops || tourData.stops.length === 0) {
        container.innerHTML = `
            <div class="empty-state-card">
                <p style="color:#9ca3af;margin-bottom:16px">
                    Tour ${escHtml(tourData.tour.tour_date)} hat keine Stopps.
                </p>
                <div class="date-picker-row">
                    <input type="date" id="date-picker" value="${escHtml(selectedDate)}">
                    <button id="date-reload-btn" class="btn btn-secondary">Anderen Tag laden</button>
                </div>
            </div>`;
        attachDatePickerListeners();
        return;
    }

    container.innerHTML = tourData.stops.map(stop => renderStopCard(stop)).join('');

    tourData.stops.forEach(stop => {
        const card = document.getElementById('stop-' + stop.id);
        if (!card) return;

        // Stop-level actions
        card.querySelector('.btn-arrived')?.addEventListener('click',  () => handleArrived(stop));
        card.querySelector('.btn-finished')?.addEventListener('click', () => handleFinished(stop));

        // Item-level actions
        (stop.order?.items ?? []).forEach(item => {
            card.querySelector('.btn-deliver-' + item.id)
                ?.addEventListener('click', () => handleItemDelivered(stop, item));
            card.querySelector('.btn-nd-' + item.id)
                ?.addEventListener('click', e => showNotDeliveredPicker(stop, item, e.currentTarget));
        });

        // Upload action
        card.querySelector('.btn-upload-' + stop.id)
            ?.addEventListener('click', () => handleUpload(stop));
    });
}

/** Attach date-picker and reload-button listeners after rendering the empty state. */
function attachDatePickerListeners() {
    document.getElementById('date-picker')?.addEventListener('change', async e => {
        selectedDate = e.target.value;
        document.getElementById('tour-title').textContent = 'Lade Tour…';
        await bootstrap();
        renderStops();
    });
    document.getElementById('date-reload-btn')?.addEventListener('click', async () => {
        await bootstrap();
        renderStops();
    });
}

function renderStopCard(stop) {
    const statusLabel = { open: 'Offen', arrived: 'Angekommen', finished: 'Abgeschlossen' };
    const arrivedDis  = stop.status !== 'open'    ? 'disabled' : '';
    const finishedDis = stop.status !== 'arrived' ? 'disabled' : '';

    // ── Items list ──────────────────────────────────────────────────────────
    const itemsHtml = (stop.order?.items ?? []).map(item => {
        const f            = (stop.item_fulfillments ?? []).find(f => f.order_item_id === item.id);
        const delivered    = f?.delivered_qty    ?? 0;
        const notDelivered = f?.not_delivered_qty ?? 0;
        const remaining    = item.quantity - delivered;
        const canDeliver   = stop.status === 'arrived' && remaining > 0;
        const canNotDel    = stop.status === 'arrived' && remaining > 0;
        const name         = item.product_name ?? ('Produkt #' + item.product_id);
        const artNr        = item.artikelnummer ? ' · ' + item.artikelnummer : '';

        return `
            <li>
                <span class="item-info">
                    <span class="item-name">${escHtml(name)}</span>
                    <span class="item-numbers">${escHtml(artNr.replace(' · ', ''))}${artNr ? ' · ' : ''}${item.quantity}× geordert
                        ${delivered    > 0 ? `<span class="qty-ok"> · ${delivered} gel.</span>` : ''}
                        ${notDelivered > 0 ? `<span class="qty-nd"> · ${notDelivered} n.gel. (${escHtml(f?.not_delivered_reason ?? '')})</span>` : ''}
                    </span>
                </span>
                <span class="item-btns">
                    <button class="item-del-btn btn-deliver-${item.id}" ${canDeliver ? '' : 'disabled'} title="Geliefert">
                        +${remaining}
                    </button>
                    <button class="item-nd-btn btn-nd-${item.id}" ${canNotDel ? '' : 'disabled'} title="Nicht geliefert">✗</button>
                </span>
            </li>`;
    }).join('');

    // ── Upload section ──────────────────────────────────────────────────────
    const uploadCount = stop.uploads_count ?? 0;
    const canUpload   = stop.status !== 'open';
    const uploadBadge = uploadCount > 0
        ? `<span class="upload-badge">${uploadCount} hochgeladen</span>`
        : '';
    const uploadControls = canUpload
        ? `<div class="upload-controls">
               <input type="file" id="upload-file-${stop.id}" accept="image/*,application/pdf" class="upload-input">
               <button class="btn btn-secondary btn-upload btn-upload-${stop.id}">📤 Hochladen</button>
           </div>`
        : `<p class="upload-hint">Verfügbar nach Ankunft</p>`;

    // ── Address / delivery note ─────────────────────────────────────────────
    const addressHtml = stop.delivery_address
        ? `<p class="stop-address">📍 ${escHtml(stop.delivery_address)}</p>`
        : '';
    const deliveryNoteHtml = stop.delivery_note
        ? `<p class="stop-delivery-note">ℹ ${escHtml(stop.delivery_note)}</p>`
        : '';

    // ── Timestamps ─────────────────────────────────────────────────────────
    const timeFmt = iso => iso ? new Date(iso).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }) : '';
    const timeHtml = [
        stop.arrived_at  ? `<span class="stop-time">Angekommen ${timeFmt(stop.arrived_at)}</span>`  : '',
        stop.finished_at ? `<span class="stop-time">Fertig ${timeFmt(stop.finished_at)}</span>`     : '',
    ].filter(Boolean).join(' ');

    return `
        <div class="stop-card" id="stop-${stop.id}">
            <h2>Stopp ${stop.stop_index + 1} – ${escHtml(stop.customer_name ?? ('Auftrag #' + stop.order_id))}</h2>
            ${addressHtml}
            ${deliveryNoteHtml}
            <p class="stop-meta">
                <span class="stop-status ${stop.status}">${statusLabel[stop.status] ?? stop.status}</span>
                ${timeHtml}
            </p>
            ${itemsHtml ? `<ul class="items-list">${itemsHtml}</ul>` : ''}
            <div class="stop-actions">
                <button class="btn btn-primary btn-arrived"    ${arrivedDis}>Angekommen</button>
                <button class="btn btn-secondary btn-finished" ${finishedDis}>Abschließen</button>
            </div>
            <div class="upload-section">
                <div class="upload-header">
                    <span class="upload-label">📷 Lieferdokument</span>
                    ${uploadBadge}
                </div>
                ${uploadControls}
            </div>
        </div>`;
}

// ── Action handlers ───────────────────────────────────────────────────────────

async function handleArrived(stop) {
    await enqueueEvent('arrived', stop.id, {});
    updateStopStatusLocally(stop, 'arrived');
    renderStops();
    showStatus('Angekommen gespeichert. Wird beim Sync übertragen.', 'success');
    await updatePendingCount();
    if (navigator.onLine) flushQueue();
}

async function handleFinished(stop) {
    if (!confirm('Stopp wirklich abschließen?')) return;
    await enqueueEvent('finished', stop.id, {});
    updateStopStatusLocally(stop, 'finished');
    renderStops();
    showStatus('Abschluss gespeichert. Wird beim Sync übertragen.', 'success');
    await updatePendingCount();
    if (navigator.onLine) flushQueue();
}

async function handleItemDelivered(stop, item) {
    const remaining = getRemainingQty(stop, item);
    if (remaining <= 0) return;
    const qty = parseInt(
        prompt(`Gelieferte Menge für "${item.product_name ?? item.product_id}"? (max ${remaining})`) ?? '0',
        10,
    );
    if (!qty || qty <= 0 || qty > remaining) { alert('Ungültige Menge.'); return; }
    await enqueueEvent('item_delivered', stop.id, { order_item_id: item.id, qty }, item.id);
    applyItemDeliveryLocally(stop, item, qty);
    renderStops();
    showStatus(`${qty}× "${item.product_name ?? item.product_id}" geliefert gespeichert.`, 'success');
    await updatePendingCount();
    if (navigator.onLine) flushQueue();
}

// ── Not-delivered reason picker ───────────────────────────────────────────────

function showNotDeliveredPicker(stop, item, btn) {
    // Remove any previously open picker
    document.querySelectorAll('.nd-picker').forEach(el => el.remove());

    const picker = document.createElement('div');
    picker.className = 'nd-picker';

    const reasonBtns = NOT_DELIVERED_REASONS.map(r =>
        `<button class="nd-reason-btn" data-code="${escHtml(r.code)}">${escHtml(r.label)}</button>`
    ).join('');

    picker.innerHTML = `
        <div class="nd-picker-inner">
            <strong class="nd-picker-title">Grund für Nicht-Lieferung:</strong>
            <div class="nd-reason-list">${reasonBtns}</div>
            <button class="nd-cancel-btn">Abbrechen</button>
        </div>`;

    // Insert directly after the item <li>
    btn.closest('li').insertAdjacentElement('afterend', picker);

    picker.querySelectorAll('.nd-reason-btn').forEach(rb => {
        rb.addEventListener('click', async () => {
            const reason = rb.dataset.code;
            picker.remove();
            await handleItemNotDelivered(stop, item, reason);
        });
    });

    picker.querySelector('.nd-cancel-btn').addEventListener('click', () => picker.remove());
}

async function handleItemNotDelivered(stop, item, reason) {
    const remaining = getRemainingQty(stop, item);
    if (remaining <= 0) {
        showStatus('Alle Einheiten wurden bereits geliefert.', 'error');
        return;
    }

    await enqueueEvent('item_not_delivered', stop.id, {
        order_item_id: item.id,
        qty:           remaining,
        reason,
    }, item.id);

    applyItemNotDeliveredLocally(stop, item, remaining, reason);
    renderStops();
    showStatus(`Nicht geliefert (${reason}) gespeichert.`, 'success');
    await updatePendingCount();
    if (navigator.onLine) flushQueue();
}

// ── Upload ────────────────────────────────────────────────────────────────────

async function handleUpload(stop) {
    if (!navigator.onLine) {
        showStatus('Upload nur verfügbar wenn online.', 'error');
        return;
    }

    const fileInput = document.getElementById('upload-file-' + stop.id);
    const file = fileInput?.files[0];

    if (!file) {
        showStatus('Bitte zuerst eine Datei auswählen.', 'error');
        return;
    }

    showStatus('Lade hoch…', 'info');

    const formData = new FormData();
    formData.append('device_id',    deviceId);
    formData.append('tour_stop_id', String(stop.id));
    formData.append('upload_type',  'delivery_note');
    formData.append('file',         file);

    try {
        // NOTE: do NOT set Content-Type — the browser sets it with the multipart boundary
        const resp = await fetch(window.__DRIVER_CONFIG__.apiBase + '/upload', {
            method:  'POST',
            headers: {
                'Authorization': 'Bearer ' + getToken(),
                'Accept':        'application/json',
            },
            body: formData,
        });

        if (resp.status === 401) { handleAuthError(); return; }
        if (!resp.ok) {
            const err = await resp.json().catch(() => ({}));
            throw new Error(err.error ?? 'Upload HTTP ' + resp.status);
        }

        const result = await resp.json();

        // Queue an 'upload' event so the upload appears in the driver event log
        await enqueueEvent('upload', stop.id, {
            upload_id: result.upload_id,
            kind:      'delivery_note',
        });

        // Optimistic local update
        const s = tourData?.stops.find(s => s.id === stop.id);
        if (s) s.uploads_count = (s.uploads_count ?? 0) + 1;

        if (fileInput) fileInput.value = '';

        renderStops();
        showStatus('Datei erfolgreich hochgeladen.', 'success');
        await updatePendingCount();
        if (navigator.onLine) flushQueue(); // flush the 'upload' event
    } catch (err) {
        showStatus('Upload fehlgeschlagen: ' + err.message, 'error');
    }
}

// ── Local state helpers (optimistic updates) ──────────────────────────────────

function updateStopStatusLocally(stop, newStatus) {
    if (!tourData) return;
    const s = tourData.stops.find(s => s.id === stop.id);
    if (s) s.status = newStatus;
}

function applyItemDeliveryLocally(stop, item, qty) {
    if (!tourData) return;
    const s = tourData.stops.find(s => s.id === stop.id);
    if (!s) return;
    s.item_fulfillments = s.item_fulfillments ?? [];
    let f = s.item_fulfillments.find(f => f.order_item_id === item.id);
    if (!f) {
        f = { order_item_id: item.id, delivered_qty: 0, not_delivered_qty: 0 };
        s.item_fulfillments.push(f);
    }
    f.delivered_qty += qty;
}

function applyItemNotDeliveredLocally(stop, item, qty, reason) {
    if (!tourData) return;
    const s = tourData.stops.find(s => s.id === stop.id);
    if (!s) return;
    s.item_fulfillments = s.item_fulfillments ?? [];
    let f = s.item_fulfillments.find(f => f.order_item_id === item.id);
    if (!f) {
        f = { order_item_id: item.id, delivered_qty: 0, not_delivered_qty: 0, not_delivered_reason: null };
        s.item_fulfillments.push(f);
    }
    f.not_delivered_qty    += qty;
    f.not_delivered_reason  = reason;
}

function getRemainingQty(stop, item) {
    const f = (stop.item_fulfillments ?? []).find(f => f.order_item_id === item.id);
    return item.quantity - (f?.delivered_qty ?? 0);
}

// ── Login prompt ──────────────────────────────────────────────────────────────

function renderLoginPrompt() {
    // The auth overlay in the Blade template is the primary login flow.
    // Surface it if present; fall back to inline form otherwise.
    const overlay = document.getElementById('driver-auth-overlay');
    if (overlay) {
        overlay.style.display = 'flex';
        return;
    }

    const container = document.getElementById('stops-container');
    container.innerHTML = `
        <div style="padding:24px;">
            <h2 style="margin-bottom:16px">Anmelden</h2>
            <label style="display:block;margin-bottom:8px;font-size:.875rem">API-Token</label>
            <input id="token-input" type="password"
                   style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;margin-bottom:12px">
            <button id="token-save-btn" class="btn btn-primary" style="width:100%">Anmelden</button>
        </div>`;

    document.getElementById('token-save-btn').addEventListener('click', () => {
        const token = document.getElementById('token-input').value.trim();
        if (!token) return;
        localStorage.setItem(TOKEN_KEY, token);
        window.__DRIVER_CONFIG__.token = token;
        location.reload();
    });
}

// ── UI helpers ────────────────────────────────────────────────────────────────

function showStatus(msg, type = 'info') {
    const bar = document.getElementById('status-bar');
    bar.textContent = msg;
    bar.className   = 'visible ' + (type === 'error' ? 'error' : type === 'success' ? 'success' : '');
    if (type !== 'error') {
        setTimeout(() => { bar.className = ''; }, 4000);
    }
}

function setSyncButtonState(isSyncing) {
    const btn = document.getElementById('sync-btn');
    btn.disabled    = isSyncing;
    btn.textContent = isSyncing ? 'Sync läuft…' : 'Sync';
}

function handleAuthError() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(TOKEN_KEY_LGY);
    showStatus('Sitzung abgelaufen. Bitte neu anmelden.', 'error');
    setTimeout(() => renderLoginPrompt(), 1500);
}

// ── Utility ───────────────────────────────────────────────────────────────────

function generateUuid() {
    if (crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
}

function getOrCreateDeviceId() {
    let id = localStorage.getItem('driver_device_id');
    if (!id) {
        id = generateUuid();
        localStorage.setItem('driver_device_id', id);
    }
    return id;
}

/** Minimal HTML-escape to prevent XSS when injecting user/server text into innerHTML. */
function escHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}
