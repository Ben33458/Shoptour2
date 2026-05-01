@php
    $darkMode = auth()->check() ? auth()->user()->dark_mode : false;
@endphp
<!DOCTYPE html>
<html lang="de" data-theme="{{ $darkMode ? 'dark' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Mitarbeiter') — Kolabri</title>
    <link rel="stylesheet" href="{{ asset('admin/admin.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .emp-layout { display:flex; flex-direction:column; min-height:100vh; }
        .emp-topbar {
            background:var(--c-surface);
            border-bottom:1px solid var(--c-border);
            padding:0 24px;
            height:56px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            font-size:14px;
        }
        .emp-topbar-logo { font-weight:700; color:var(--c-primary); font-size:16px; }
        .emp-topbar-nav { display:flex; gap:20px; align-items:center; }
        .emp-topbar-nav a {
            color:var(--c-muted);
            text-decoration:none;
            font-weight:500;
            padding:4px 0;
            border-bottom:2px solid transparent;
        }
        .emp-topbar-nav a.active,
        .emp-topbar-nav a:hover {
            color:var(--c-primary);
            border-bottom-color:var(--c-primary);
        }
        .emp-topbar-user {
            color:var(--c-muted);
            font-size:13px;
            display:flex;
            gap:12px;
            align-items:center;
        }
        .emp-topbar-user button {
            background:none;
            border:none;
            cursor:pointer;
            font-size:12px;
            color:var(--c-muted);
        }
        .emp-topbar-user button:hover { color:var(--c-danger); }
        .emp-content {
            flex:1;
            padding:28px 32px;
            max-width:1200px;
            margin:0 auto;
            width:100%;
            box-sizing:border-box;
        }
    </style>
    @stack('head')
</head>
<body>
<div class="emp-layout">
    <header class="emp-topbar">
        <div class="emp-topbar-logo">🍺 Kolabri Mitarbeiter</div>
        @php
            $navEmployee = \App\Models\Employee\Employee::where('email', auth()->user()->email)->first();
        @endphp
        <nav class="emp-topbar-nav">
            <a href="{{ route('employee.tasks.index') }}"
               class="{{ request()->routeIs('employee.tasks.*') ? 'active' : '' }}">
                Aufgaben
            </a>
            @if($navEmployee?->cash_register_id)
            <a href="{{ route('employee.cash.index') }}"
               class="{{ request()->routeIs('employee.cash.*') ? 'active' : '' }}">
                Kasse
            </a>
            @endif
            @if(auth()->user()->isAdmin())
            <a href="{{ route('admin.orders.index') }}">Admin →</a>
            @endif
        </nav>
        <div class="emp-topbar-user">
            <span>{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button type="submit">Abmelden</button>
            </form>
        </div>
    </header>

    <main class="emp-content">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>
</div>
@stack('scripts')
<script>
function toggleDarkMode() {
    const html = document.documentElement;
    const next = html.dataset.theme !== 'dark';
    html.dataset.theme = next ? 'dark' : '';
    fetch('{{ route('preferences.dark-mode') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ dark: next }),
    });
}
</script>
</body>
</html>
