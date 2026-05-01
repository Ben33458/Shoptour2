@php
    $darkMode = auth()->check() ? auth()->user()->dark_mode : (request()->cookie('dark_mode') === '1');
@endphp
<!DOCTYPE html>
<html lang="de" data-theme="{{ $darkMode ? 'dark' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — Kolabri</title>
    <link rel="stylesheet" href="{{ asset('admin/admin.css') }}?v={{ filemtime(public_path('admin/admin.css')) }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        /* ── Pagination vars (neutral admin colors) ── */
        :root { --pag-bg:var(--c-surface,#fff); --pag-border:var(--c-border,#e2e8f0); --pag-text:var(--c-text,#1e293b); --pag-muted:var(--c-muted,#94a3b8); --pag-hover:var(--c-bg,#f8fafc); --pag-hover-border:var(--c-primary,#3b82f6); --pag-active-bg:var(--c-primary,#3b82f6); --pag-active-text:#fff; }

        /* Nav subsection label (e.g. LMIV under Katalog) */
        .nav-subsection {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--c-muted, #94a3b8);
            padding: 8px 16px 2px 24px;
        }
        /* Indented sub-links */
        .nav .nav-sub {
            padding-left: 28px !important;
        }
        /* Aktionen-Dropdown (details/summary pattern — JS-free) */
        .actions-dropdown {
            position: relative;
            display: inline-block;
        }
        .actions-dropdown > summary {
            list-style: none;
            cursor: pointer;
            user-select: none;
        }
        .actions-dropdown > summary::-webkit-details-marker { display: none; }
        /* Invisible overlay closes dropdown on outside click */
        .actions-dropdown[open] > summary::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 9;
        }
        .actions-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 4px);
            z-index: 10;
            background: var(--c-surface);
            border: 1px solid var(--c-border, #e2e8f0);
            border-radius: 6px;
            box-shadow: 0 4px 16px rgba(0,0,0,.12);
            min-width: 200px;
            padding: 4px 0;
            white-space: nowrap;
        }
        .actions-menu a {
            display: block;
            padding: 8px 16px;
            font-size: .875rem;
            color: inherit;
            text-decoration: none;
        }
        .actions-menu a:hover {
            background: var(--c-surface, #f8fafc);
        }
        .actions-menu-divider {
            border: none;
            border-top: 1px solid var(--c-border, #e2e8f0);
            margin: 4px 0;
        }
    /* Checkbox-Fokus-Ring für Tastatur-Navigation */
    .row-check:focus-visible {
        outline: 2px solid var(--c-primary);
        outline-offset: 2px;
        border-radius: 2px;
    }
    /* ── Table enhance: search bar + sortable headers ── */
    .tbl-enhance-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px 0;
    }
    .tbl-enhance-search {
        padding: 6px 10px;
        border: 1px solid var(--c-border);
        border-radius: var(--radius);
        font-size: .8125rem;
        background: var(--c-surface);
        color: var(--c-text);
        width: 260px;
        outline: none;
        transition: border-color .15s;
    }
    .tbl-enhance-search:focus {
        border-color: var(--c-primary);
    }
    .tbl-enhance-hint {
        font-size: .7rem;
        color: var(--c-muted);
    }
    .tbl-sortable {
        cursor: pointer;
        user-select: none;
        white-space: nowrap;
    }
    .tbl-sortable:hover {
        color: var(--c-primary);
    }
    .tbl-sort-asc,
    .tbl-sort-desc {
        color: var(--c-primary);
    }
    /* ── Section sub-navigation ── */
    .section-nav {
        background: var(--c-surface);
        border-bottom: 1px solid var(--c-border);
        padding: 0 20px;
        overflow-x: auto;
        display: flex;
        align-items: stretch;
        gap: 0;
        white-space: nowrap;
        scrollbar-width: none;
    }
    .section-nav::-webkit-scrollbar { display: none; }
    /* Group container */
    .snav-group {
        display: flex;
        align-items: stretch;
        gap: 0;
    }
    .snav-group + .snav-group {
        border-left: 1px solid var(--c-border);
        margin-left: 4px;
        padding-left: 4px;
    }
    /* Group label */
    .snav-label {
        font-size: .65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--c-muted);
        padding: 0 6px 0 8px;
        display: flex;
        align-items: center;
    }
    /* Tab links */
    .section-nav a {
        display: flex;
        align-items: center;
        gap: 5px;
        padding: 9px 11px;
        font-size: .8rem;
        color: var(--c-muted);
        text-decoration: none;
        border-bottom: 2px solid transparent;
        transition: color .15s, border-color .15s;
        white-space: nowrap;
    }
    .section-nav a:hover {
        color: var(--c-text);
        text-decoration: none;
    }
    .section-nav a.active {
        color: var(--c-primary);
        border-bottom-color: var(--c-primary);
        font-weight: 500;
    }
    /* Badge inside sub-nav */
    .snav-badge {
        background: #f59e0b;
        color: #fff;
        border-radius: 10px;
        padding: 1px 6px;
        font-size: .65rem;
        font-weight: 700;
    }
    </style>
    @stack('head')
</head>
<body>
<div class="layout">

    {{-- ── Sidebar Navigation ── --}}
    @include('admin._partials.sidebar')

    {{-- ── Main Content ── --}}
    <div class="main">
        <div class="topbar">
            <h1>@yield('title', 'Dashboard')</h1>
            <div class="topbar-actions">
                @yield('actions')
                <button class="dark-toggle" onclick="toggleDarkMode()" title="Dark Mode umschalten" type="button">
                    <span id="dm-icon">{{ $darkMode ? '☀️' : '🌙' }}</span>
                    <div class="toggle-track"><div class="toggle-thumb"></div></div>
                </button>
            </div>
        </div>

        {{-- ── Section sub-navigation ── --}}
        @include('admin._partials.section-nav')

        <div class="content">
            {{-- Flash messages --}}
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning">{{ session('warning') }}</div>
            @endif

            {{-- Validation errors --}}
            @if($errors->any())
                <div class="alert alert-error">
                    <strong>Validierungsfehler:</strong>
                    <ul style="margin:6px 0 0; padding-left:18px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </div>

</div>
{{-- ── Page debug footer ── --}}
@php
    $routeShort  = str_replace('admin.', '', Route::currentRouteName() ?? '?');
    $pageUrl     = request()->path();
    $now         = now();
    $weekStart   = $now->copy()->startOfWeek(\Carbon\Carbon::MONDAY)->format('d.m.Y');
    $weekEnd     = $now->copy()->endOfWeek(\Carbon\Carbon::SUNDAY)->format('d.m.Y');
    $kw          = $now->isoWeek();
@endphp
<div id="page-footer" style="position:fixed;bottom:0;right:0;left:0;z-index:800;background:var(--c-surface,#f8fafc);border-top:1px solid var(--c-border,#e5e7eb);padding:3px 16px;display:flex;gap:16px;align-items:center;font-size:.72rem;color:var(--c-muted,#9ca3af);font-family:monospace;pointer-events:none">
    <span title="Route">{{ $routeShort }}</span>
    <span style="opacity:.4">·</span>
    <span title="Pfad">/{{ $pageUrl }}</span>
    <span style="opacity:.4">·</span>
    <span title="Benutzer">{{ auth()->user()->name ?? '—' }}</span>
    <span style="opacity:.4">·</span>
    <span title="Zeit">{{ $now->format('H:i') }}</span>
    <span style="opacity:.4">·</span>
    <span title="Aktuelle Woche">{{ $weekStart }} – {{ $weekEnd }} KW {{ $kw }}</span>
</div>

@stack('scripts')
<script src="{{ asset('admin/table-enhance.js') }}?v={{ filemtime(public_path('admin/table-enhance.js')) }}" defer></script>
<script>
/*
 * Checkbox-Keyboard-Navigation (global)
 * ─────────────────────────────────────
 * Tab  : springt vom fokussierten .row-check zur nächsten Checkbox
 * Shift+Tab : geht zurück
 * Space: Browser-Standard (toggle) bleibt erhalten
 *
 * Damit können Mehrfach-Auswahl-Tabellen komplett per Tastatur bedient werden
 * ohne dass der Fokus in zwischenliegenden Aktions-Buttons hängen bleibt.
 */
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Tab') return;
    var focused = document.activeElement;
    if (!focused || !focused.classList.contains('row-check')) return;

    var checks = Array.from(document.querySelectorAll('.row-check:not([disabled])'));
    var idx    = checks.indexOf(focused);
    if (idx === -1) return;

    var next = e.shiftKey ? checks[idx - 1] : checks[idx + 1];
    if (!next) return;

    e.preventDefault();
    next.focus();
});

function toggleDarkMode() {
    const html = document.documentElement;
    const isDark = html.dataset.theme === 'dark';
    const next = !isDark;
    html.dataset.theme = next ? 'dark' : '';
    document.getElementById('dm-icon').textContent = next ? '☀️' : '🌙';
    fetch('{{ route('preferences.dark-mode') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ dark: next }),
    });
}
</script>
</body>
</html>
