<!DOCTYPE html>
<html lang="de" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stempeluhr</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
    <script>
        // Apply theme before paint to avoid flash
        (function() {
            var t = localStorage.getItem('theme') || 'dark';
            document.getElementById('html-root').setAttribute('data-theme', t);
        })();
    </script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --tc-bg:       #0f172a;
            --tc-surface:  #1e293b;
            --tc-border:   #334155;
            --tc-text:     #f1f5f9;
            --tc-muted:    #94a3b8;
            --tc-primary:  #60a5fa;
            --tc-input-bg: #0f172a;
        }

        [data-theme="light"] {
            --tc-bg:       #f1f5f9;
            --tc-surface:  #ffffff;
            --tc-border:   #cbd5e1;
            --tc-text:     #0f172a;
            --tc-muted:    #64748b;
            --tc-primary:  #2563eb;
            --tc-input-bg: #ffffff;
        }

        body {
            background: var(--tc-bg);
            color: var(--tc-text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            transition: background .2s, color .2s;
        }

        /* Top-right controls */
        .top-controls {
            position: fixed;
            top: 1rem;
            right: 1rem;
            display: flex;
            align-items: center;
            gap: .75rem;
            z-index: 10;
        }

        /* Dark toggle */
        .dark-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--tc-muted);
            background: var(--tc-surface);
            border: 1px solid var(--tc-border);
            border-radius: 20px;
            padding: 4px 10px;
            cursor: pointer;
            transition: border-color .15s;
        }
        .dark-toggle:hover { border-color: var(--tc-primary); color: var(--tc-text); }
        .dark-toggle .tt { width: 28px; height: 16px; background: var(--tc-border); border-radius: 8px; position: relative; transition: background .2s; }
        .dark-toggle .tt::after { content:''; position:absolute; left:2px; top:2px; width:12px; height:12px; background:#fff; border-radius:50%; transition: left .2s; }
        [data-theme="dark"]  .dark-toggle .tt { background: var(--tc-primary); }
        [data-theme="dark"]  .dark-toggle .tt::after { left: 14px; }

        /* Device toggle */
        .device-toggle {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .8rem;
            color: var(--tc-muted);
        }
        .toggle-label { font-weight: 600; font-size: .78rem; }
        .toggle-label.active { color: var(--tc-primary); }

        .toggle-switch { position: relative; width: 40px; height: 22px; cursor: pointer; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
        .toggle-track { position: absolute; inset: 0; background: var(--tc-border); border-radius: 22px; transition: background .2s; }
        .toggle-track::after { content:''; position:absolute; left:3px; top:3px; width:16px; height:16px; background: var(--tc-muted); border-radius:50%; transition: left .2s, background .2s; }
        .toggle-switch input:checked + .toggle-track { background: #1d4ed8; }
        .toggle-switch input:checked + .toggle-track::after { left: 21px; background: var(--tc-primary); }

        /* Clock */
        .clock-header { text-align: center; margin-bottom: 2rem; }
        .clock-time { font-size: 4rem; font-weight: 700; letter-spacing: .05em; color: var(--tc-primary); }
        .clock-date { font-size: 1.1rem; color: var(--tc-muted); margin-top: .25rem; }

        /* Card */
        .card {
            background: var(--tc-surface);
            border: 2px solid var(--tc-border);
            border-radius: 16px;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            transition: background .2s, border-color .2s;
        }
        .card h2 { text-align: center; font-size: 1.4rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--tc-text); }

        /* Form */
        .form-field { display: flex; flex-direction: column; gap: .35rem; margin-bottom: 1rem; }
        .form-field label { font-size: .8rem; color: var(--tc-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
        .form-field input {
            background: var(--tc-input-bg);
            border: 1px solid var(--tc-border);
            border-radius: 8px;
            color: var(--tc-text);
            font-size: 1.3rem;
            padding: .65rem .9rem;
            width: 100%;
            outline: none;
            transition: border-color .15s;
        }
        .form-field input:focus { border-color: var(--tc-primary); }

        .error-box {
            background: #7f1d1d;
            border: 1px solid #dc2626;
            color: #fecaca;
            border-radius: 8px;
            padding: .75rem 1rem;
            font-size: .9rem;
            margin-bottom: 1rem;
            text-align: center;
        }

        /* Buttons */
        .btn { width: 100%; padding: .9rem; border: none; border-radius: 10px; font-size: 1.05rem; font-weight: 700; cursor: pointer; transition: opacity .15s, transform .1s; }
        .btn:hover  { opacity: .85; }
        .btn:active { transform: scale(.98); }
        .btn:disabled { opacity: .4; cursor: not-allowed; transform: none; }
        .btn-primary { background: #1d4ed8; color: #fff; margin-top: .5rem; }
        .btn-green   { background: #16a34a; color: #fff; }
        .btn-red     { background: #dc2626; color: #fff; }
        .btn-orange  { background: #d97706; color: #fff; }
        .btn-blue    { background: #0284c7; color: #fff; }

        /* Status */
        .status-name  { font-size: 1.5rem; font-weight: 700; color: var(--tc-text); text-align: center; }
        .status-area  { font-size: .95rem; color: var(--tc-muted); text-align: center; margin-top: .2rem; margin-bottom: 1rem; }
        .net-time     { font-size: 2.4rem; font-weight: 700; color: var(--tc-primary); text-align: center; margin-bottom: .25rem; }
        .net-time-label { text-align: center; font-size: .75rem; color: var(--tc-muted); margin-bottom: 1rem; }

        .status-badge { display:inline-block; padding:.3rem .9rem; border-radius:999px; font-size:.82rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
        .badge-clocked_out { background: var(--tc-surface); color: var(--tc-muted); border: 1px solid var(--tc-border); }
        .badge-active      { background: #14532d; color: #4ade80; }
        .badge-on_break    { background: #78350f; color: #fbbf24; }

        .action-row { display: flex; gap: .75rem; margin-top: 1.5rem; }
        .action-row .btn { flex: 1; }

        .btn-back { width:100%; background:none; border:1px solid var(--tc-border); color:var(--tc-muted); border-radius:8px; padding:.5rem; cursor:pointer; font-size:.85rem; margin-top:.75rem; transition:border-color .15s,color .15s; }
        .btn-back:hover { border-color: var(--tc-primary); color: var(--tc-primary); }

        .footer-link { margin-top: 2rem; text-align: center; color: var(--tc-muted); font-size: .85rem; }
        .footer-link a { color: var(--tc-primary); text-decoration: none; }

        @media (max-width: 480px) { .clock-time { font-size: 2.8rem; } }
    </style>
</head>
<body>

<div x-data="timeclock()" x-init="init()">

    <div class="top-controls">
        {{-- Dark/Light toggle --}}
        <button class="dark-toggle" onclick="toggleTheme()" type="button">
            <span id="theme-icon">☀️</span>
            <div class="tt"></div>
        </button>

        {{-- Device toggle --}}
        <div class="device-toggle">
            <span class="toggle-label" :class="{ active: !isPrivate }">Terminal</span>
            <label class="toggle-switch">
                <input type="checkbox" x-model="isPrivate" @change="setDeviceType()">
                <span class="toggle-track"></span>
            </label>
            <span class="toggle-label" :class="{ active: isPrivate }">Mein Gerät</span>
        </div>
    </div>

    {{-- Clock --}}
    <div class="clock-header">
        <div class="clock-time" id="live-clock">{{ now()->format('H:i:s') }}</div>
        <div class="clock-date">{{ now()->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}</div>
    </div>

    {{-- Login screen --}}
    <div x-show="screen === 'login'">
        <div class="card">
            <h2>Anmelden</h2>

            <div class="error-box" style="display:none" x-show="errorMsg" x-text="errorMsg"></div>

            <div class="form-field">
                <label for="emp-number">Personalnummer</label>
                <input type="text" id="emp-number" x-model="empNumber"
                       placeholder="z.B. MA001" autocomplete="off"
                       @keydown.enter="$refs.pinInput.focus()">
            </div>

            <div class="form-field">
                <label for="emp-pin">PIN</label>
                <input type="password" id="emp-pin" x-ref="pinInput" x-model="pin"
                       maxlength="4" pattern="\d{4}" inputmode="numeric"
                       placeholder="• • • •"
                       @keydown.enter="authenticate()">
            </div>

            <button type="button" class="btn btn-primary" @click="authenticate()" :disabled="loading">
                <span x-show="!loading" x-text="isPrivate ? 'Zum Mitarbeiterportal →' : 'Anmelden'"></span>
                <span x-show="loading" style="display:none">Bitte warten …</span>
            </button>
        </div>
    </div>

    {{-- Status screen --}}
    <div x-show="screen === 'status'" style="display:none">
        <div class="card" style="max-width:460px;">
            <div class="status-name" x-text="statusData.name"></div>
            <div class="status-area" x-show="statusData.shift_area" x-text="statusData.shift_area"></div>

            <div class="net-time" x-text="formatMinutes(statusData.net_minutes_today)"></div>
            <div class="net-time-label">Nettoarbeitszeit heute</div>

            <div style="text-align:center;">
                <span class="status-badge"
                      :class="'badge-' + statusData.status"
                      x-text="statusLabel(statusData.status)"></span>
            </div>

            <div class="error-box" style="display:none; margin-top:1rem;" x-show="errorMsg" x-text="errorMsg"></div>

            <div class="action-row" style="display:none" x-show="statusData.status === 'clocked_out'">
                <button class="btn btn-green" @click="doAction('clock_in')" :disabled="loading">Landen</button>
            </div>
            <div class="action-row" style="display:none" x-show="statusData.status === 'active'">
                <button class="btn btn-red"    @click="doAction('clock_out')"   :disabled="loading">Abflug</button>
                <button class="btn btn-orange" @click="doAction('break_start')" :disabled="loading">Pause starten</button>
            </div>
            <div class="action-row" style="display:none" x-show="statusData.status === 'on_break'">
                <button class="btn btn-blue" @click="doAction('break_end')" :disabled="loading">Pause beenden</button>
            </div>

            <button class="btn-back" @click="goBack()">← Zurück zur Anmeldung</button>
        </div>
    </div>

</div>

<div class="footer-link">
    <a href="{{ route('onboarding.start') }}">Neu hier? Onboarding starten →</a>
    &nbsp;·&nbsp;
    <a href="{{ route('admin.employees.dashboard') }}">Admin-Bereich</a>
</div>

<script>
function timeclock() {
    return {
        screen: 'login',
        empNumber: '',
        pin: '',
        loading: false,
        errorMsg: '',
        isPrivate: false,
        deviceToken: null,
        statusData: { name: '', shift_area: null, net_minutes_today: 0, status: 'clocked_out' },

        init() {
            updateThemeIcon();
            this.deviceToken = localStorage.getItem('device_token');
            if (!this.deviceToken) {
                this.deviceToken = crypto.randomUUID();
                localStorage.setItem('device_token', this.deviceToken);
                this.initDevice();
            } else {
                this.loadDeviceType();
            }
        },

        async initDevice() {
            try {
                const res = await fetch('/timeclock/device/init', {
                    method: 'POST', headers: this.headers(),
                    body: JSON.stringify({ token: this.deviceToken }),
                });
                if (res.ok) { const d = await res.json(); this.isPrivate = d.device_type === 'private'; }
            } catch (_) {}
        },

        async loadDeviceType() {
            try {
                const res = await fetch('/timeclock/device/' + encodeURIComponent(this.deviceToken));
                if (res.ok) { const d = await res.json(); this.isPrivate = d.device_type === 'private'; }
            } catch (_) {}
        },

        async setDeviceType() {
            try {
                await fetch('/timeclock/device/set', {
                    method: 'POST', headers: this.headers(),
                    body: JSON.stringify({ token: this.deviceToken, device_type: this.isPrivate ? 'private' : 'public' }),
                });
            } catch (_) {}
        },

        async authenticate() {
            if (!this.empNumber.trim() || !this.pin.trim()) {
                this.errorMsg = 'Bitte Personalnummer und PIN eingeben.';
                return;
            }
            this.loading = true;
            this.errorMsg = '';
            try {
                const res = await fetch('/timeclock/authenticate', {
                    method: 'POST', headers: this.headers(),
                    body: JSON.stringify({
                        employee_number: this.empNumber.trim(),
                        pin: this.pin,
                        device_token: this.deviceToken,
                        force_portal: this.isPrivate ? 1 : 0,
                    }),
                });
                const data = await res.json();
                if (data.redirect) { window.location.href = data.redirect; return; }
                if (!res.ok || !data.success) {
                    this.errorMsg = data.message || 'Anmeldung fehlgeschlagen.';
                } else {
                    this.statusData = data;
                    this.screen = 'status';
                }
            } catch (_) {
                this.errorMsg = 'Verbindungsfehler. Bitte erneut versuchen.';
            } finally {
                this.loading = false;
            }
        },

        async doAction(action) {
            this.loading = true;
            this.errorMsg = '';
            try {
                const res = await fetch('/timeclock/action', {
                    method: 'POST', headers: this.headers(),
                    body: JSON.stringify({ employee_number: this.empNumber, pin: this.pin, action }),
                });
                const data = await res.json();
                if (!res.ok || !data.success) { this.errorMsg = data.message || 'Fehler.'; }
                else { this.statusData = data; }
            } catch (_) {
                this.errorMsg = 'Verbindungsfehler.';
            } finally {
                this.loading = false;
            }
        },

        goBack() { this.screen = 'login'; this.pin = ''; this.errorMsg = ''; },

        formatMinutes(mins) {
            if (!mins) return '0:00';
            return Math.floor(mins / 60) + ':' + String(mins % 60).padStart(2, '0');
        },

        statusLabel(s) {
            return { clocked_out: 'Ausgestempelt', active: 'Eingestempelt', on_break: 'Pause' }[s] || s;
        },

        headers() {
            return { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content };
        },
    };
}

function toggleTheme() {
    var html = document.getElementById('html-root');
    var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
    updateThemeIcon();
}

function updateThemeIcon() {
    var t = document.getElementById('html-root').getAttribute('data-theme');
    var el = document.getElementById('theme-icon');
    if (el) el.textContent = t === 'dark' ? '☀️' : '🌙';
}

(function tick() {
    var el = document.getElementById('live-clock');
    if (el) {
        var n = new Date();
        el.textContent = String(n.getHours()).padStart(2,'0') + ':' + String(n.getMinutes()).padStart(2,'0') + ':' + String(n.getSeconds()).padStart(2,'0');
    }
    setTimeout(tick, 1000);
})();
</script>
</body>
</html>
