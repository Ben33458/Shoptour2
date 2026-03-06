<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1a56db">
    <title>Fahrer-App – Getränke-Shop</title>

    <link rel="manifest" href="/driver/manifest.json">
    <link rel="apple-touch-icon" href="/driver/icons/icon-192.png">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            color: #111827;
            min-height: 100dvh;
        }

        #app-root {
            max-width: 480px;
            margin: 0 auto;
            padding: 0 0 80px;
            min-height: 100dvh;
        }

        /* ---------- Header ---------- */
        header {
            background: #1a56db;
            color: #fff;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        header h1 { font-size: 1.1rem; font-weight: 600; flex: 1; }
        #sync-btn {
            background: rgba(255,255,255,.2);
            border: none;
            color: #fff;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: .875rem;
        }
        #sync-btn:disabled { opacity: .5; cursor: default; }

        /* ---------- Status banner ---------- */
        #status-bar {
            padding: 8px 16px;
            font-size: .8rem;
            background: #dbeafe;
            color: #1e40af;
            display: none;
        }
        #status-bar.visible { display: block; }
        #status-bar.error   { background: #fee2e2; color: #991b1b; }
        #status-bar.success { background: #dcfce7; color: #166534; }

        /* ---------- Stop cards ---------- */
        .stop-card {
            background: #fff;
            border-radius: 10px;
            margin: 12px 12px 0;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        .stop-card h2 { font-size: 1rem; font-weight: 600; margin-bottom: 4px; }
        .stop-meta { font-size: .8rem; color: #6b7280; margin-bottom: 12px; }
        .stop-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: .75rem;
            font-weight: 600;
        }
        .stop-status.open      { background: #e0e7ff; color: #3730a3; }
        .stop-status.arrived   { background: #fef3c7; color: #92400e; }
        .stop-status.finished  { background: #d1fae5; color: #065f46; }

        .stop-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
        .btn {
            padding: 8px 14px;
            border-radius: 6px;
            border: none;
            font-size: .875rem;
            font-weight: 500;
            cursor: pointer;
        }
        .btn-primary   { background: #1a56db; color: #fff; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .btn-danger    { background: #ef4444; color: #fff; }
        .btn:disabled  { opacity: .4; cursor: default; }

        /* ---------- Stop card extras ---------- */
        .stop-address       { font-size: .8rem; color: #4b5563; margin-bottom: 3px; }
        .stop-delivery-note { font-size: .8rem; color: #92400e; margin-bottom: 6px; }
        .stop-time          { font-size: .75rem; color: #9ca3af; margin-left: 6px; }

        /* ---------- Items list ---------- */
        .items-list { list-style: none; margin-top: 10px; }
        .items-list li {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: .875rem;
            gap: 8px;
        }
        .items-list li:last-child { border-bottom: none; }
        .item-info   { flex: 1; min-width: 0; }
        .item-name   { display: block; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .item-numbers { display: block; font-size: .75rem; color: #6b7280; margin-top: 1px; }
        .qty-ok { color: #065f46; }
        .qty-nd { color: #991b1b; }
        .item-btns { display: flex; gap: 4px; align-items: center; flex-shrink: 0; }
        .item-del-btn {
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 4px;
            padding: 3px 8px;
            cursor: pointer;
            font-size: .75rem;
            color: #166534;
            font-weight: 600;
        }
        .item-nd-btn {
            background: #fff1f2;
            border: 1px solid #fca5a5;
            border-radius: 4px;
            padding: 3px 7px;
            cursor: pointer;
            font-size: .75rem;
            color: #b91c1c;
            font-weight: 600;
        }
        .item-del-btn:disabled,
        .item-nd-btn:disabled { opacity: .35; cursor: default; }

        /* ---------- Not-delivered reason picker ---------- */
        .nd-picker { padding: 0 0 4px; }
        .nd-picker-inner {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            border-radius: 6px;
            padding: 10px 12px;
            margin: 4px 0;
        }
        .nd-picker-title { display: block; font-size: .8rem; color: #7f1d1d; margin-bottom: 8px; }
        .nd-reason-list  { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
        .nd-reason-btn {
            background: #fff;
            border: 1px solid #fca5a5;
            border-radius: 14px;
            padding: 4px 12px;
            cursor: pointer;
            font-size: .8rem;
            color: #b91c1c;
        }
        .nd-reason-btn:hover { background: #fee2e2; }
        .nd-cancel-btn {
            background: none;
            border: none;
            color: #6b7280;
            font-size: .78rem;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
        }

        /* ---------- Upload section ---------- */
        .upload-section {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #f3f4f6;
        }
        .upload-header   { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .upload-label    { font-size: .8rem; font-weight: 600; color: #374151; }
        .upload-badge    { background: #dbeafe; color: #1e40af; font-size: .7rem; font-weight: 600; padding: 2px 8px; border-radius: 10px; }
        .upload-controls { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .upload-input    { font-size: .8rem; flex: 1; min-width: 0; max-width: 200px; }
        .upload-hint     { font-size: .78rem; color: #9ca3af; }

        /* ---------- Empty state (initial loading spinner) ---------- */
        .empty-state {
            text-align: center;
            color: #9ca3af;
            padding: 60px 24px;
        }
        .empty-state svg { width: 64px; height: 64px; margin-bottom: 16px; }

        /* ---------- Empty state card (no tour / no stops) ---------- */
        .empty-state-card {
            background: #fff;
            border-radius: 10px;
            margin: 24px 12px 0;
            padding: 32px 20px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        .empty-state-card h2   { font-size: 1.1rem; font-weight: 600; margin-bottom: 6px; }
        .empty-state-card .empty-date { color: #6b7280; margin-bottom: 20px; font-size: .9rem; }
        .date-picker-row { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-top: 12px; }
        .date-picker-row input[type=date] {
            border: 1px solid #d1d5db; border-radius: 6px;
            padding: 7px 10px; font-size: .875rem;
        }
        .date-picker-row input[type=date]:focus { outline: none; border-color: #1a56db; }

        /* ---------- Offline banner ---------- */
        #offline-banner {
            background: #fef3c7;
            color: #78350f;
            text-align: center;
            padding: 6px;
            font-size: .8rem;
            display: none;
        }
        body.offline #offline-banner { display: block; }

        /* ---------- Connectivity indicator ---------- */
        #connectivity {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: .75rem;
            padding: 4px 9px;
            border-radius: 12px;
            background: rgba(255,255,255,.15);
            white-space: nowrap;
            flex-shrink: 0;
        }
        #connectivity .conn-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #4ade80;
            flex-shrink: 0;
        }
        #connectivity.offline .conn-dot { background: #f87171; }

        /* ---------- Sync status bar ---------- */
        #sync-status-bar {
            display: flex;
            gap: 12px;
            align-items: center;
            padding: 5px 14px;
            font-size: .75rem;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            flex-wrap: wrap;
        }
        #sync-pending    { color: #374151; font-weight: 600; }
        #sync-last-time  { color: #9ca3af; }
        #sync-last-error { color: #dc2626; }

        /* ---------- General note card ---------- */
        .note-card {
            background: #fff;
            border-radius: 10px;
            margin: 12px 12px 0;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        .note-card h3 { font-size: .9rem; font-weight: 600; margin-bottom: 10px; color: #374151; }
        .note-card textarea {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px;
            font-size: .875rem;
            font-family: inherit;
            resize: vertical;
            margin-bottom: 10px;
        }
        .note-card textarea:focus { outline: none; border-color: #1a56db; }
        .note-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .note-hint {
            margin-top: 8px;
            font-size: .8rem;
            color: #166534;
            background: #dcfce7;
            padding: 6px 10px;
            border-radius: 4px;
        }
        .note-hint.hidden { display: none; }

        /* ---------- Rejected events panel ---------- */
        #rejected-panel {
            margin: 12px 12px 0;
            border: 1px solid #fca5a5;
            border-radius: 10px;
            overflow: hidden;
        }
        #rejected-panel.hidden { display: none; }
        #rejected-toggle {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            background: #fee2e2;
            cursor: pointer;
            font-size: .85rem;
            font-weight: 600;
            color: #991b1b;
        }
        .toggle-icon { transition: transform .2s; }
        .toggle-icon.open { transform: rotate(180deg); }
        #rejected-list {
            list-style: none;
            background: #fff;
            padding: 0 14px;
            max-height: 280px;
            overflow-y: auto;
        }
        #rejected-list.hidden { display: none; }
        #rejected-list li {
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: .8rem;
        }
        #rejected-list li:last-child { border-bottom: none; }
        .rej-type   { font-weight: 600; color: #374151; }
        .rej-id     { color: #9ca3af; font-size: .72rem; font-family: monospace; margin-top: 1px; }
        .rej-reason { color: #dc2626; margin-top: 2px; }
    </style>
</head>
<body>

<div id="offline-banner">⚠ Kein Internet – Aktionen werden gespeichert und beim nächsten Sync übertragen.</div>

<div id="app-root">
    <header>
        <h1 id="tour-title">Lade Tour…</h1>
        <div id="connectivity" class="online" title="Verbindungsstatus">
            <span class="conn-dot"></span>
            <span id="conn-label">Online</span>
        </div>
        <button id="sync-btn">Sync</button>
    </header>

    <div id="status-bar"></div>

    <div id="sync-status-bar">
        <span id="sync-pending">0 ausstehend</span>
        <span id="sync-last-time"></span>
        <span id="sync-last-error" style="display:none"></span>
    </div>

    {{-- General note section – visible even without a tour --}}
    <section class="note-card">
        <h3>Notiz für Disposition</h3>
        <textarea id="note-text" rows="3" placeholder="Notiz eingeben…"></textarea>
        <div class="note-actions">
            <button id="note-save-btn" class="btn btn-secondary">Notiz speichern (offline)</button>
            <button id="note-sync-btn" class="btn btn-primary">Jetzt synchronisieren</button>
        </div>
        <div id="note-hint" class="note-hint hidden">In Warteschlange gespeichert</div>
    </section>

    {{-- Rejected events panel – hidden until rejections occur --}}
    <div id="rejected-panel" class="hidden">
        <div id="rejected-toggle">
            <span id="rejected-badge">⚠ 0 Ereignisse abgelehnt</span>
            <span class="toggle-icon" id="rejected-arrow">▼</span>
        </div>
        <ul id="rejected-list" class="hidden"></ul>
    </div>

    <div id="stops-container">
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0
                    1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621
                    0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0
                    0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554
                    48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0
                    4.5v-4.5m0 0h-12"/>
            </svg>
            <p>Bitte warte…</p>
        </div>
    </div>
</div>

{{-- PWA bootstrap config injected by server --}}
<script>
window.__DRIVER_CONFIG__ = {
    apiBase: '/api/driver',
    token: null, // Set after login (stored in localStorage)
};
</script>
<style>
  #driver-auth-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,.35);
    display: none; align-items: center; justify-content: center; z-index: 99999;
  }
  #driver-auth-card {
    width: min(520px, 92vw); background: #fff; border-radius: 14px;
    box-shadow: 0 12px 32px rgba(0,0,0,.2); padding: 18px;
    font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  }
  #driver-auth-card h2 { margin: 0 0 10px; font-size: 18px; }
  #driver-auth-card p { margin: 0 0 12px; color: #444; font-size: 14px; line-height: 1.35; }
  #driver-token { width: 100%; padding: 10px 12px; font-size: 14px; border: 1px solid #ccc; border-radius: 10px; }
  #driver-auth-actions { display: flex; gap: 10px; margin-top: 12px; justify-content: flex-end; }
  #driver-auth-actions button { padding: 9px 12px; border-radius: 10px; border: 1px solid #bbb; background: #f6f6f6; cursor: pointer; }
  #driver-auth-actions button.primary { background: #2d6cdf; border-color: #2d6cdf; color: #fff; }
  #driver-auth-error { display:none; margin-top: 10px; color: #b00020; font-size: 13px; }
  #driver-auth-hint { font-size: 12px; color: #666; margin-top: 8px; }
</style>

<div id="driver-auth-overlay" role="dialog" aria-modal="true" aria-labelledby="driver-auth-title">
  <div id="driver-auth-card">
    <h2 id="driver-auth-title">Driver Token erforderlich</h2>
    <p>Für die Fahrer-API wird ein Bearer-Token benötigt. Bitte den <b>Plain Token</b> einfügen (nicht den Hash).</p>
    <input id="driver-token" type="password" placeholder="Bearer Token (Plain)" autocomplete="off" />
    <div id="driver-auth-error">Token ungültig oder Server nicht erreichbar.</div>
    <div id="driver-auth-actions">
      <button id="driver-auth-clear" type="button">Token löschen</button>
      <button id="driver-auth-save" class="primary" type="button">Speichern & testen</button>
    </div>
    <div id="driver-auth-hint">Tipp: Token wird lokal im Browser gespeichert (localStorage).</div>
  </div>
</div>

<script>
(function () {
  const STORAGE_KEY = 'driver_api_token_plain';

  const overlay = document.getElementById('driver-auth-overlay');
  const input   = document.getElementById('driver-token');
  const errBox  = document.getElementById('driver-auth-error');
  const btnSave = document.getElementById('driver-auth-save');
  const btnClr  = document.getElementById('driver-auth-clear');

  function getToken() {
    return localStorage.getItem(STORAGE_KEY) || '';
  }
  function setToken(t) {
    localStorage.setItem(STORAGE_KEY, t);
  }
  function clearToken() {
    localStorage.removeItem(STORAGE_KEY);
  }
  function showOverlay(message) {
    errBox.style.display = message ? 'block' : 'none';
    if (message) errBox.textContent = message;
    input.value = getToken();
    overlay.style.display = 'flex';
    setTimeout(() => input.focus(), 0);
  }
  function hideOverlay() {
    overlay.style.display = 'none';
    errBox.style.display = 'none';
  }

  // Monkeypatch fetch: add Authorization header to /api/driver/*
  const originalFetch = window.fetch.bind(window);
  window.fetch = async function(input, init) {
    try {
      const url = (typeof input === 'string') ? input : (input && input.url) ? input.url : '';
      const isDriverApi = url.includes('/api/driver/');
      if (!isDriverApi) return await originalFetch(input, init);

      const token = getToken().replace(/[^\x20-\x7E]/g, '');
      if (!token) {
        showOverlay('Bitte Token setzen, sonst ist die Fahrer-API nicht nutzbar.');
        // still perform request without token (will 401), so app can handle
        return await originalFetch(input, init);
      }

      init = init || {};
      init.headers = init.headers || {};
      // normalize headers
      const headers = (init.headers instanceof Headers) ? init.headers : new Headers(init.headers);
      if (!headers.has('Authorization')) headers.set('Authorization', 'Bearer ' + token);
      init.headers = headers;

      const resp = await originalFetch(input, init);
      if (resp.status === 401) {
        showOverlay('401 – Token fehlt/ist falsch. Bitte korrigieren.');
      }
      return resp;
    } catch (e) {
      showOverlay('Fetch-Fehler: ' + (e && e.message ? e.message : String(e)));
      throw e;
    }
  };

  // UI actions
  btnClr.addEventListener('click', () => {
    clearToken();
    input.value = '';
    showOverlay('Token gelöscht. Bitte neuen Token einfügen.');
  });

  btnSave.addEventListener('click', async () => {
    let t = (input.value || '').trim();
// harte Reinigung: entferne alles außerhalb ASCII (inkl. unsichtbarer Unicode-Zeichen)
t = t.replace(/[^\x20-\x7E]/g, '');
    if (!t) return showOverlay('Token leer. Bitte Plain Token einfügen.');
    setToken(t);

    // Quick test: bootstrap (today)
    const today = new Date().toISOString().slice(0,10);
    try {
      const h = new Headers();
h.set('Authorization', 'Bearer ' + t);

const r = await originalFetch('/api/driver/bootstrap?date=' + today, {
  method: 'GET',
  headers: h,
});
      if (r.status === 200) {
        hideOverlay();
        // let the app continue; optional reload to force re-bootstrap
        window.location.reload();
        return;
      }
      showOverlay('Token-Test fehlgeschlagen (HTTP ' + r.status + ').');
    } catch (e) {
      showOverlay('Token-Test fehlgeschlagen: ' + (e && e.message ? e.message : String(e)));
    }
  });

  // On load: if no token, show overlay
  if (!getToken()) showOverlay('');
})();
</script>
<script src="/driver/app.js" defer></script>
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/driver/sw.js')
                .then(r => console.log('[SW] registered', r.scope))
                .catch(e => console.error('[SW] registration failed', e));
        });
    }
</script>

</body>
</html>
