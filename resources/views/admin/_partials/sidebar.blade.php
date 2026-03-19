{{--
    Admin Sidebar Navigation
    ───────────────────────
    Zentral definierte Menüstruktur. Alle Änderungen am Menü hier vornehmen.
    Active-State per request()->routeIs(). Rechte-Checks hier ergänzen falls nötig.

    Reihenfolge:
      1 Verkauf     → Bestellungen, Rechnungen
      2 Katalog     → Produkte, Kategorien, Marken, Produktlinien, Gebinde, LMIV
      3 Preise      → Kundengruppen
      4 Lager       → Lagerorte, Bestände, Bewegungen
      5 Kunden
      6 Lieferanten
      7 Pfand       → Pfandpositionen, Pfandsets
      8 Inhalte     → CMS-Seiten
      9 Berichte
     10 Integrationen → Lexoffice
     11 System       → Aufgaben-Queue, Fahrer-Token, Diagnose, Deployment
--}}
<nav class="nav">
    <div class="nav-logo">🍺 Kolabri Admin</div>

    {{-- ── 1. Verkauf ── --}}
    <div class="nav-section">Verkauf</div>
    <a href="{{ route('admin.orders.index') }}"
       class="{{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
        Bestellungen
    </a>
    <a href="{{ route('admin.invoices.index') }}"
       class="{{ request()->routeIs('admin.invoices.*') ? 'active' : '' }}">
        Rechnungen
    </a>

    {{-- ── 2. Katalog ── --}}
    <div class="nav-section">Katalog</div>
    <a href="{{ route('admin.products.index') }}"
       class="{{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
        Produkte
    </a>
    <a href="{{ route('admin.categories.index') }}"
       class="{{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
        Kategorien
    </a>
    <a href="{{ route('admin.brands.index') }}"
       class="{{ request()->routeIs('admin.brands.*') ? 'active' : '' }}">
        Marken
    </a>
    <a href="{{ route('admin.product-lines.index') }}"
       class="{{ request()->routeIs('admin.product-lines.*') ? 'active' : '' }}">
        Produktlinien
    </a>
    <a href="{{ route('admin.gebinde.index') }}"
       class="{{ request()->routeIs('admin.gebinde.*') ? 'active' : '' }}">
        Gebinde
    </a>
    {{-- LMIV als Unterbereich --}}
    <div class="nav-subsection">LMIV</div>
    <a href="{{ route('admin.lmiv.index') }}"
       class="nav-sub {{ request()->routeIs('admin.lmiv.*') && !request()->routeIs('admin.imports.lmiv*') ? 'active' : '' }}">
        LMIV verwalten
    </a>
    <a href="{{ route('admin.imports.lmiv') }}"
       class="nav-sub {{ request()->routeIs('admin.imports.lmiv*') ? 'active' : '' }}">
        LMIV importieren
    </a>

    {{-- ── 3. Preise ── --}}
    <div class="nav-section">Preise</div>
    <a href="{{ route('admin.customer-groups.index') }}"
       class="{{ request()->routeIs('admin.customer-groups.*') ? 'active' : '' }}">
        Kundengruppen
    </a>

    {{-- ── 4. Lager ── --}}
    <div class="nav-section">Lager</div>
    <a href="{{ route('admin.warehouses.index') }}"
       class="{{ request()->routeIs('admin.warehouses.*') ? 'active' : '' }}">
        Lagerorte
    </a>
    <a href="{{ route('admin.stock.index') }}"
       class="{{ request()->routeIs('admin.stock.*') ? 'active' : '' }}">
        Bestände
    </a>
    <a href="{{ route('admin.stock-movements.index') }}"
       class="{{ request()->routeIs('admin.stock-movements.*') ? 'active' : '' }}">
        Bewegungen
    </a>

    {{-- ── 5. Kunden ── --}}
    <div class="nav-section">Kunden</div>
    <a href="{{ route('admin.customers.index') }}"
       class="{{ request()->routeIs('admin.customers.*') ? 'active' : '' }}">
        Kunden
    </a>

    {{-- ── 6. Lieferanten ── --}}
    <div class="nav-section">Lieferanten</div>
    <a href="{{ route('admin.suppliers.index') }}"
       class="{{ request()->routeIs('admin.suppliers.*') ? 'active' : '' }}">
        Lieferanten
    </a>

    {{-- ── 7. Pfand ── --}}
    <div class="nav-section">Pfand</div>
    <a href="{{ route('admin.pfand-items.index') }}"
       class="{{ request()->routeIs('admin.pfand-items.*') ? 'active' : '' }}">
        Pfandpositionen
    </a>
    <a href="{{ route('admin.pfand-sets.index') }}"
       class="{{ request()->routeIs('admin.pfand-sets.*') ? 'active' : '' }}">
        Pfandsets
    </a>

    {{-- ── 8. Inhalte ── --}}
    <div class="nav-section">Inhalte</div>
    <a href="{{ route('admin.pages.index') }}"
       class="{{ request()->routeIs('admin.pages.*') ? 'active' : '' }}">
        CMS-Seiten
    </a>

    {{-- ── 9. Berichte ── --}}
    <div class="nav-section">Berichte</div>
    <a href="{{ route('admin.reports.index') }}"
       class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
        Berichte
    </a>

    {{-- ── 10. Integrationen ── --}}
    <div class="nav-section">Integrationen</div>
    <a href="{{ route('admin.integrations.lexoffice') }}"
       class="{{ request()->routeIs('admin.integrations.*') ? 'active' : '' }}">
        Lexoffice
    </a>

    {{-- ── 11. System ── --}}
    <div class="nav-section">System</div>
    <a href="{{ route('admin.tasks.index') }}"
       class="{{ request()->routeIs('admin.tasks.*') ? 'active' : '' }}">
        Aufgaben-Queue
    </a>
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
