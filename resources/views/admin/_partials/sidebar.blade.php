{{--
    Admin Sidebar Navigation — NEUE STRUKTUR (2026-04)
    ────────────────────────────────────────────────────
    Nur noch 11 Hauptbereiche. Sub-Navigation läuft über section-nav.blade.php.
    Active-State per request()->routeIs().
--}}
<nav class="nav">
    <div class="nav-logo">
        <img src="{{ asset('images/kolabri_logo.png') }}" alt="Kolabri Getränke" style="height:72px;width:auto;display:block">
    </div>

    {{-- ── Dashboard ── --}}
    <a href="{{ route('admin.dashboard') }}"
       class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
       style="font-weight:600;">
        Dashboard
    </a>

    {{-- ── 1. Verkauf ── --}}
    <a href="{{ route('admin.orders.index') }}"
       class="{{ request()->routeIs('admin.orders.*', 'admin.invoices.*', 'admin.cash-registers.*', 'admin.rental.delivery-returns.*') ? 'active' : '' }}">
        Verkauf
    </a>

    {{-- ── 1a. Finanzen / Mahnwesen ── --}}
    <a href="{{ route('admin.debtor.index') }}"
       class="{{ request()->routeIs('admin.debtor.*', 'admin.dunning.*', 'admin.settings.dunning.*') ? 'active' : '' }}">
        Finanzen
        @php
            $openDebtorCount = \App\Models\Pricing\Customer::where('debt_hold', false)
                ->whereHas('openVouchers', fn($q) => $q->where('is_dunning_blocked', false))
                ->count();
        @endphp
        @if($openDebtorCount > 0)
            <span style="background:#dc2626;color:#fff;border-radius:10px;padding:1px 7px;font-size:.7rem;margin-left:auto;">{{ $openDebtorCount }}</span>
        @endif
    </a>

    {{-- ── 1b. Statistik ── --}}
    <a href="{{ route('admin.statistics.pos_top') }}"
       class="{{ request()->routeIs('admin.statistics.*') ? 'active' : '' }}">
        Statistik
    </a>
    @if(request()->routeIs('admin.statistics.*'))
    <div style="padding-left:16px;margin-top:-4px;display:flex;flex-direction:column;gap:2px">
        <a href="{{ route('admin.statistics.pos_top') }}"
           style="font-size:.82em;padding:2px 8px;border-radius:4px;{{ request()->routeIs('admin.statistics.pos_top') ? 'font-weight:600;color:var(--c-primary)' : 'color:var(--c-muted)' }}">
            Top-Artikel
        </a>
        <a href="{{ route('admin.statistics.purchase_planning') }}"
           style="font-size:.82em;padding:2px 8px;border-radius:4px;{{ request()->routeIs('admin.statistics.purchase_planning') ? 'font-weight:600;color:var(--c-primary)' : 'color:var(--c-muted)' }}">
            Einkaufsplanung
        </a>
        <a href="{{ route('admin.statistics.warengruppen') }}"
           style="font-size:.82em;padding:2px 8px;border-radius:4px;{{ request()->routeIs('admin.statistics.warengruppen') ? 'font-weight:600;color:var(--c-primary)' : 'color:var(--c-muted)' }}">
            Warengruppen
        </a>
        <a href="{{ route('admin.statistics.pfand') }}"
           style="font-size:.82em;padding:2px 8px;border-radius:4px;{{ request()->routeIs('admin.statistics.pfand') ? 'font-weight:600;color:var(--c-primary)' : 'color:var(--c-muted)' }}">
            Pfand
        </a>
        <a href="{{ route('admin.statistics.mhd_abschreibungen') }}"
           style="font-size:.82em;padding:2px 8px;border-radius:4px;{{ request()->routeIs('admin.statistics.mhd_abschreibungen') ? 'font-weight:600;color:var(--c-primary)' : 'color:var(--c-muted)' }}">
            MHD-Abschreibungen
        </a>
    </div>
    @endif

    {{-- ── 2. Verleih & Events ── --}}
    <a href="{{ route('admin.rental.items.index') }}"
       class="{{ request()->routeIs('admin.rental.*', 'admin.event.locations.*', 'admin.assets.issues.*', 'admin.vehicles.*') ? 'active' : '' }}">
        Verleih &amp; Events
    </a>

    {{-- ── 3. Katalog ── --}}
    <a href="{{ route('admin.products.index') }}"
       class="{{ request()->routeIs('admin.products.*', 'admin.categories.*', 'admin.customer-groups.*') ? 'active' : '' }}">
        Katalog
    </a>

    {{-- ── 4. Lager & Einkauf ── --}}
    <a href="{{ route('admin.stock.index') }}"
       class="{{ request()->routeIs('admin.stock.*', 'admin.stock-movements.*', 'admin.warehouses.*', 'admin.suppliers.*', 'admin.einkauf.*') ? 'active' : '' }}">
        Lager &amp; Einkauf
    </a>

    {{-- ── 5. Kunden ── --}}
    <a href="{{ route('admin.customers.index') }}"
       class="{{ request()->routeIs('admin.customers.*') ? 'active' : '' }}">
        Kunden
    </a>

    {{-- ── 6. Kommunikation ── --}}
    <a href="{{ route('admin.communications.index') }}"
       class="{{ request()->routeIs('admin.communications.*') ? 'active' : '' }}">
        Kommunikation
        @php $reviewCount = \App\Models\Communications\Communication::where('status', 'review')->count(); @endphp
        @if($reviewCount > 0)
            <span style="background:#f59e0b;color:#fff;border-radius:10px;padding:1px 7px;font-size:.7rem;margin-left:auto;">{{ $reviewCount }}</span>
        @endif
    </a>

    {{-- ── 7. Personal ── --}}
    <a href="{{ route('admin.employees.index') }}"
       class="{{ request()->routeIs('admin.employees.*', 'admin.shifts.*', 'admin.time.*', 'admin.vacation.*', 'admin.onboarding.*', 'admin.emp-tasks.*', 'admin.recurring-tasks.*', 'employee.*') ? 'active' : '' }}">
        Personal
        @php $pendingOnboarding = \App\Models\Employee\Employee::where('onboarding_status','pending_review')->count(); @endphp
        @if($pendingOnboarding > 0)
            <span style="background:#f59e0b;color:#fff;border-radius:10px;padding:1px 7px;font-size:.7rem;margin-left:auto;">{{ $pendingOnboarding }}</span>
        @endif
    </a>

    {{-- ── 8. Import & Synchronisation ── --}}
    <a href="{{ route('admin.reconcile.hub') }}"
       class="{{ request()->routeIs('admin.reconcile.*', 'admin.integrations.*', 'admin.ninox-import.*', 'admin.imports.*') ? 'active' : '' }}">
        Import &amp; Sync
    </a>

    {{-- ── Primeur-Archiv (IT-Drink Altdaten) ── --}}
    <a href="{{ route('admin.primeur.dashboard') }}"
       class="{{ request()->routeIs('admin.primeur.*') ? 'active' : '' }}"
       title="IT-Drink Archiv 2015–2024">
        Primeur-Archiv
    </a>

    {{-- ── 9. Administration ── --}}
    <a href="{{ route('admin.users.index') }}"
       class="{{ request()->routeIs('admin.users.*', 'admin.driver-tokens.*', 'admin.diagnostics', 'admin.deploy.*', 'admin.audit-logs.*', 'admin.tasks.*', 'admin.settings.shop_display.*') ? 'active' : '' }}">
        Administration
    </a>

    {{-- ── 10. Mehr ── --}}
    <a href="{{ route('admin.pages.index') }}"
       class="{{ request()->routeIs('admin.pages.*', 'admin.features') ? 'active' : '' }}">
        Mehr
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
