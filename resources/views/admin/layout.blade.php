<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — Kolabri</title>
    <link rel="stylesheet" href="{{ asset('admin/admin.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @stack('head')
</head>
<body>
<div class="layout">

    {{-- ── Sidebar Navigation ── --}}
    <nav class="nav">
        <div class="nav-logo">🍺 Kolabri Admin</div>

        <div class="nav-section">Bestellungen</div>
        <a href="{{ route('admin.orders.index') }}"
           class="{{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
            Bestellungen
        </a>
        <a href="{{ route('admin.invoices.index') }}"
           class="{{ request()->routeIs('admin.invoices.*') ? 'active' : '' }}">
            Rechnungen
        </a>

        <div class="nav-section">Produkte</div>
        <a href="{{ route('admin.products.index') }}"
           class="{{ request()->routeIs('admin.products.*') || request()->routeIs('admin.lmiv.*') ? 'active' : '' }}">
            Produktliste
        </a>
        <a href="{{ route('admin.imports.lmiv') }}"
           class="{{ request()->routeIs('admin.imports.lmiv*') ? 'active' : '' }}">
            LMIV importieren
        </a>

        <div class="nav-section">Stammdaten</div>
        <a href="{{ route('admin.customers.index') }}"
           class="{{ request()->routeIs('admin.customers.*') ? 'active' : '' }}">
            Kunden
        </a>
        <a href="{{ route('admin.suppliers.index') }}"
           class="{{ request()->routeIs('admin.suppliers.*') ? 'active' : '' }}">
            Lieferanten
        </a>
        <a href="{{ route('admin.customer-groups.index') }}"
           class="{{ request()->routeIs('admin.customer-groups.*') ? 'active' : '' }}">
            Kundengruppen
        </a>
        <a href="{{ route('admin.brands.index') }}"
           class="{{ request()->routeIs('admin.brands.*') ? 'active' : '' }}">
            Marken
        </a>
        <a href="{{ route('admin.product-lines.index') }}"
           class="{{ request()->routeIs('admin.product-lines.*') ? 'active' : '' }}">
            Produktlinien
        </a>
        <a href="{{ route('admin.categories.index') }}"
           class="{{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
            Kategorien
        </a>
        <a href="{{ route('admin.gebinde.index') }}"
           class="{{ request()->routeIs('admin.gebinde.*') ? 'active' : '' }}">
            Gebinde
        </a>
        <a href="{{ route('admin.pfand-items.index') }}"
           class="{{ request()->routeIs('admin.pfand-items.*') ? 'active' : '' }}">
            Pfandpositionen
        </a>
        <a href="{{ route('admin.pfand-sets.index') }}"
           class="{{ request()->routeIs('admin.pfand-sets.*') ? 'active' : '' }}">
            Pfandsets
        </a>
        <a href="{{ route('admin.imports.customers') }}"
           class="{{ request()->routeIs('admin.imports.customers*') ? 'active' : '' }}">
            Kunden importieren
        </a>
        <a href="{{ route('admin.imports.products') }}"
           class="{{ request()->routeIs('admin.imports.products*') ? 'active' : '' }}">
            Produkte importieren
        </a>
        <a href="{{ route('admin.imports.suppliers') }}"
           class="{{ request()->routeIs('admin.imports.suppliers*') ? 'active' : '' }}">
            Lieferanten importieren
        </a>
        <a href="{{ route('admin.imports.brands') }}"
           class="{{ request()->routeIs('admin.imports.brands*') ? 'active' : '' }}">
            Marken importieren
        </a>
        <a href="{{ route('admin.imports.customer-groups') }}"
           class="{{ request()->routeIs('admin.imports.customer-groups*') ? 'active' : '' }}">
            Kundengruppen importieren
        </a>

        <div class="nav-section">Berichte</div>
        <a href="{{ route('admin.reports.index') }}"
           class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
            Berichte
        </a>

        <div class="nav-section">Integrationen</div>
        <a href="{{ route('admin.integrations.lexoffice') }}"
           class="{{ request()->routeIs('admin.integrations.*') ? 'active' : '' }}">
            Lexoffice
        </a>

        <div class="nav-section">Aufgaben</div>
        <a href="{{ route('admin.tasks.index') }}"
           class="{{ request()->routeIs('admin.tasks.*') ? 'active' : '' }}">
            Aufgaben-Queue
        </a>

        <div class="nav-section">Inhalte</div>
        <a href="{{ route('admin.pages.index') }}"
           class="{{ request()->routeIs('admin.pages.*') ? 'active' : '' }}">
            Seiten (Impressum etc.)
        </a>

        <div class="nav-section">System</div>
        <a href="{{ route('admin.driver-tokens.index') }}"
           class="{{ request()->routeIs('admin.driver-tokens.*') ? 'active' : '' }}">
            Fahrer-Token
        </a>
        <a href="{{ route('admin.diagnostics') }}"
           class="{{ request()->routeIs('admin.diagnostics') ? 'active' : '' }}">
            Diagnose
        </a>
        <a href="{{ route('admin.deploy.index') }}"
           class="{{ request()->routeIs('admin.deploy.*') ? 'active' : '' }}">
            Deployment
        </a>

        <div class="nav-footer">
            {{ Auth::user()->name }}<br>
            <a href="{{ route('logout') }}"
               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                Abmelden
            </a>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">
                @csrf
            </form>
        </div>
    </nav>

    {{-- ── Main Content ── --}}
    <div class="main">
        <div class="topbar">
            <h1>@yield('title', 'Dashboard')</h1>
            <div class="topbar-actions">@yield('actions')</div>
        </div>

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
@stack('scripts')
</body>
</html>
