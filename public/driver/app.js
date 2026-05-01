/**
 * Driver PWA – Main Application
 * Extended: tour_start/end, depart, delay warning, Leergutausgleich, Kassenentnahme.
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
 *   Each record: { id (auto), client_event_id, event_type, tour_id,
 *                  tour_stop_id, order_item_id, payload, synced: 0|1, created_at }
 */

'use strict';

// ── Constants ─────────────────────────────────────────────────────────────────

const DB_NAME         = 'driver_pwa';
const DB_VERSION      = 3; // v3: added tour_id field to event_queue records
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
let tourData     = null;   // { tour, stops, cash_register, avg_durations, leergut_map, delay_threshold } from /bootstrap
let deviceId     = getOrCreateDeviceId();
let syncing      = false;
let selectedDate = new Date().toISOString().slice(0, 10); // YYYY-MM-DD

// ── Online/offline detection ───────────────────────────────────────────────────────

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
            const d      = e.target.result;
            const oldVer = e.oldVersion;

            // v1/v2: drop and recreate to ensure clean schema
            if (oldVer < 3 && d.objectStoreNames.contains(STORE_NAME)) {
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

function enqueueEvent(eventType, tourId, tourStopId, payload, orderItemId = null) {
    const record = {
        client_event_id: generateUuid(),
        event_type:      eventType,
        tour_id:         tourId      ?? null,
        tour_stop_id:    tourStopId  ?? null,
        order_item_id:   orderItemId ?? null,
        payload,
        synced:          0,
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
        const req   = idx.getAll(IDBKeyRange.only(0));
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
            tour_id:         e.tour_id      ?? null,
            tour_stop_id:    e.tour_stop_id ?? null,
            order_item_id:   e.order_item_id ?? null,
            payload:         e.payload       ?? {},
        }));

        const resp = await apiFetch('POST', '/sync', { device_id: deviceId, events });
        if (resp.status === 401) { handleAuthError(); return; }
        if (!resp.ok) throw new Error('Sync HTTP ' + resp.status);

        const result = await resp.json();
        await markEventsSynced(pending.map(e => e.id));

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
        errorEl.textContent   = status.lastError ? '⚠ ' + status.lastError : '';
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

// ── Connectivity indicator ─────────────────────────────────────────────────────────

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
    await enqueueEvent('note', tourData?.tour?.id ?? null, null, { text });
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

    // Tour-level header card
    const tourCardHtml = renderTourCard();

    container.innerHTML = tourCardHtml + tourData.stops.map(stop => renderStopCard(stop)).join('');

    // Tour card listeners
    document.getElementById('btn-tour-start')?.addEventListener('click', handleTourStart);
    document.getElementById('btn-tour-end')?.addEventListener('click', handleTourEnd);
    document.getElementById('btn-kasse')?.addEventListener('click', showCashModal);

    tourData.stops.forEach(stop => {
        const card = document.getElementById('stop-' + stop.id);
        if (!card) return;

        card.querySelector('.btn-arrived')?.addEventListener('click',  () => handleArrived(stop));
        card.querySelector('.btn-depart')?.addEventListener('click',   () => handleDepart(stop));
        card.querySelector('.btn-finished')?.addEventListener('click', () => handleFinished(stop));
        card.querySelector('.btn-leergut')?.addEventListener('click',  () => handleLeergutausgleich(stop));

        (stop.order?.items ?? []).forEach(item => {
            card.querySelector('.btn-deliver-' + item.id)
                ?.addEventListener('click', () => handleItemDelivered(stop, item));
            card.querySelector('.btn-nd-' + item.id)
                ?.addEventListener('click', e => showNotDeliveredPicker(stop, item, e.currentTarget));
        });

        card.querySelector('.btn-upload-' + stop.id)
            ?.addEventListener('click', () => handleUpload(stop));
    });
}

function renderTourCard() {
    const tour        = tourData.tour;
    const allFinished = (tourData.stops ?? []).every(s => s.status === 'finished' || s.status === 'skipped');
    const isStarted   = !!tour.started_at;
    const isEnded     = !!tour.ended_at;
    const reg         = tourData.cash_register;

    const startBtn = !isStarted
        ? `<button id="btn-tour-start" class="btn btn-primary" style="flex:1">🚚 Tour starten</button>`
        : `<span style="font-size:.8rem;color:#166534;background:#dcfce7;padding:4px 10px;border-radius:6px">
               ✓ Gestartet ${fmtTime(tour.started_at)}</span>`;

    const endBtn = isStarted && !isEnded && allFinished
        ? `<button id="btn-tour-end" class="btn btn-danger" style="flex:1">🏁 Tour beenden</button>`
        : (isEnded
            ? `<span style="font-size:.8rem;color:#991b1b;background:#fee2e2;padding:4px 10px;border-radius:6px">
                   ✓ Beendet ${fmtTime(tour.ended_at)}</span>`
            : '');

    const kasseBtn = reg
        ? `<button id="btn-kasse" class="btn btn-secondary" style="font-size:.8rem">
               💰 ${escHtml(reg.name)}</button>`
        : '';

    return `
        <div class="stop-card" style="background:#eef2ff;border:1px solid #c7d2fe">
            <h2 style="color:#3730a3">Tour ${escHtml(tour.tour_date)}</h2>
            <p style="font-size:.8rem;color:#4338ca;margin-bottom:10px">
                ${tourData.stops.length} Stopps · Status: ${escHtml(tour.status)}
            </p>
            <div class="stop-actions">
                ${startBtn}
                ${endBtn}
                ${kasseBtn}
            </div>
        </div>`;
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
    const statusLabel = { open: 'Offen', arrived: 'Angekommen', finished: 'Abgeschlossen', skipped: 'Übersprungen' };
    const arrivedDis  = stop.status !== 'open'    ? 'disabled' : '';
    const finishedDis = stop.status !== 'arrived' ? 'disabled' : '';
    const departDis   = (stop.status !== 'arrived' && stop.status !== 'finished') ? 'disabled' : '';

    // Delay warning
    const delayHtml = buildDelayWarning(stop);

    // Leergut button — only if stop is arrived and has leergut items
    const leergutItems = getLeergutItems(stop);
    const leergutBtn = (stop.status === 'arrived' && leergutItems.length > 0)
        ? `<button class="btn btn-secondary btn-leergut" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a">
               ♻ Leergutausgleich (${leergutItems.length} Artikel)
           </button>`
        : '';

    // Items list
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

    // Upload section
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

    const addressHtml = stop.delivery_address
        ? `<p class="stop-address">📍 ${escHtml(stop.delivery_address)}</p>`
        : '';
    const deliveryNoteHtml = stop.delivery_note
        ? `<p class="stop-delivery-note">ℹ ${escHtml(stop.delivery_note)}</p>`
        : '';

    const timeHtml = [
        stop.arrived_at  ? `<span class="stop-time">Angekommen ${fmtTime(stop.arrived_at)}</span>`   : '',
        stop.departed_at ? `<span class="stop-time">Abgefahren ${fmtTime(stop.departed_at)}</span>`  : '',
        stop.finished_at ? `<span class="stop-time">Fertig ${fmtTime(stop.finished_at)}</span>`      : '',
    ].filter(Boolean).join(' ');

    return `
        <div class="stop-card" id="stop-${stop.id}">
            <h2>Stopp ${stop.stop_index + 1} – ${escHtml(stop.customer_name ?? ('Auftrag #' + stop.order_id))}</h2>
            ${addressHtml}
            ${deliveryNoteHtml}
            ${delayHtml}
            <p class="stop-meta">
                <span class="stop-status ${stop.status}">${statusLabel[stop.status] ?? stop.status}</span>
                ${timeHtml}
            </p>
            ${itemsHtml ? `<ul class="items-list">${itemsHtml}</ul>` : ''}
            <div class="stop-actions">
                <button class="btn btn-primary btn-arrived"    ${arrivedDis}>Angekommen</button>
                ${leergutBtn}
                <button class="btn btn-secondary btn-depart"   ${departDis}>Abgefahren</button>
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

/** Build a delay warning banner if this customer is taking significantly longer than average. */
function buildDelayWarning(stop) {
    if (stop.status !== 'arrived' || !stop.arrived_at) return '';

    const avgSeconds    = tourData?.avg_durations?.[stop.order?.customer_id] ?? null;
    const threshold     = (tourData?.delay_threshold ?? 30) / 100;

    if (!avgSeconds || avgSeconds < 60) return ''; // skip if no meaningful avg

    const elapsedSeconds = (Date.now() - new Date(stop.arrived_at).getTime()) / 1000;
    const limitSeconds   = avgSeconds * (1 + threshold);

    if (elapsedSeconds > limitSeconds) {
        const overMin = Math.round((elapsedSeconds - avgSeconds) / 60);
        return `<div style="background:#fef3c7;border:1px solid #fde68a;border-radius:6px;
                             padding:6px 10px;font-size:.78rem;color:#92400e;margin-bottom:8px">
                    ⏱ ${overMin} Min. über Kundendurchschnitt
                </div>`;
    }
    return '';
}

/** Returns leergut items for a stop based on leergut_map and delivered items. */
function getLeergutItems(stop) {
    const leergutMap = tourData?.leergut_map ?? {};
    const items      = stop.order?.items ?? [];
    const result     = [];

    items.forEach(item => {
        if (!item.product_id) return;
        const leergut = leergutMap[item.product_id];
        if (!leergut) return;
        const f   = (stop.item_fulfillments ?? []).find(f => f.order_item_id === item.id);
        const qty = f?.delivered_qty ?? item.quantity;
        if (qty > 0) result.push({ ...leergut, qty, order_item_id: item.id });
    });

    return result;
}

// ── Action handlers ───────────────────────────────────────────────────────────

async function handleTourStart() {
    if (!tourData?.tour) return;
    if (!confirm('Tour jetzt starten?')) return;
    await enqueueEvent('tour_start', tourData.tour.id, null, {});
    tourData.tour.started_at = new Date().toISOString();
    tourData.tour.status = 'in_progress';
    renderStops();
    showStatus('Tour gestartet.', 'success');
    await updatePendingCount();
    if (navigator.onLine) flushQueue();
}

async function handleTourEnd() {
    if (!tourData?.tour) return;
    if (!confirm('Tour wirklich beenden?')) return;
    await enqueueEvent('tour_end', tourData.tour.id, null, {});
    tourData.tour.ended_at = new Date().toISOString();
    tourData.tour.status = 'done';
    renderStops();
    showStatus('Tour beendet.', 'success');
    await updatePendingCount();
    if (navigator.onLine) flushQueue();
}

async function handleArrived(stop) {
    await enqueueEvent('arrived', tourData?.tour?.id ?? null, stop.id, {});
    updateStopStatusLocally(stop, 'arrived', { arrived_at: new Date().toISOString() });
    renderStops();
    showStatus('Angekommen gespeichert.', 'success');
    await updatePendingCount();
    if (navigator.onLine) flushQueue();
}

async function handleDepart(stop) {
    await enqueueEvent('depart', tourData?.tour?.id ?? null, stop.id, {});
    updateStopStatusLocally(stop, stop.status, { departed_at: new Date().toISOString() });
    renderStops();
    showStatus('Abfahrt gespeichert.', 'success');
    await updatePendingCount();
    if (navigator.onLine) flushQueue();
}

async function handleFinished(stop) {
    if (!confirm('Stopp wirklich abschließen?')) return;
    await enqueueEvent('finished', tourData?.tour?.id ?? null, stop.id, {});
    updateStopStatusLocally(stop, 'finished');
    renderStops();
    showStatus('Abschluss gespeichert.', 'success');
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
    await enqueueEvent('item_delivered', tourData?.tour?.id ?? null, stop.id, { order_item_id: item.id, qty }, item.id);
    applyItemDeliveryLocally(stop, item, qty);
    renderStops();
    showStatus(`${qty}× "${item.product_name ?? item.product_id}" geliefert gespeichert.`, 'success');
    await updatePendingCount();
    if (navigator.onLine) flushQueue();
}

async function handleLeergutausgleich(stop) {
    const items = getLeergutItems(stop);
    if (items.length === 0) return;

    const listText = items.map(i => `${i.qty}× ${i.leergut_name}`).join('\n');
    if (!confirm(`Leergutausgleich buchen?\n\n${listText}\n\nDiese Artikel werden dem Kunden gutgeschrieben.`)) return;

    const payload = {
        order_id: stop.order_id,
        items:    items.map(i => ({
            wawi_artikel_nr:        i.leergut_art_nr ?? '',
            qty:                    i.qty,
            leergut_name:           i.leergut_name,
            unit_price_net_milli:   i.unit_price_net_milli,
            unit_price_gross_milli: i.unit_price_gross_milli,
            tax_rate_percent:       19,
        })),
    };

    await enqueueEvent('leergutausgleich', tourData?.tour?.id ?? null, stop.id, payload);
    showStatus('Leergutausgleich gespeichert.', 'success');
    await updatePendingCount();
    if (navigator.onLine) flushQueue();
}

// ── Kassenentnahme modal ──────────────────────────────────────────────────────

function showCashModal() {
    const reg = tourData?.cash_register;
    if (!reg) { showStatus('Keine Kasse zugewiesen.', 'error'); return; }

    // Remove existing modal
    document.getElementById('cash-modal')?.remove();

    const modal = document.createElement('div');
    modal.id = 'cash-modal';
    modal.innerHTML = `
        <div style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;
                    display:flex;align-items:flex-end;justify-content:center">
            <div style="background:#fff;border-radius:16px 16px 0 0;padding:24px 20px;
                        width:100%;max-width:480px;max-height:80dvh;overflow-y:auto">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:4px">💰 ${escHtml(reg.name)}</h3>
                <p style="font-size:.8rem;color:#6b7280;margin-bottom:16px">Buchung erfassen</p>

                <div style="display:flex;gap:8px;margin-bottom:12px">
                    <button id="cash-type-withdrawal" data-type="withdrawal"
                            style="flex:1;padding:10px;border-radius:8px;border:2px solid #ef4444;
                                   background:#fef2f2;color:#991b1b;font-weight:600;cursor:pointer">
                        Entnahme
                    </button>
                    <button id="cash-type-deposit" data-type="deposit"
                            style="flex:1;padding:10px;border-radius:8px;border:2px solid #d1d5db;
                                   background:#f9fafb;color:#374151;font-weight:600;cursor:pointer">
                        Einnahme
                    </button>
                </div>

                <label style="display:block;font-size:.8rem;font-weight:500;color:#374151;margin-bottom:4px">
                    Betrag (€)
                </label>
                <input id="cash-amount" type="number" min="0.01" step="0.01" placeholder="0,00"
                       style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;
                              font-size:1rem;margin-bottom:12px">

                <label style="display:block;font-size:.8rem;font-weight:500;color:#374151;margin-bottom:4px">
                    Notiz (optional)
                </label>
                <input id="cash-note" type="text" placeholder="z.B. Tankfüllung, Wechselgeld …"
                       style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;
                              font-size:.9rem;margin-bottom:16px">

                <div style="display:flex;gap:8px">
                    <button id="cash-cancel"
                            style="flex:1;padding:10px;border-radius:8px;border:1px solid #d1d5db;
                                   background:#fff;cursor:pointer">Abbrechen</button>
                    <button id="cash-save"
                            style="flex:1;padding:10px;border-radius:8px;border:none;
                                   background:#4f46e5;color:#fff;font-weight:600;cursor:pointer">Speichern</button>
                </div>
            </div>
        </div>`;

    document.body.appendChild(modal);

    let selectedType = 'withdrawal';

    const btnW = modal.querySelector('#cash-type-withdrawal');
    const btnD = modal.querySelector('#cash-type-deposit');

    function setType(type) {
        selectedType = type;
        btnW.style.borderColor = type === 'withdrawal' ? '#ef4444' : '#d1d5db';
        btnW.style.background  = type === 'withdrawal' ? '#fef2f2' : '#f9fafb';
        btnD.style.borderColor = type === 'deposit'    ? '#10b981' : '#d1d5db';
        btnD.style.background  = type === 'deposit'    ? '#ecfdf5' : '#f9fafb';
    }

    btnW.addEventListener('click', () => setType('withdrawal'));
    btnD.addEventListener('click', () => setType('deposit'));

    modal.querySelector('#cash-cancel').addEventListener('click', () => modal.remove());
    modal.querySelector('#cash-save').addEventListener('click', async () => {
        const amountEur  = parseFloat(modal.querySelector('#cash-amount').value);
        const note       = modal.querySelector('#cash-note').value.trim();
        if (!amountEur || amountEur <= 0) { alert('Bitte gültigen Betrag eingeben.'); return; }

        const amountCents = Math.round(amountEur * 100);
        await enqueueEvent('cash_transaction', tourData?.tour?.id ?? null, null, {
            cash_register_id: reg.id,
            type:             selectedType,
            amount_cents:     amountCents,
            note:             note || null,
        });

        modal.remove();
        showStatus('Kassenbuchung gespeichert.', 'success');
        await updatePendingCount();
        if (navigator.onLine) flushQueue();
    });
}

// ── Not-delivered reason picker ───────────────────────────────────────────────

function showNotDeliveredPicker(stop, item, btn) {
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

    await enqueueEvent('item_not_delivered', tourData?.tour?.id ?? null, stop.id, {
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

        await enqueueEvent('upload', tourData?.tour?.id ?? null, stop.id, {
            upload_id: result.upload_id,
            kind:      'delivery_note',
        });

        const s = tourData?.stops.find(s => s.id === stop.id);
        if (s) s.uploads_count = (s.uploads_count ?? 0) + 1;

        if (fileInput) fileInput.value = '';

        renderStops();
        showStatus('Datei erfolgreich hochgeladen.', 'success');
        await updatePendingCount();
        if (navigator.onLine) flushQueue();
    } catch (err) {
        showStatus('Upload fehlgeschlagen: ' + err.message, 'error');
    }
}

// ── Local state helpers (optimistic updates) ──────────────────────────────────

function updateStopStatusLocally(stop, newStatus, extraFields = {}) {
    if (!tourData) return;
    const s = tourData.stops.find(s => s.id === stop.id);
    if (s) {
        s.status = newStatus;
        Object.assign(s, extraFields);
    }
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

function fmtTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
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
