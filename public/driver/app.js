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

let db                 = null;   // IDBDatabase
let tourData           = null;   // { tour, stops, cash_register, avg_durations, leergut_map, delay_threshold } from /bootstrap
let deviceId           = getOrCreateDeviceId();
let syncing            = false;
let selectedDate       = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
let showFinishedStops  = false;  // toggle: show/hide finished stops

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

    const isSessionAuth = window.__DRIVER_CONFIG__?.isSessionAuth;
    const token = getToken();
    if (!token && !isSessionAuth) {
        renderLoginPrompt();
        return;
    }

    if (token) window.__DRIVER_CONFIG__.token = token;
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
    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    const token = getToken();
    if (token) headers['Authorization'] = 'Bearer ' + token;
    return headers;
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
        updateHeaderTitle();
    } catch (err) {
        tourData = null;
        showStatus('Bootstrap fehlgeschlagen: ' + err.message, 'error');
    }
}

function tourLabel(tour) {
    return tour?.name || ('Tour ' + (tour?.tour_date ?? ''));
}

function updateHeaderTitle() {
    const el = document.getElementById('tour-title');
    if (!el) return;
    if (tourData?.tour) {
        el.textContent = tourLabel(tourData.tour);
    } else {
        el.textContent = 'Keine Tour – ' + selectedDate;
    }
}

function switchTour(tourId) {
    const entry = (tourData?.all_tours ?? []).find(t => t.tour.id === tourId);
    if (!entry) return;
    tourData.tour            = entry.tour;
    tourData.stops           = entry.stops;
    tourData.cash_register   = entry.cash_register;
    tourData.avg_durations   = entry.avg_durations;
    tourData.leergut_map     = entry.leergut_map;
    tourData.open_balances   = entry.open_balances;
    tourData.zielkassen      = entry.zielkassen;
    updateHeaderTitle();
    renderStops();
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
        const tourCardHtml = renderTourCard();
        container.innerHTML = renderTourSelector() + tourCardHtml + `
            <div class="empty-state-card">
                <p style="color:#9ca3af;margin-bottom:16px">
                    ${escHtml(tourLabel(tourData.tour))} hat keine Stopps.
                </p>
                <div class="date-picker-row">
                    <input type="date" id="date-picker" value="${escHtml(selectedDate)}">
                    <button id="date-reload-btn" class="btn btn-secondary">Anderen Tag laden</button>
                </div>
            </div>`;
        document.getElementById('btn-tour-start')?.addEventListener('click', handleTourStart);
        document.getElementById('btn-tour-end')?.addEventListener('click', showTourAbschlussModal);
        document.getElementById('btn-kasse')?.addEventListener('click', showCashModal);
        document.getElementById('btn-add-stop')?.addEventListener('click', showAddStopModal);
        attachDatePickerListeners();
        return;
    }

    // Tour-level header card
    const tourCardHtml = renderTourCard();

    const allStops      = tourData.stops ?? [];
    const finishedCount = allStops.filter(s => s.status === 'finished').length;
    const visibleStops  = showFinishedStops ? allStops : allStops.filter(s => s.status !== 'finished');
    const toggleHtml    = finishedCount > 0
        ? `<div style="text-align:center;padding:6px 0 2px">
               <button id="toggle-finished-stops" class="btn btn-outline"
                   style="font-size:.8rem;padding:4px 16px">
                   ${showFinishedStops
                       ? '▲ Abgeschlossene ausblenden'
                       : `▼ ${finishedCount} abgeschlossene${finishedCount === 1 ? 'n' : ''} Stopp${finishedCount === 1 ? '' : 's'} anzeigen`}
               </button>
           </div>`
        : '';

    container.innerHTML = renderTourSelector() + tourCardHtml + toggleHtml + visibleStops.map(stop => renderStopCard(stop)).join('');

    // Tour card listeners
    document.getElementById('btn-tour-start')?.addEventListener('click', handleTourStart);
    document.getElementById('btn-tour-end')?.addEventListener('click', showTourAbschlussModal);
    document.getElementById('btn-kasse')?.addEventListener('click', showCashModal);
    document.getElementById('btn-add-stop')?.addEventListener('click', showAddStopModal);

    document.getElementById('toggle-finished-stops')?.addEventListener('click', () => {
        showFinishedStops = !showFinishedStops;
        renderStops();
    });

    tourData.stops.forEach(stop => {
        const card = document.getElementById('stop-' + stop.id);
        if (!card) return;

        card.querySelector('.btn-arrived')?.addEventListener('click',  () => handleArrived(stop));
        card.querySelector('.btn-undo-' + stop.id)?.addEventListener('click', () => handleUndo(stop));
        card.querySelector('.btn-depart')?.addEventListener('click',   () => handleDepart(stop));
        card.querySelector('.btn-leergut')?.addEventListener('click',  () => showLeergutModal(stop));
        card.querySelector('.btn-pay-' + stop.id)?.addEventListener('click', () => showPaymentModal(stop));

        (stop.order?.items ?? []).forEach(item => {
            card.querySelector('.btn-deliver-' + item.id)
                ?.addEventListener('click', () => handleItemDelivered(stop, item));
            card.querySelector('.btn-nd-' + item.id)
                ?.addEventListener('click', e => showNotDeliveredPicker(stop, item, e.currentTarget));
        });

        card.querySelector('.btn-upload-' + stop.id)
            ?.addEventListener('click', () => handleUpload(stop));

        card.querySelectorAll('.upload-file-link').forEach(link => {
            link.addEventListener('click', async e => {
                e.preventDefault();
                await openUploadFile(Number(link.dataset.uploadId));
            });
        });
    });
}

function renderTourSelector() {
    const all = tourData?.all_tours ?? [];
    if (all.length <= 1) return '';

    const tabs = all.map(entry => {
        const t        = entry.tour;
        const isActive = t.id === tourData.tour?.id;
        const isMine   = t.is_mine;
        const label    = tourLabel(t);
        const style    = isActive
            ? 'background:#3730a3;color:#fff;border-color:#3730a3'
            : (isMine
                ? 'background:#eef2ff;color:#3730a3;border-color:#c7d2fe;font-weight:700'
                : 'background:#f9fafb;color:#6b7280;border-color:#e5e7eb');
        return `<button onclick="switchTour(${t.id})"
                        style="flex:0 0 auto;padding:7px 14px;border-radius:20px;border:1.5px solid;
                               font-size:.8rem;font-weight:600;cursor:pointer;white-space:nowrap;${style}">
                    ${escHtml(label)}${isMine ? ' ★' : ''}
                </button>`;
    }).join('');

    return `<div style="display:flex;gap:8px;overflow-x:auto;padding:10px 12px 0;scrollbar-width:none">
                ${tabs}
            </div>`;
}

function renderTourCard() {
    const tour        = tourData.tour;
    const allFinished = (tourData.stops ?? []).every(s => s.status === 'finished' || s.status === 'skipped');
    const isStarted   = !!tour.started_at;
    const isEnded     = !!tour.ended_at;
    const reg         = tourData.cash_register;

    const startBtn = !isStarted
        ? `<button id="btn-tour-start" class="btn btn-primary" style="flex:1" title="Startet die Tour und aktiviert die Zeiterfassung.">🚚 Tour starten</button>`
        : `<span style="font-size:.8rem;color:#166534;background:#dcfce7;padding:4px 10px;border-radius:6px">
               ✓ Gestartet ${fmtTime(tour.started_at)}</span>`;

    const endBtn = isStarted && !isEnded && allFinished
        ? `<button id="btn-tour-end" class="btn btn-danger" style="flex:1" title="Beendet die Tour. Alle Stopps müssen abgeschlossen sein.">🏁 Tour beenden</button>`
        : (isEnded
            ? `<span style="font-size:.8rem;color:#991b1b;background:#fee2e2;padding:4px 10px;border-radius:6px">
                   ✓ Beendet ${fmtTime(tour.ended_at)}</span>`
            : '');

    const kasseBtn = reg
        ? `<button id="btn-kasse" class="btn btn-secondary" style="font-size:.8rem" title="Kassenentnahme erfassen.">
               💰 ${escHtml(reg.name)}</button>`
        : '';

    const addStopBtn = isStarted && !isEnded
        ? `<button id="btn-add-stop" class="btn btn-secondary" style="font-size:.8rem" title="Zusätzlichen Kunden zur laufenden Tour hinzufügen.">+ Stopp</button>`
        : '';

    const totalVpe = tour.total_vpe ?? 0;

    return `
        <div class="stop-card" style="background:#eef2ff;border:1px solid #c7d2fe">
            <h2 style="color:#3730a3">${escHtml(tourLabel(tour))}</h2>
            <p style="font-size:.8rem;color:#4338ca;margin-bottom:10px">
                ${tourData.stops.length} Stopps · Status: ${escHtml(tour.status)} · Gesamt: ${totalVpe} VPE
            </p>
            <div class="stop-actions">
                ${startBtn}
                ${endBtn}
                ${kasseBtn}
                ${addStopBtn}
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
    const undoBtn = (stop.status === 'arrived' || stop.status === 'finished')
        ? `<button class="btn btn-undo btn-undo-${stop.id}" style="background:#fef9c3;color:#854d0e;border:1px solid #fde047;font-size:.8rem;" title="Macht Ankunft oder Abschluss rückgängig. Bei Abschluss wird die Lagerbuchung zurückgedreht.">↩ Rückgängig</button>`
        : '';

    // Delay warning
    const delayHtml = buildDelayWarning(stop);

    // Leergut button — only if stop is arrived and has leergut items
    const leergutItems = getLeergutItems(stop);
    const leergutBtn = (stop.status === 'arrived' && leergutItems.length > 0)
        ? `<button class="btn btn-secondary btn-leergut" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a" title="Leergut-Rücknahme erfassen (Kisten/Flaschen).">
               ♻ Leergutausgleich (${leergutItems.length} Artikel)
           </button>`
        : '';

    // VPE total for this stop
    const stopVpe = (stop.order?.items ?? []).reduce((sum, i) => sum + i.quantity, 0);
    const vpeHtml = stopVpe > 0
        ? `<p style="font-size:.8rem;color:#64748b;margin:0 0 6px;font-weight:600">📦 ${stopVpe} VPE</p>`
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
    const uploads     = stop.uploads ?? [];
    const canUpload   = stop.status !== 'open';
    const uploadBadge = uploads.length > 0
        ? `<span class="upload-badge">${uploads.length} hochgeladen</span>`
        : '';
    const uploadedFilesList = uploads.map(u => {
        const icon = (u.mime_type ?? '').startsWith('image/') ? '🖼' : '📄';
        const name = escHtml(u.original_name ?? ('Datei #' + u.id));
        return `<a class="upload-file-link" data-upload-id="${u.id}" href="#"
                   style="display:flex;align-items:center;gap:6px;padding:4px 8px;
                          background:#f0f9ff;border-radius:4px;font-size:.8rem;
                          color:#0284c7;margin-bottom:4px;text-decoration:none">
                     ${icon} <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:220px">${name}</span>
                   </a>`;
    }).join('');
    const uploadControls = canUpload
        ? `<div class="upload-controls">
               <input type="file" id="upload-file-${stop.id}" accept="image/*,application/pdf" class="upload-input">
               <button class="btn btn-secondary btn-upload btn-upload-${stop.id}" title="Foto oder Lieferschein hochladen.">📤 Hochladen</button>
           </div>`
        : `<p class="upload-hint">Verfügbar nach Ankunft</p>`;

    const addressHtml = stop.delivery_address
        ? `<p class="stop-address">📍 ${escHtml(stop.delivery_address)}</p>`
        : '';
    const deliveryNoteHtml = stop.delivery_note
        ? `<p class="stop-delivery-note">ℹ ${escHtml(stop.delivery_note)}</p>`
        : '';

    // Abstellort / Abstellen erlaubt
    const dropOffLabels = { keller: 'Keller', einfahrt: 'Einfahrt', eg: 'Erdgeschoss', garage: 'Garage', og1: 'OG 1', sonstiges: 'Sonstiges' };
    const dropOffLoc = stop.drop_off_location;
    let abstellortText = dropOffLabels[dropOffLoc] ?? '';
    if (dropOffLoc === 'sonstiges' && stop.drop_off_location_custom) abstellortText = stop.drop_off_location_custom;
    const abstellortHtml = stop.leave_at_door
        ? `<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:6px 10px;margin-bottom:6px;font-size:.82rem;color:#166534;font-weight:600">✅ Abstellen erlaubt${abstellortText ? ` – ${escHtml(abstellortText)}` : ''}</div>`
        : (abstellortText ? `<p style="font-size:.82rem;color:#374151;margin:0 0 4px">📦 Abstellort: ${escHtml(abstellortText)}</p>` : '');
    const orderNotesHtml = stop.order?.notes
        ? `<div class="order-notes">📋 ${escHtml(stop.order.notes)}</div>`
        : '';

    // Open customer balance (from previous unpaid invoices)
    const openBalance = stop.customer_id ? (tourData?.open_balances?.[stop.customer_id] ?? 0) : 0;
    const openBalanceHtml = openBalance > 0
        ? `<div class="open-balance-badge">⚠ Offene Posten: ${escHtml(fmtEuro(openBalance))}</div>`
        : '';

    // Payments already recorded for this stop
    const paymentsRecorded = stop.payments_recorded ?? [];
    const cashPaidMilli = paymentsRecorded.filter(p => p.method === 'cash').reduce((s, p) => s + p.amount_milli, 0);
    const ecPaidMilli   = paymentsRecorded.filter(p => p.method === 'ec').reduce((s, p)   => s + p.amount_milli, 0);
    const orderTotalMilli = (stop.order?.total_gross_milli ?? 0) + (stop.order?.total_pfand_brutto_milli ?? 0);
    const paymentSummaryHtml = paymentsRecorded.length > 0
        ? `<div class="payment-summary">
               ${cashPaidMilli > 0 ? `<span class="pay-cash">Bar: ${escHtml(fmtEuro(cashPaidMilli))}</span>` : ''}
               ${ecPaidMilli   > 0 ? `<span class="pay-ec">EC: ${escHtml(fmtEuro(ecPaidMilli))}</span>`       : ''}
           </div>`
        : '';

    // Payment button (visible when stop is arrived)
    const payBtn = stop.status === 'arrived'
        ? `<button class="btn btn-payment btn-pay-${stop.id}" style="background:#f0fdf4;color:#166534;border:1px solid #86efac" title="Bar- oder EC-Zahlung für diese Lieferung erfassen.">
               💳 Zahlung
           </button>`
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
            ${abstellortHtml}
            ${deliveryNoteHtml}
            ${orderNotesHtml}
            ${openBalanceHtml}
            ${paymentSummaryHtml}
            ${delayHtml}
            <p class="stop-meta">
                <span class="stop-status ${stop.status}">${statusLabel[stop.status] ?? stop.status}</span>
                ${timeHtml}
            </p>
            ${vpeHtml}
            ${itemsHtml ? `<ul class="items-list">${itemsHtml}</ul>` : ''}
            <div class="stop-actions">
                <button class="btn btn-primary btn-arrived"  ${arrivedDis} title="Markiert diesen Stopp als angekommen. Waren können jetzt übergeben werden.">Angekommen</button>
                ${leergutBtn}
                ${payBtn}
                <button class="btn btn-secondary btn-depart" ${departDis} title="Speichert Abfahrtszeit und schließt den Stopp ab (bucht Lager, Bestellung auf Geliefert).">Abgefahren</button>
                ${undoBtn}
            </div>
            <div class="upload-section">
                <div class="upload-header">
                    <span class="upload-label">📷 Lieferdokument</span>
                    ${uploadBadge}
                </div>
                ${uploadedFilesList}
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
    await enqueueEvent('tour_end', tourData.tour.id, null, {});
    tourData.tour.ended_at = new Date().toISOString();
    tourData.tour.status = 'done';
    renderStops();
    showStatus('Tour beendet.', 'success');
    await updatePendingCount();
    if (navigator.onLine) flushQueue();
}

async function handleUndo(stop) {
    const eventType = stop.status === 'arrived' ? 'undo_arrived' : 'undo_finished';
    const label     = stop.status === 'arrived' ? 'Ankunft rückgängig' : 'Abschluss rückgängig';

    await enqueueEvent(eventType, stop.tour_id, stop.id, {});

    if (stop.status === 'arrived') {
        stop.status     = 'open';
        stop.arrived_at = null;
    } else {
        stop.status      = 'arrived';
        stop.finished_at = null;
        stop.departed_at = null;
    }

    renderStops();
    showStatus(label + ' gemacht.', 'success');
    await updatePendingCount();
    if (navigator.onLine) flushQueue();
}

// ── Tour-Abschluss Modal ──────────────────────────────────────────────────────

function showTourAbschlussModal() {
    if (!tourData?.tour) return;

    document.getElementById('tour-abschluss-modal')?.remove();

    const stops      = tourData.stops ?? [];
    const zielkassen = tourData.zielkassen ?? [];

    // Sum up cash/EC from server-confirmed payments
    let totalCashMilli = 0;
    let totalEcMilli   = 0;
    const stopRows = stops.map(stop => {
        const payments = stop.payments_recorded ?? [];
        const cash = payments.filter(p => p.method === 'cash').reduce((s, p) => s + p.amount_milli, 0);
        const ec   = payments.filter(p => p.method === 'ec').reduce((s, p)   => s + p.amount_milli, 0);
        totalCashMilli += cash;
        totalEcMilli   += ec;
        const name = escHtml(stop.customer_name ?? ('Auftrag #' + stop.order_id));
        if (cash === 0 && ec === 0) {
            return `<div class="abschluss-stop">${name} <span style="color:#9ca3af">keine Zahlung</span></div>`;
        }
        return `<div class="abschluss-stop">${name}
            ${cash > 0 ? `<span class="pay-cash">Bar: ${escHtml(fmtEuro(cash))}</span>` : ''}
            ${ec   > 0 ? `<span class="pay-ec">EC: ${escHtml(fmtEuro(ec))}</span>` : ''}
        </div>`;
    }).join('');

    const einzahlungsMilli = totalCashMilli; // EC goes to bank directly

    const kassenOpts = zielkassen.length > 0
        ? zielkassen.map((k, i) =>
            `<label class="kasse-option">
                <input type="radio" name="zielkasse" value="${k.id}" ${i === 0 ? 'checked' : ''}>
                ${escHtml(k.name)}
            </label>`
          ).join('')
        : '<p style="color:#9ca3af;font-size:.85rem">Keine Zielkassen konfiguriert</p>';

    const modal = document.createElement('div');
    modal.id = 'tour-abschluss-modal';
    modal.innerHTML = `
        <div style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:300;
                    display:flex;align-items:flex-end;justify-content:center">
            <div style="background:#fff;border-radius:16px 16px 0 0;padding:20px;
                        width:100%;max-width:480px;max-height:85dvh;overflow-y:auto">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:14px">🏁 Tour abschließen</h3>

                <div style="background:#f9fafb;border-radius:8px;padding:12px;margin-bottom:14px">
                    <p style="font-size:.78rem;font-weight:600;color:#6b7280;margin-bottom:8px">Zahlungsübersicht</p>
                    ${stopRows || '<p style="color:#9ca3af;font-size:.85rem">Keine Zahlungen erfasst</p>'}
                    <hr style="border:none;border-top:1px solid #e5e7eb;margin:10px 0">
                    <div style="display:flex;justify-content:space-between;font-size:.9rem;font-weight:600">
                        <span>Bar gesamt:</span><span>${escHtml(fmtEuro(totalCashMilli))}</span>
                    </div>
                    ${totalEcMilli > 0 ? `
                    <div style="display:flex;justify-content:space-between;font-size:.85rem;color:#6b7280">
                        <span>EC gesamt:</span><span>${escHtml(fmtEuro(totalEcMilli))}</span>
                    </div>` : ''}
                </div>

                ${zielkassen.length > 0 ? `
                <p style="font-size:.85rem;font-weight:600;color:#374151;margin-bottom:8px">Einzahlung in Kasse</p>
                <div style="margin-bottom:12px">${kassenOpts}</div>

                <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:4px">
                    Betrag (€)
                </label>
                <input id="deposit-amount" type="number" min="0" step="0.01"
                       value="${(einzahlungsMilli / 1000).toFixed(2)}"
                       style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;
                              font-size:1rem;margin-bottom:10px">

                <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:4px">
                    Notiz (optional)
                </label>
                <input id="deposit-note" type="text" placeholder="z.B. Abends eingeworfen …"
                       style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;
                              font-size:.9rem;margin-bottom:14px">
                ` : ''}

                <div style="display:flex;gap:8px">
                    <button id="abschluss-cancel"
                            style="flex:1;padding:10px;border-radius:8px;border:1px solid #d1d5db;
                                   background:#fff;cursor:pointer">Abbrechen</button>
                    <button id="abschluss-confirm"
                            style="flex:1;padding:10px;border-radius:8px;border:none;
                                   background:#1a56db;color:#fff;font-weight:600;cursor:pointer">
                        ✓ Tour beenden
                    </button>
                </div>
            </div>
        </div>`;

    document.body.appendChild(modal);

    modal.querySelector('#abschluss-cancel').addEventListener('click', () => modal.remove());

    modal.querySelector('#abschluss-confirm').addEventListener('click', async () => {
        modal.remove();
        await handleTourEnd();

        if (zielkassen.length > 0) {
            const selectedKasseId = parseInt(
                modal.querySelector('input[name="zielkasse"]:checked')?.value ?? '0', 10
            );
            const depositEur  = parseFloat(modal.querySelector('#deposit-amount')?.value ?? '0');
            const depositNote = (modal.querySelector('#deposit-note')?.value ?? '').trim();
            const depositCents = Math.round(depositEur * 100);

            if (selectedKasseId > 0 && depositCents > 0) {
                await enqueueEvent('cash_deposit', tourData?.tour?.id ?? null, null, {
                    cash_register_id: selectedKasseId,
                    amount_cents:     depositCents,
                    note:             depositNote || null,
                });
                await updatePendingCount();
                if (navigator.onLine) flushQueue();
            }
        }
    });
}

// ── Zahlungs-Modal pro Stop ───────────────────────────────────────────────────

function showPaymentModal(stop) {
    document.getElementById('payment-modal')?.remove();

    const orderTotal  = (stop.order?.total_gross_milli ?? 0) + (stop.order?.total_pfand_brutto_milli ?? 0);
    const alreadyPaid = (stop.payments_recorded ?? []).reduce((s, p) => s + p.amount_milli, 0);
    const remaining   = Math.max(0, orderTotal - alreadyPaid);

    const modal = document.createElement('div');
    modal.id = 'payment-modal';
    modal.innerHTML = `
        <div style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:250;
                    display:flex;align-items:flex-end;justify-content:center">
            <div style="background:#fff;border-radius:16px 16px 0 0;padding:24px 20px;
                        width:100%;max-width:480px;max-height:80dvh;overflow-y:auto">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:2px">💳 Zahlung erfassen</h3>
                <p style="font-size:.8rem;color:#6b7280;margin-bottom:4px">
                    ${escHtml(stop.customer_name ?? 'Kunde')}
                </p>
                <p style="font-size:.85rem;color:#374151;margin-bottom:14px">
                    Offen: <strong>${escHtml(fmtEuro(remaining))}</strong>
                    ${alreadyPaid > 0 ? `<span style="color:#9ca3af"> (bereits: ${escHtml(fmtEuro(alreadyPaid))})</span>` : ''}
                </p>

                <div style="display:flex;gap:8px;margin-bottom:14px">
                    <button id="pay-type-cash" data-method="cash"
                            style="flex:1;padding:10px;border-radius:8px;font-weight:600;cursor:pointer;
                                   border:2px solid #1a56db;background:#eff6ff;color:#1e40af">
                        💵 Bar
                    </button>
                    <button id="pay-type-ec" data-method="ec"
                            style="flex:1;padding:10px;border-radius:8px;font-weight:600;cursor:pointer;
                                   border:2px solid #d1d5db;background:#f9fafb;color:#374151">
                        💳 EC
                    </button>
                </div>

                <label style="display:block;font-size:.8rem;font-weight:500;color:#374151;margin-bottom:4px">
                    Betrag (€)
                </label>
                <input id="pay-amount" type="number" min="0.01" step="0.01"
                       value="${(remaining / 1000).toFixed(2)}"
                       style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;
                              font-size:1.1rem;margin-bottom:12px">

                <div id="ec-ref-row" style="display:none">
                    <label style="display:block;font-size:.8rem;font-weight:500;color:#374151;margin-bottom:4px">
                        Referenz / Beleg-Nr. (optional)
                    </label>
                    <input id="pay-reference" type="text" placeholder="z.B. Terminal-Bon-Nr."
                           style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;
                                  font-size:.9rem;margin-bottom:12px">
                </div>

                <div style="display:flex;gap:8px">
                    <button id="pay-cancel"
                            style="flex:1;padding:10px;border-radius:8px;border:1px solid #d1d5db;
                                   background:#fff;cursor:pointer">Abbrechen</button>
                    <button id="pay-save"
                            style="flex:1;padding:10px;border-radius:8px;border:none;
                                   background:#166534;color:#fff;font-weight:600;cursor:pointer">Speichern</button>
                </div>
            </div>
        </div>`;

    document.body.appendChild(modal);

    let selectedMethod = 'cash';

    const btnCash = modal.querySelector('#pay-type-cash');
    const btnEc   = modal.querySelector('#pay-type-ec');
    const ecRow   = modal.querySelector('#ec-ref-row');

    function setMethod(method) {
        selectedMethod = method;
        btnCash.style.borderColor = method === 'cash' ? '#1a56db' : '#d1d5db';
        btnCash.style.background  = method === 'cash' ? '#eff6ff' : '#f9fafb';
        btnEc.style.borderColor   = method === 'ec'   ? '#1a56db' : '#d1d5db';
        btnEc.style.background    = method === 'ec'   ? '#eff6ff' : '#f9fafb';
        ecRow.style.display       = method === 'ec'   ? '' : 'none';
    }

    btnCash.addEventListener('click', () => setMethod('cash'));
    btnEc.addEventListener('click',   () => setMethod('ec'));

    modal.querySelector('#pay-cancel').addEventListener('click', () => modal.remove());
    modal.querySelector('#pay-save').addEventListener('click', async () => {
        const amountEur = parseFloat(modal.querySelector('#pay-amount').value);
        if (!amountEur || amountEur <= 0) { alert('Bitte gültigen Betrag eingeben.'); return; }

        const amountMilli = Math.round(amountEur * 1000);
        const reference   = modal.querySelector('#pay-reference')?.value.trim() ?? '';

        await enqueueEvent('payment', tourData?.tour?.id ?? null, stop.id, {
            method:      selectedMethod,
            amount_milli: amountMilli,
            reference:   reference || null,
        });

        // Optimistic update: add to local payments_recorded
        const s = tourData?.stops.find(s => s.id === stop.id);
        if (s) {
            s.payments_recorded = s.payments_recorded ?? [];
            s.payments_recorded.push({ amount_milli: amountMilli, method: selectedMethod });
        }

        modal.remove();
        renderStops();
        showStatus(`Zahlung (${selectedMethod === 'cash' ? 'Bar' : 'EC'}: ${fmtEuro(amountMilli)}) gespeichert.`, 'success');
        await updatePendingCount();
        if (navigator.onLine) flushQueue();
    });
}

// ── Ad-hoc Stopp hinzufügen ───────────────────────────────────────────────────

let _addStopDebounceTimer = null;

function showAddStopModal() {
    if (!navigator.onLine) {
        showStatus('Stopp hinzufügen erfordert eine Internetverbindung.', 'error');
        return;
    }
    if (!tourData?.tour) return;

    document.getElementById('add-stop-modal')?.remove();

    let selectedCustomer = null;

    const modal = document.createElement('div');
    modal.id = 'add-stop-modal';
    modal.innerHTML = `
        <div style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:250;
                    display:flex;align-items:flex-end;justify-content:center">
            <div style="background:#fff;border-radius:16px 16px 0 0;padding:24px 20px;
                        width:100%;max-width:480px;max-height:85dvh;overflow-y:auto">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:14px">+ Stopp hinzufügen</h3>

                <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:4px">
                    Kundensuche (Name oder Kundennummer)
                </label>
                <input id="add-stop-search" type="text" placeholder="Mind. 2 Zeichen eingeben…"
                       autocomplete="off"
                       style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;
                              font-size:.95rem;margin-bottom:6px">

                <div id="add-stop-results"
                     style="border:1px solid #e5e7eb;border-radius:8px;max-height:200px;
                            overflow-y:auto;margin-bottom:12px;display:none"></div>

                <div id="add-stop-selected" style="display:none;
                     background:#f0fdf4;border:1px solid #86efac;border-radius:8px;
                     padding:10px 12px;margin-bottom:12px;font-size:.88rem;color:#166534">
                </div>

                <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:4px">
                    Notiz (optional)
                </label>
                <input id="add-stop-note" type="text" placeholder="z.B. Zusatzlieferung, Kundenanfrage …"
                       style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;
                              font-size:.9rem;margin-bottom:14px">

                <div style="display:flex;gap:8px">
                    <button id="add-stop-cancel"
                            style="flex:1;padding:10px;border-radius:8px;border:1px solid #d1d5db;
                                   background:#fff;cursor:pointer">Abbrechen</button>
                    <button id="add-stop-confirm" disabled
                            style="flex:1;padding:10px;border-radius:8px;border:none;
                                   background:#1a56db;color:#fff;font-weight:600;cursor:pointer;opacity:.4">
                        Stopp hinzufügen
                    </button>
                </div>
            </div>
        </div>`;

    document.body.appendChild(modal);

    const searchInput   = modal.querySelector('#add-stop-search');
    const resultsBox    = modal.querySelector('#add-stop-results');
    const selectedBox   = modal.querySelector('#add-stop-selected');
    const confirmBtn    = modal.querySelector('#add-stop-confirm');

    function selectCustomer(customer) {
        selectedCustomer = customer;
        searchInput.value = customer.display_name + (customer.customer_number ? ' (' + customer.customer_number + ')' : '');
        resultsBox.style.display = 'none';
        selectedBox.textContent  = '✓ ' + customer.display_name
            + (customer.city ? ' · ' + customer.city : '')
            + (customer.customer_number ? ' · #' + customer.customer_number : '');
        selectedBox.style.display = '';
        confirmBtn.disabled = false;
        confirmBtn.style.opacity = '1';
    }

    function renderResults(customers) {
        if (customers.length === 0) {
            resultsBox.innerHTML = '<p style="padding:10px;font-size:.85rem;color:#9ca3af">Keine Ergebnisse</p>';
            resultsBox.style.display = '';
            return;
        }
        resultsBox.innerHTML = customers.map(c =>
            `<div class="add-stop-result" data-id="${c.id}"
                  style="padding:10px 12px;cursor:pointer;font-size:.88rem;border-bottom:1px solid #f3f4f6">
                <strong>${escHtml(c.display_name)}</strong>
                ${c.customer_number ? `<span style="color:#9ca3af"> · #${escHtml(c.customer_number)}</span>` : ''}
                ${c.city ? `<span style="color:#6b7280"> · ${escHtml(c.city)}</span>` : ''}
            </div>`
        ).join('');
        resultsBox.style.display = '';

        resultsBox.querySelectorAll('.add-stop-result').forEach(el => {
            el.addEventListener('mouseenter', () => { el.style.background = '#f9fafb'; });
            el.addEventListener('mouseleave', () => { el.style.background = ''; });
            el.addEventListener('click', () => {
                const c = customers.find(c => c.id === parseInt(el.dataset.id, 10));
                if (c) selectCustomer(c);
            });
        });
    }

    searchInput.addEventListener('input', () => {
        const q = searchInput.value.trim();
        clearTimeout(_addStopDebounceTimer);
        selectedCustomer = null;
        selectedBox.style.display = 'none';
        confirmBtn.disabled = true;
        confirmBtn.style.opacity = '.4';

        if (q.length < 2) { resultsBox.style.display = 'none'; return; }

        _addStopDebounceTimer = setTimeout(async () => {
            try {
                const resp = await apiFetch('GET', '/customers?q=' + encodeURIComponent(q) + '&limit=10');
                if (resp.ok) renderResults(await resp.json());
            } catch { /* ignore */ }
        }, 300);
    });

    modal.querySelector('#add-stop-cancel').addEventListener('click', () => modal.remove());

    modal.querySelector('#add-stop-confirm').addEventListener('click', async () => {
        if (!selectedCustomer) return;
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Wird hinzugefügt…';

        const note = modal.querySelector('#add-stop-note').value.trim();

        try {
            const resp = await apiFetch('POST', '/add-stop', {
                customer_id: selectedCustomer.id,
                note:        note || null,
            });

            if (resp.status === 401) { handleAuthError(); return; }

            if (!resp.ok) {
                const err = await resp.json().catch(() => ({}));
                throw new Error(err.error ?? 'Fehler beim Hinzufügen des Stopps');
            }

            const result = await resp.json();
            modal.remove();

            // Insert new stop into local tourData
            tourData.stops.push(result.stop);
            renderStops();

            const msg = result.reused_existing_order
                ? `Stopp hinzugefügt (Bestellung #${result.order_id} wiederverwendet).`
                : 'Neuer Stopp hinzugefügt.';
            showStatus(msg, 'success');

            // Scroll to new stop
            setTimeout(() => {
                document.getElementById('stop-' + result.stop.id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);

        } catch (err) {
            showStatus('Fehler: ' + err.message, 'error');
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Stopp hinzufügen';
            confirmBtn.style.opacity = '1';
        }
    });

    searchInput.focus();
}

// ── Leergut-Modal (mit Pfandausgleich-Wahl) ───────────────────────────────────

async function showLeergutModal(stop) {
    const items = getLeergutItems(stop);
    if (items.length === 0) return;

    const isDepositExempt = stop.is_deposit_exempt ?? false;
    const listHtml = items.map(i =>
        `<div style="display:flex;justify-content:space-between;font-size:.88rem;padding:4px 0">
            <span>${escHtml(i.leergut_name)}</span>
            <span>${i.qty}×</span>
        </div>`
    ).join('');

    document.getElementById('leergut-modal')?.remove();

    const modal = document.createElement('div');
    modal.id = 'leergut-modal';
    modal.innerHTML = `
        <div style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:250;
                    display:flex;align-items:flex-end;justify-content:center">
            <div style="background:#fff;border-radius:16px 16px 0 0;padding:24px 20px;
                        width:100%;max-width:480px">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:10px">♻ Leergut erfassen</h3>
                <div style="background:#fef3c7;border-radius:8px;padding:10px 12px;margin-bottom:14px">
                    ${listHtml}
                </div>

                ${!isDepositExempt ? `
                <p style="font-size:.82rem;font-weight:600;color:#374151;margin-bottom:8px">Abwicklung:</p>
                <label style="display:flex;align-items:center;gap:10px;margin-bottom:8px;cursor:pointer">
                    <input type="radio" name="leergut-mode" value="adjust" checked>
                    <span>
                        <strong>Pfandausgleich buchen</strong><br>
                        <span style="font-size:.78rem;color:#6b7280">Betrag wird vom Rechnungsbetrag abgezogen</span>
                    </span>
                </label>
                <label style="display:flex;align-items:center;gap:10px;margin-bottom:14px;cursor:pointer">
                    <input type="radio" name="leergut-mode" value="record_only">
                    <span>
                        <strong>Nur erfassen</strong><br>
                        <span style="font-size:.78rem;color:#6b7280">Mengen protokollieren, kein Abzug</span>
                    </span>
                </label>
                ` : `
                <p style="font-size:.82rem;color:#6b7280;margin-bottom:14px">
                    ℹ Nur Mengen werden erfasst (Pfandausgleich-befreit).
                </p>
                `}

                <div style="display:flex;gap:8px">
                    <button id="leergut-cancel"
                            style="flex:1;padding:10px;border-radius:8px;border:1px solid #d1d5db;
                                   background:#fff;cursor:pointer">Abbrechen</button>
                    <button id="leergut-confirm"
                            style="flex:1;padding:10px;border-radius:8px;border:none;
                                   background:#92400e;color:#fff;font-weight:600;cursor:pointer">Bestätigen</button>
                </div>
            </div>
        </div>`;

    document.body.appendChild(modal);

    modal.querySelector('#leergut-cancel').addEventListener('click', () => modal.remove());
    modal.querySelector('#leergut-confirm').addEventListener('click', async () => {
        const mode = modal.querySelector('input[name="leergut-mode"]:checked')?.value ?? 'adjust';
        const adjustOrder = !isDepositExempt && mode === 'adjust';

        modal.remove();
        await commitLeergutausgleich(stop, items, adjustOrder);
    });
}

async function commitLeergutausgleich(stop, items, adjustOrder) {
    const payload = {
        order_id:     stop.order_id,
        adjust_order: adjustOrder,
        items:        items.map(i => ({
            wawi_artikel_nr:        i.leergut_art_nr ?? '',
            qty:                    i.qty,
            leergut_name:           i.leergut_name,
            unit_price_net_milli:   i.unit_price_net_milli,
            unit_price_gross_milli: i.unit_price_gross_milli,
            tax_rate_percent:       19,
        })),
    };

    await enqueueEvent('leergutausgleich', tourData?.tour?.id ?? null, stop.id, payload);
    showStatus(
        adjustOrder ? 'Leergutausgleich gebucht.' : 'Leergut erfasst (ohne Abzug).',
        'success',
    );
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
    const current     = tourData?.stops.find(s => s.id === stop.id) ?? stop;
    const wasArrived  = current.status === 'arrived';

    if (wasArrived) {
        const items        = current.order?.items ?? [];
        const totalOrdered = items.reduce((s, i) => s + i.quantity, 0);
        if (totalOrdered > 0) {
            const fulfillments   = current.item_fulfillments ?? [];
            const totalDelivered = fulfillments.reduce((s, f) => s + (f.delivered_qty ?? 0), 0);
            const totalND        = fulfillments.reduce((s, f) => s + (f.not_delivered_qty ?? 0), 0);
            if (totalDelivered + totalND < totalOrdered) {
                showDepartConfirm(current, totalDelivered, totalOrdered);
                return;
            }
        }
    }

    await proceedDepart(current);
}

function showDepartConfirm(stop, totalDelivered, totalOrdered) {
    document.getElementById('depart-confirm-' + stop.id)?.remove();

    const card = document.getElementById('stop-' + stop.id);
    if (!card) return;

    const leergutItems = getLeergutItems(stop);
    const leergutBtn   = leergutItems.length > 0
        ? `<button id="depart-leergut-${stop.id}" class="btn btn-secondary"
               style="font-size:.85rem;background:#fef3c7;color:#92400e;border:1px solid #fde68a">
               ♻ Leergut erfassen
           </button>`
        : '';

    const msg = totalDelivered === 0
        ? 'Kein Artikel wurde als geliefert markiert.'
        : `Nur ${totalDelivered} von ${totalOrdered} VPE als geliefert markiert.`;

    const panel = document.createElement('div');
    panel.id = 'depart-confirm-' + stop.id;
    panel.innerHTML = `
        <div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:8px;
                    padding:12px;margin:8px 0 4px">
            <p style="font-weight:600;color:#92400e;margin:0 0 4px">⚠ Unvollständig geliefert?</p>
            <p style="font-size:.85rem;color:#78350f;margin:0 0 10px">
                ${escHtml(msg)} Wirklich abfahren?
            </p>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button id="depart-confirm-yes-${stop.id}" class="btn btn-primary" style="font-size:.85rem">Ja, abfahren</button>
                <button id="depart-confirm-no-${stop.id}"  class="btn btn-outline"  style="font-size:.85rem">Zurück</button>
                ${leergutBtn}
            </div>
        </div>`;

    card.appendChild(panel);

    document.getElementById(`depart-confirm-yes-${stop.id}`)?.addEventListener('click', async () => {
        panel.remove();
        await proceedDepart(stop);
    });
    document.getElementById(`depart-confirm-no-${stop.id}`)?.addEventListener('click', () => {
        panel.remove();
    });
    if (leergutItems.length > 0) {
        document.getElementById(`depart-leergut-${stop.id}`)?.addEventListener('click', () => {
            panel.remove();
            showLeergutModal(stop);
        });
    }
}

async function proceedDepart(stop) {
    const current    = tourData?.stops.find(s => s.id === stop.id) ?? stop;
    const wasArrived = current.status === 'arrived';
    if (wasArrived) {
        await enqueueEvent('finished', tourData?.tour?.id ?? null, current.id, {});
        updateStopStatusLocally(current, 'finished');
    }
    await enqueueEvent('depart', tourData?.tour?.id ?? null, current.id, {});
    updateStopStatusLocally(current, current.status, { departed_at: new Date().toISOString() });
    renderStops();
    showStatus(wasArrived ? 'Abgefahren und Stopp abgeschlossen.' : 'Abfahrt gespeichert.', 'success');
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
        if (s) {
            s.uploads_count = (s.uploads_count ?? 0) + 1;
            if (!s.uploads) s.uploads = [];
            s.uploads.push({
                id:            result.upload_id,
                mime_type:     file.type,
                original_name: file.name,
                created_at:    new Date().toISOString(),
            });
        }

        if (fileInput) fileInput.value = '';

        renderStops();
        showStatus('Datei erfolgreich hochgeladen.', 'success');
        await updatePendingCount();
        if (navigator.onLine) flushQueue();
    } catch (err) {
        showStatus('Upload fehlgeschlagen: ' + err.message, 'error');
    }
}

async function openUploadFile(uploadId) {
    if (!navigator.onLine) { showStatus('Datei-Ansicht nur online verfügbar.', 'error'); return; }
    showStatus('Lade Datei…', 'info');
    try {
        const resp = await fetch(window.__DRIVER_CONFIG__.apiBase + '/uploads/' + uploadId, {
            headers: { 'Authorization': 'Bearer ' + getToken(), 'Accept': '*/*' },
        });
        if (resp.status === 401) { handleAuthError(); return; }
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        const blob    = await resp.blob();
        const blobUrl = URL.createObjectURL(blob);
        window.open(blobUrl, '_blank');
        showStatus('', '');
    } catch (e) {
        showStatus('Datei konnte nicht geladen werden: ' + e.message, 'error');
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
    if (window.__DRIVER_CONFIG__?.isSessionAuth) {
        showStatus('Sitzung abgelaufen. Bitte neu anmelden.', 'error');
        setTimeout(() => { window.location.href = '/anmelden'; }, 1500);
        return;
    }
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(TOKEN_KEY_LGY);
    showStatus('Sitzung abgelaufen. Bitte neu anmelden.', 'error');
    setTimeout(() => renderLoginPrompt(), 1500);
}

function fmtTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
}

function fmtEuro(milliCents) {
    return (milliCents / 1000).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
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
