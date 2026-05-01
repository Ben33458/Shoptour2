{{--
    Kontextuelle Sekundär-Navigation (Section Sub-Nav)
    ────────────────────────────────────────────────────
    Wird in admin/layout.blade.php zwischen Topbar und Content eingebunden.
    Zeigt je nach aktivem Hauptbereich die passenden Tabs.
    Kein eigener State — alles per request()->routeIs() ermittelt.
--}}

{{-- ── Verkauf ────────────────────────────────────────────────── --}}
@if(request()->routeIs('admin.orders.*', 'admin.invoices.*', 'admin.cash-registers.*', 'admin.rental.delivery-returns.*'))
<nav class="section-nav">
    <a href="{{ route('admin.orders.index') }}"
       class="{{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">Bestellungen</a>
    <a href="{{ route('admin.invoices.index') }}"
       class="{{ request()->routeIs('admin.invoices.*') ? 'active' : '' }}">Rechnungen</a>
    <a href="{{ route('admin.cash-registers.index') }}"
       class="{{ request()->routeIs('admin.cash-registers.*') ? 'active' : '' }}">Kasse</a>
    <a href="{{ route('admin.rental.delivery-returns.index') }}"
       class="{{ request()->routeIs('admin.rental.delivery-returns.*') ? 'active' : '' }}">Vollgut-Rücknahmen</a>
</nav>

{{-- ── Verleih & Events ─────────────────────────────────────────── --}}
@elseif(request()->routeIs('admin.rental.*', 'admin.event.locations.*', 'admin.assets.issues.*', 'admin.vehicles.*'))
<nav class="section-nav">
    <div class="snav-group">
        <span class="snav-label">Artikelstamm</span>
        <a href="{{ route('admin.rental.items.index') }}"
           class="{{ request()->routeIs('admin.rental.items.*') ? 'active' : '' }}">Leihartikel</a>
        <a href="{{ route('admin.rental.categories.index') }}"
           class="{{ request()->routeIs('admin.rental.categories.*') ? 'active' : '' }}">Kategorien</a>
        <a href="{{ route('admin.rental.packaging-units.index') }}"
           class="{{ request()->routeIs('admin.rental.packaging-units.*') ? 'active' : '' }}">VPE</a>
    </div>
    <div class="snav-group">
        <span class="snav-label">Inventar</span>
        <a href="{{ route('admin.rental.inventory.index') }}"
           class="{{ request()->routeIs('admin.rental.inventory.*') ? 'active' : '' }}">Bestandsverwaltung</a>
        <a href="{{ route('admin.rental.inventory-units.index') }}"
           class="{{ request()->routeIs('admin.rental.inventory-units.*') ? 'active' : '' }}">Inventareinheiten</a>
        <a href="{{ route('admin.assets.issues.index') }}"
           class="{{ request()->routeIs('admin.assets.issues.*') ? 'active' : '' }}">Schadensmeldungen</a>
    </div>
    <div class="snav-group">
        <span class="snav-label">Vorgänge</span>
        <a href="{{ route('admin.rental.return-slips.index') }}"
           class="{{ request()->routeIs('admin.rental.return-slips.*') ? 'active' : '' }}">Rückgabescheine</a>
    </div>
    <div class="snav-group">
        <span class="snav-label">Regeln</span>
        <a href="{{ route('admin.rental.time-models.index') }}"
           class="{{ request()->routeIs('admin.rental.time-models.*') ? 'active' : '' }}">Zeitmodelle</a>
        <a href="{{ route('admin.rental.price-rules.index') }}"
           class="{{ request()->routeIs('admin.rental.price-rules.*') ? 'active' : '' }}">Preisregeln</a>
        <a href="{{ route('admin.rental.damage-tariffs.index') }}"
           class="{{ request()->routeIs('admin.rental.damage-tariffs.*') ? 'active' : '' }}">Schadenstarifte</a>
        <a href="{{ route('admin.rental.cleaning-fee-rules.index') }}"
           class="{{ request()->routeIs('admin.rental.cleaning-fee-rules.*') ? 'active' : '' }}">Reinigungspauschalen</a>
        <a href="{{ route('admin.rental.deposit-rules.index') }}"
           class="{{ request()->routeIs('admin.rental.deposit-rules.*') ? 'active' : '' }}">Pfandregeln</a>
    </div>
    <div class="snav-group">
        <span class="snav-label">Fuhrpark</span>
        <a href="{{ route('admin.vehicles.index') }}"
           class="{{ request()->routeIs('admin.vehicles.*') ? 'active' : '' }}">Fahrzeuge</a>
        <a href="{{ route('admin.event.locations.index') }}"
           class="{{ request()->routeIs('admin.event.locations.*') ? 'active' : '' }}">Event-Locations</a>
    </div>
</nav>

{{-- ── Katalog ─────────────────────────────────────────────────── --}}
@elseif(request()->routeIs('admin.products.*', 'admin.categories.*', 'admin.customer-groups.*', 'admin.catalog.overview'))
<nav class="section-nav">
    <a href="{{ route('admin.products.index') }}"
       class="{{ request()->routeIs('admin.products.index', 'admin.products.create', 'admin.products.show', 'admin.products.edit') ? 'active' : '' }}">Produkte</a>
    <a href="{{ route('admin.products.bulk-alkohol') }}"
       class="{{ request()->routeIs('admin.products.bulk-alkohol*') ? 'active' : '' }}">Alkohol-Daten</a>
    <a href="{{ route('admin.catalog.overview') }}"
       class="{{ request()->routeIs('admin.catalog.overview') ? 'active' : '' }}">Gesamtübersicht</a>
    <a href="{{ route('admin.categories.index') }}"
       class="{{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">Kategorien</a>
    <a href="{{ route('admin.customer-groups.index') }}"
       class="{{ request()->routeIs('admin.customer-groups.*') ? 'active' : '' }}">Kundengruppen</a>
</nav>

{{-- ── Lager & Einkauf ─────────────────────────────────────────── --}}
@elseif(request()->routeIs('admin.stock.*', 'admin.stock-movements.*', 'admin.warehouses.*', 'admin.suppliers.*', 'admin.einkauf.*'))
<nav class="section-nav">
    <a href="{{ route('admin.stock.index') }}"
       class="{{ request()->routeIs('admin.stock.*') ? 'active' : '' }}">Bestände</a>
    <a href="{{ route('admin.stock-movements.index') }}"
       class="{{ request()->routeIs('admin.stock-movements.*') ? 'active' : '' }}">Bewegungen</a>
    <a href="{{ route('admin.warehouses.index') }}"
       class="{{ request()->routeIs('admin.warehouses.*') ? 'active' : '' }}">Lagerorte</a>
    <a href="{{ route('admin.suppliers.index') }}"
       class="{{ request()->routeIs('admin.suppliers.*') ? 'active' : '' }}">Lieferanten</a>
    <a href="{{ route('admin.einkauf.index') }}"
       class="{{ request()->routeIs('admin.einkauf.*') ? 'active' : '' }}">Einkauf</a>
</nav>

{{-- ── Kommunikation ───────────────────────────────────────────── --}}
@elseif(request()->routeIs('admin.communications.*'))
<nav class="section-nav">
    <a href="{{ route('admin.communications.index') }}"
       class="{{ request()->routeIs('admin.communications.index') || request()->routeIs('admin.communications.show') ? 'active' : '' }}">
        Posteingang
        @php $reviewCount = \App\Models\Communications\Communication::where('status', 'review')->count(); @endphp
        @if($reviewCount > 0)
            <span class="snav-badge">{{ $reviewCount }}</span>
        @endif
    </a>
    <a href="{{ route('admin.communications.rules.index') }}"
       class="{{ request()->routeIs('admin.communications.rules*') ? 'active' : '' }}">Regeln</a>
    <a href="{{ route('admin.communications.settings') }}"
       class="{{ request()->routeIs('admin.communications.settings*') ? 'active' : '' }}">Einstellungen</a>
</nav>

{{-- ── Personal ────────────────────────────────────────────────── --}}
@elseif(request()->routeIs('admin.employees.*', 'admin.shifts.*', 'admin.time.*', 'admin.vacation.*', 'admin.onboarding.*', 'admin.emp-tasks.*', 'admin.recurring-tasks.*', 'employee.*'))
<nav class="section-nav">
    <a href="{{ route('admin.employees.index') }}"
       class="{{ request()->routeIs('admin.employees.*') ? 'active' : '' }}">Mitarbeiter</a>
    <a href="{{ route('admin.shifts.index') }}"
       class="{{ request()->routeIs('admin.shifts.*') ? 'active' : '' }}">Schichten</a>
    <a href="{{ route('admin.time.index') }}"
       class="{{ request()->routeIs('admin.time.*') ? 'active' : '' }}">Zeiterfassung</a>
    <a href="{{ route('admin.vacation.index') }}"
       class="{{ request()->routeIs('admin.vacation.*') ? 'active' : '' }}">Urlaub</a>
    <a href="{{ route('admin.emp-tasks.index') }}"
       class="{{ request()->routeIs('admin.emp-tasks.*', 'admin.recurring-tasks.*') ? 'active' : '' }}">Aufgaben</a>
    <a href="{{ route('admin.onboarding.index') }}"
       class="{{ request()->routeIs('admin.onboarding.*') ? 'active' : '' }}">
        Onboarding
        @php $pendingOnboarding = \App\Models\Employee\Employee::where('onboarding_status','pending_review')->count(); @endphp
        @if($pendingOnboarding > 0)
            <span class="snav-badge">{{ $pendingOnboarding }}</span>
        @endif
    </a>
    <a href="{{ route('employee.tasks.index') }}"
       class="{{ request()->routeIs('employee.*') ? 'active' : '' }}"
       style="margin-left:auto;font-size:.75rem;color:var(--c-muted)">→ Mitarbeiteransicht</a>
</nav>

{{-- ── Import & Synchronisation ───────────────────────────────── --}}
@elseif(request()->routeIs('admin.reconcile.*', 'admin.integrations.*', 'admin.ninox-import.*', 'admin.imports.*'))
<nav class="section-nav">
    <div class="snav-group">
        <span class="snav-label">Quellen</span>
        <a href="{{ route('admin.integrations.lexoffice') }}"
           class="{{ request()->routeIs('admin.integrations.*') ? 'active' : '' }}">Lexoffice</a>
        <a href="{{ route('admin.ninox-import.index') }}"
           class="{{ request()->routeIs('admin.ninox-import.*') ? 'active' : '' }}">Ninox</a>
    </div>
    <div class="snav-group">
        <span class="snav-label">Abgleich</span>
        <a href="{{ route('admin.reconcile.hub') }}"
           class="{{ request()->routeIs('admin.reconcile.hub') ? 'active' : '' }}">Übersicht</a>
        <a href="{{ route('admin.reconcile.customers') }}"
           class="{{ request()->routeIs('admin.reconcile.customers*') ? 'active' : '' }}">Kunden</a>
        <a href="{{ route('admin.reconcile.products') }}"
           class="{{ request()->routeIs('admin.reconcile.products*') ? 'active' : '' }}">Produkte</a>
        <a href="{{ route('admin.reconcile.suppliers') }}"
           class="{{ request()->routeIs('admin.reconcile.suppliers*') ? 'active' : '' }}">Lieferanten</a>
        <a href="{{ route('admin.reconcile.employees') }}"
           class="{{ request()->routeIs('admin.reconcile.employees*') ? 'active' : '' }}">Mitarbeiter</a>
        <a href="{{ route('admin.reconcile.getraenkedb.index') }}"
           class="{{ request()->routeIs('admin.reconcile.getraenkedb.*') ? 'active' : '' }}">GetraenkeDB</a>
    </div>
    <div class="snav-group">
        <span class="snav-label">CSV-Import</span>
        <a href="{{ route('admin.imports.customers') }}"
           class="{{ request()->routeIs('admin.imports.customers*') ? 'active' : '' }}">Kunden</a>
        <a href="{{ route('admin.imports.products') }}"
           class="{{ request()->routeIs('admin.imports.products*') ? 'active' : '' }}">Produkte</a>
        <a href="{{ route('admin.imports.suppliers') }}"
           class="{{ request()->routeIs('admin.imports.suppliers*') ? 'active' : '' }}">Lieferanten</a>
    </div>
</nav>

{{-- ── Administration ──────────────────────────────────────────── --}}
@elseif(request()->routeIs('admin.users.*', 'admin.driver-tokens.*', 'admin.diagnostics', 'admin.deploy.*', 'admin.audit-logs.*', 'admin.tasks.*', 'admin.settings.shop_display.*'))
<nav class="section-nav">
    <a href="{{ route('admin.users.index') }}"
       class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Benutzer &amp; Rollen</a>
    <a href="{{ route('admin.driver-tokens.index') }}"
       class="{{ request()->routeIs('admin.driver-tokens.*') ? 'active' : '' }}">Fahrer-Token</a>
    <a href="{{ route('admin.audit-logs.index') }}"
       class="{{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">Audit-Log</a>
    <a href="{{ route('admin.tasks.index') }}"
       class="{{ request()->routeIs('admin.tasks.*') ? 'active' : '' }}">Aufgaben-Queue</a>
    <a href="{{ route('admin.diagnostics') }}"
       class="{{ request()->routeIs('admin.diagnostics') ? 'active' : '' }}">Diagnose</a>
    <a href="{{ route('admin.deploy.index') }}"
       class="{{ request()->routeIs('admin.deploy.*') ? 'active' : '' }}">Deployment</a>
    <a href="{{ route('admin.settings.shop_display.edit') }}"
       class="{{ request()->routeIs('admin.settings.shop_display.*') ? 'active' : '' }}">Shop-Ansicht</a>
</nav>

{{-- ── Finanzen / Mahnwesen ─────────────────────────────────────── --}}
@elseif(request()->routeIs('admin.debtor.*', 'admin.dunning.*', 'admin.settings.dunning.*'))
<nav class="section-nav">
    <a href="{{ route('admin.debtor.index') }}"
       class="{{ request()->routeIs('admin.debtor.*') ? 'active' : '' }}">Offene Posten</a>
    <a href="{{ route('admin.dunning.index') }}"
       class="{{ request()->routeIs('admin.dunning.*') ? 'active' : '' }}">Mahnläufe</a>
    <a href="{{ route('admin.settings.dunning.edit') }}"
       class="{{ request()->routeIs('admin.settings.dunning.*') ? 'active' : '' }}">Einstellungen</a>
</nav>

{{-- ── Mehr ────────────────────────────────────────────────────── --}}
@elseif(request()->routeIs('admin.pages.*', 'admin.features'))
<nav class="section-nav">
    <a href="{{ route('admin.pages.index') }}"
       class="{{ request()->routeIs('admin.pages.*') ? 'active' : '' }}">CMS-Seiten</a>
    <a href="{{ route('admin.features') }}"
       class="{{ request()->routeIs('admin.features') ? 'active' : '' }}">Hilfe &amp; Features</a>
</nav>
@endif
