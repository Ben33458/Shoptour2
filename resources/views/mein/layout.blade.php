<!DOCTYPE html>
<html lang="de" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Mein Bereich') — Kolabri</title>
    <link rel="stylesheet" href="{{ asset('admin/admin.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        // Apply theme before paint to avoid flash
        (function() {
            var t = localStorage.getItem('theme') || 'dark';
            document.getElementById('html-root').setAttribute('data-theme', t);
        })();
    </script>
    <style>
        /* Dark-mode input fix */
        [data-theme="dark"] input:not([type="checkbox"]):not([type="radio"]),
        [data-theme="dark"] select,
        [data-theme="dark"] textarea {
            background: #1a2235;
            color: var(--c-text);
            border-color: var(--c-border);
            color-scheme: dark;
        }
        [data-theme="dark"] input::placeholder,
        [data-theme="dark"] textarea::placeholder {
            color: var(--c-muted);
        }

        /* Mein layout */
        .mein-layout { display: flex; flex-direction: column; min-height: 100vh; }

        .mein-topbar {
            background: var(--c-surface);
            border-bottom: 1px solid var(--c-border);
            padding: 0 20px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 14px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .mein-logo { font-weight: 700; color: var(--c-primary); font-size: 15px; }

        .mein-nav { display: flex; gap: 4px; align-items: center; }

        .mein-nav a {
            color: var(--c-muted);
            text-decoration: none;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 13px;
            transition: background .15s, color .15s;
        }

        .mein-nav a:hover { background: var(--c-bg); color: var(--c-text); }
        .mein-nav a.active { background: var(--c-bg); color: var(--c-primary); font-weight: 600; }

        .mein-topbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .mein-topbar-right strong { color: var(--c-text); }

        .mein-topbar-right a,
        .mein-topbar-right button {
            font-size: 12px;
            color: var(--c-muted);
            text-decoration: none;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid var(--c-border);
            background: none;
            cursor: pointer;
            transition: border-color .15s, color .15s;
        }

        .mein-topbar-right a:hover,
        .mein-topbar-right button:hover { border-color: var(--c-primary); color: var(--c-text); }

        .mein-content {
            flex: 1;
            padding: 24px 20px;
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
        }

        .mein-card {
            background: var(--c-surface);
            border: 1px solid var(--c-border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .mein-card-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--c-muted);
            text-transform: uppercase;
            letter-spacing: .07em;
            margin-bottom: 12px;
        }

        /* Dark toggle (matching admin.css .dark-toggle style) */
        .dark-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--c-muted);
            background: none;
            border: 1px solid var(--c-border);
            border-radius: 20px;
            padding: 4px 10px;
            cursor: pointer;
        }
        .dark-toggle:hover { border-color: var(--c-primary); color: var(--c-text); }
        .dark-toggle .toggle-track { width: 28px; height: 16px; background: var(--c-border); border-radius: 8px; position: relative; transition: background .2s; }
        .dark-toggle .toggle-track::after { content:''; position:absolute; left:2px; top:2px; width:12px; height:12px; background:#fff; border-radius:50%; transition:left .2s; }
        [data-theme="dark"] .dark-toggle .toggle-track { background: var(--c-primary); }
        [data-theme="dark"] .dark-toggle .toggle-track::after { left: 14px; }
    </style>
    @stack('head')
</head>
<body>
<div class="mein-layout">
    <header class="mein-topbar">
        <div class="mein-logo">
            <img src="{{ asset('images/kolabri_logo.png') }}" alt="Kolabri Getränke" style="height:28px;width:auto;vertical-align:middle">
        </div>

        <nav class="mein-nav">
            <a href="{{ route('mein.dashboard') }}"
               class="{{ request()->routeIs('mein.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('mein.schicht') }}"
               class="{{ request()->routeIs('mein.schicht*') ? 'active' : '' }}">Schichtbericht</a>
            <a href="{{ route('mein.aufgaben') }}"
               class="{{ request()->routeIs('mein.aufgaben*') ? 'active' : '' }}">Aufgaben</a>
            <a href="{{ route('mein.news') }}"
               class="{{ request()->routeIs('mein.news*') ? 'active' : '' }}">Neuigkeiten</a>
            <a href="{{ route('mein.urlaub') }}"
               class="{{ request()->routeIs('mein.urlaub*') ? 'active' : '' }}">Urlaub</a>
            @if(isset($employee) && $employee->cash_register_id)
            <a href="{{ route('mein.kasse') }}"
               class="{{ request()->routeIs('mein.kasse*') ? 'active' : '' }}">Kasse</a>
            @endif
        </nav>

        <div class="mein-topbar-right">
            <strong>{{ $employee->full_name ?? '' }}</strong>

            <button class="dark-toggle" onclick="toggleTheme()" type="button">
                <span id="theme-icon">☀️</span>
                <div class="toggle-track"></div>
            </button>

            <a href="/timeclock">← Stempeluhr</a>

            <form method="POST" action="{{ route('mein.logout') }}" style="display:inline;margin:0;">
                @csrf
                <button type="submit">Abmelden</button>
            </form>
        </div>
    </header>

    <main class="mein-content">
        @if(session('success'))
            <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-error" style="margin-bottom:16px;">
                @foreach($errors->all() as $err) {{ $err }}<br> @endforeach
            </div>
        @endif

        @yield('content')
    </main>
</div>
@stack('scripts')
<script>
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
updateThemeIcon();
</script>
</body>
</html>
