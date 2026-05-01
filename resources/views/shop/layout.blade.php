@php
    $darkMode = auth()->check() ? auth()->user()->dark_mode : (request()->cookie('dark_mode') === '1');
@endphp
<!DOCTYPE html>
<html lang="de" data-theme="{{ $darkMode ? 'dark' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Shop') — Kolabri Getränke</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }

        /* ── Dark mode für Shop ─────────────────────────────────── */
        [data-theme="dark"] body,
        [data-theme="dark"] { background-color: #1a2235; color: #ccd6e8; }
        [data-theme="dark"] header { background-color: #242f45 !important; border-color: #38496a !important; }
        [data-theme="dark"] nav.border-t { background-color: #1e2b40 !important; border-color: #38496a !important; }
        [data-theme="dark"] .bg-white { background-color: #242f45 !important; }
        [data-theme="dark"] .bg-gray-50 { background-color: #1a2235 !important; }
        [data-theme="dark"] .bg-amber-50 { background-color: #0e1e30 !important; }
        [data-theme="dark"] .border-gray-200 { border-color: #38496a !important; }
        [data-theme="dark"] .border-gray-100 { border-color: #2d3d58 !important; }
        [data-theme="dark"] .text-gray-800 { color: #ccd6e8 !important; }
        [data-theme="dark"] .text-gray-700 { color: #b0bfd4 !important; }
        [data-theme="dark"] .text-gray-600 { color: #8899b4 !important; }
        [data-theme="dark"] .text-gray-900 { color: #e2eaf5 !important; }
        [data-theme="dark"] .text-gray-400 { color: #637590 !important; }
        [data-theme="dark"] .divide-gray-100 > * { border-color: #38496a !important; }
        [data-theme="dark"] footer { border-color: #38496a !important; }
        /* CMS prose content */
        [data-theme="dark"] .cms-content h1,
        [data-theme="dark"] .cms-content h2,
        [data-theme="dark"] .cms-content h3 { color: #e2eaf5 !important; }
        [data-theme="dark"] .cms-content p,
        [data-theme="dark"] .cms-content li { color: #b0bfd4 !important; }
        [data-theme="dark"] .cms-content li strong,
        [data-theme="dark"] .cms-content p strong,
        [data-theme="dark"] .cms-content strong { color: #e2eaf5 !important; }
        [data-theme="dark"] .cms-content { border-color: #38496a !important; }
        [data-theme="dark"] .cms-content .section-card { background: #1e2b40 !important; border-color: #38496a !important; }
        [data-theme="dark"] .shadow-lg,
        [data-theme="dark"] .shadow-xl { box-shadow: 0 4px 20px rgba(0,0,0,.35) !important; }

        /* ── Pagination CSS vars ────────────────────────────────── */
        :root { --pag-bg:#fff; --pag-border:#e5e7eb; --pag-text:#374151; --pag-muted:#9ca3af; --pag-hover:#eff6ff; --pag-hover-border:#93c5fd; --pag-active-bg:#f59e0b; --pag-active-text:#fff; }
        [data-theme="dark"] { --pag-bg:#242f45; --pag-border:#38496a; --pag-text:#ccd6e8; --pag-muted:#4a5d7a; --pag-hover:#1e2b40; --pag-hover-border:#4a6fa5; --pag-active-bg:#d97706; --pag-active-text:#fff; }

        /* ── Product card hover ─────────────────────────────────── */
        .product-card { transition: background .15s, box-shadow .15s; }
        .product-card:hover { background-color: #eff6ff !important; box-shadow: 0 2px 10px rgba(59,130,246,.1); }
        [data-theme="dark"] .product-card:hover { background-color: #1e2b40 !important; box-shadow: 0 2px 10px rgba(0,0,0,.3); }

        /* Dark mode toggle button */
        .shop-dark-toggle {
            display: flex; align-items: center; gap: 5px;
            background: none; border: none; cursor: pointer;
            color: #6b7280; font-size: 18px; padding: 4px;
            border-radius: 6px; transition: color .2s, background .15s;
            line-height: 1;
        }
        .shop-dark-toggle:hover { background: rgba(0,0,0,.05); color: #d97706; }
        [data-theme="dark"] .shop-dark-toggle:hover { background: rgba(255,255,255,.08); }

        /* ── Confirm modal ──────────────────────────────────────── */
        #shop-confirm-backdrop {
            position: fixed; inset: 0; z-index: 9000;
            background: rgba(0,0,0,.45);
            display: flex; align-items: center; justify-content: center;
            padding: 16px;
            opacity: 0; pointer-events: none;
            transition: opacity .15s;
        }
        #shop-confirm-backdrop.active { opacity: 1; pointer-events: all; }
        #shop-confirm-box {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,.2);
            padding: 28px 28px 22px;
            max-width: 380px; width: 100%;
            transform: scale(.95);
            transition: transform .15s;
        }
        #shop-confirm-backdrop.active #shop-confirm-box { transform: scale(1); }
        #shop-confirm-box h3 { margin: 0 0 8px; font-size: 1rem; font-weight: 700; color: #111827; }
        #shop-confirm-box p  { margin: 0 0 22px; font-size: .875rem; color: #6b7280; line-height: 1.5; }
        #shop-confirm-box .btns { display: flex; gap: 10px; justify-content: flex-end; }
        #shop-confirm-cancel {
            padding: 8px 18px; border-radius: 10px; border: 1px solid #e5e7eb;
            background: #f9fafb; color: #374151; font-size: .875rem; font-weight: 500;
            cursor: pointer; transition: background .12s;
        }
        #shop-confirm-cancel:hover { background: #f3f4f6; }
        #shop-confirm-ok {
            padding: 8px 18px; border-radius: 10px; border: none;
            background: #ef4444; color: #fff; font-size: .875rem; font-weight: 600;
            cursor: pointer; transition: background .12s;
        }
        #shop-confirm-ok:hover { background: #dc2626; }
        #shop-confirm-ok.ok-amber { background: #f59e0b; }
        #shop-confirm-ok.ok-amber:hover { background: #d97706; }
        [data-theme="dark"] #shop-confirm-box { background: #242f45; box-shadow: 0 20px 60px rgba(0,0,0,.5); }
        [data-theme="dark"] #shop-confirm-box h3 { color: #e2eaf5; }
        [data-theme="dark"] #shop-confirm-box p  { color: #8899b4; }
        [data-theme="dark"] #shop-confirm-cancel { background: #1a2235; border-color: #38496a; color: #ccd6e8; }
        [data-theme="dark"] #shop-confirm-cancel:hover { background: #1e2b40; }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('head')
</head>
<body class="min-h-screen bg-gray-50 text-gray-800">

{{-- ─── Navbar ─────────────────────────────────────────────────────────────── --}}
<header class="bg-white border-b border-gray-200 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-3 sm:px-5 lg:px-7 flex items-center gap-3 h-14">

        {{-- Logo --}}
        <a href="{{ route('shop.index') }}" class="shrink-0">
            <img src="{{ asset('images/kolabri_logo.png') }}" alt="Kolabri Getränke" style="height:52px;width:auto">
        </a>

        {{-- Search --}}
        <form action="{{ route('shop.index') }}" method="GET" class="flex-1 max-w-lg hidden sm:block">
            <div class="relative">
                <input type="text" name="suche" value="{{ request('suche') }}"
                       placeholder="Produkte suchen…"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                <button type="submit" class="absolute right-3 top-2.5 text-gray-400 hover:text-amber-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
                </button>
            </div>
        </form>

        <div class="flex items-center gap-3 ml-auto">

            {{-- Cart — Alpine.js mini-cart dropdown (BUG-1 fix: count loaded via JS, not server-side) --}}
            <div x-data="miniCart()" @click.outside="open = false" class="relative">
                <button @click="toggle()" class="relative flex items-center gap-1.5 text-sm text-gray-600 hover:text-amber-600 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 9m5-9v9m4-9v9m5-9l2 9"/></svg>
                    <span x-show="count > 0" x-text="count"
                          class="absolute -top-1.5 -right-1.5 bg-amber-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center leading-none font-bold"
                          x-cloak></span>
                    <span class="hidden sm:inline">Warenkorb</span>
                </button>

                {{-- Mini-cart dropdown --}}
                <div x-show="open" x-cloak
                     class="absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden">

                    {{-- Loading spinner --}}
                    <div x-show="loading" class="p-6 text-center">
                        <svg class="w-5 h-5 mx-auto animate-spin text-amber-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z"/>
                        </svg>
                    </div>

                    {{-- Empty state --}}
                    <div x-show="!loading && count === 0" class="p-4 text-center text-sm text-gray-400">
                        <p class="mb-3">Ihr Warenkorb ist leer.</p>
                        <a href="{{ route('cart.index') }}"
                           class="inline-block bg-amber-500 text-white rounded-lg px-4 py-1.5 text-sm font-medium hover:bg-amber-600 transition-colors">
                            Zum Warenkorb
                        </a>
                    </div>

                    {{-- Items list --}}
                    <div x-show="!loading && count > 0">
                        <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                            <template x-for="(item, i) in items" :key="i">
                                <div class="px-4 py-2 flex justify-between items-center text-sm">
                                    <span class="truncate flex-1 text-gray-700" x-text="item.qty + '× ' + item.name"></span>
                                    <span class="ml-3 text-gray-900 font-medium shrink-0" x-text="item.price_display"></span>
                                </div>
                            </template>
                        </div>
                        <div class="px-4 py-2 flex justify-between items-center text-sm font-semibold border-t border-gray-200 bg-gray-50">
                            <span>Gesamt</span>
                            <span x-text="total"></span>
                        </div>
                        <div class="p-3 border-t border-gray-100">
                            <a href="{{ route('cart.index') }}"
                               class="block w-full text-center bg-amber-500 text-white rounded-lg py-2 text-sm font-medium hover:bg-amber-600 transition-colors">
                                Zum Warenkorb
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Dark mode toggle --}}
            <button class="shop-dark-toggle" onclick="toggleDarkMode()" title="Dark Mode" type="button" id="shop-dm-btn">
                {{ $darkMode ? '☀️' : '🌙' }}
            </button>

            {{-- User menu --}}
            @auth
                @php
                    $navUser = Auth::user();
                    if ($navUser->isSubUser()) {
                        $navDisplayName = $navUser->subUser?->parentCustomer?->company_name
                            ?: $navUser->name;
                    } else {
                        $navDisplayName = $navUser->customer?->company_name
                            ?: $navUser->name;
                    }
                @endphp
                <div class="relative group">
                    <button class="flex items-center gap-1.5 text-sm text-gray-600 hover:text-amber-600">
                        @if(Auth::user()->avatar_url)
                            <img src="{{ Auth::user()->avatar_url }}" class="w-7 h-7 rounded-full" alt="">
                        @else
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A8 8 0 1118.88 17.804M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        @endif
                        <span class="hidden sm:inline">{{ Str::limit($navDisplayName, 18) }}</span>
                    </button>
                    <div class="absolute right-0 mt-1 w-44 bg-white border border-gray-200 rounded-xl shadow-lg py-1 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all text-sm">
                        @if(Auth::user()->hasAdminAccess())
                            <a href="{{ route('admin.orders.index') }}" class="block px-4 py-2 hover:bg-gray-50">Admin-Bereich</a>
                            <div class="border-t border-gray-100 my-1"></div>
                        @else
                            <a href="{{ route('account') }}" class="block px-4 py-2 hover:bg-gray-50">Mein Konto</a>
                            <a href="{{ route('account.orders') }}" class="block px-4 py-2 hover:bg-gray-50">Bestellungen</a>
                            <a href="{{ route('account.addresses') }}" class="block px-4 py-2 hover:bg-gray-50">Adressen</a>
                            @if(Auth::user()->customer?->lexoffice_contact_id)
                                <a href="{{ route('account.invoices') }}" class="block px-4 py-2 hover:bg-gray-50">Rechnungen</a>
                            @endif
                            <div class="border-t border-gray-100 my-1"></div>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-red-600">Abmelden</button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-amber-600">Anmelden</a>
                <a href="{{ route('register') }}" class="text-sm bg-amber-500 text-white px-3 py-1.5 rounded-lg hover:bg-amber-600 transition-colors">Registrieren</a>
            @endauth
        </div>
    </div>

    {{-- ─── Hauptmenü-Leiste (CMS main-Seiten + Leihen) ──────────────────── --}}
    <nav class="border-t border-gray-100 bg-amber-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center gap-1 h-10 overflow-x-auto">
            @foreach($mainMenuPages as $navPage)
                <a href="{{ route('page.show', $navPage->slug) }}"
                   class="shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition-colors
                          {{ request()->is('seite/' . $navPage->slug)
                              ? 'bg-amber-500 text-white'
                              : 'text-amber-800 hover:bg-amber-100' }}">
                    {{ $navPage->title }}
                </a>
            @endforeach
            <a href="{{ route('rental.landing') }}"
               class="shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition-colors
                      {{ request()->is('leihen*')
                          ? 'bg-amber-500 text-white'
                          : 'text-amber-800 hover:bg-amber-100' }}">
                Leihen
            </a>
        </div>
    </nav>
</header>

{{-- ─── Flash messages ──────────────────────────────────────────────────────── --}}
@if(session('success'))
    <div class="bg-green-50 border-b border-green-200 text-green-700 text-sm text-center py-2">{{ session('success') }}</div>
@endif
@if(session('info'))
    <div class="bg-blue-50 border-b border-blue-200 text-blue-700 text-sm text-center py-2">{{ session('info') }}</div>
@endif
@if($errors->any())
    <div class="bg-red-50 border-b border-red-200 text-red-700 text-sm text-center py-2">{{ $errors->first() }}</div>
@endif

{{-- ─── Onboarding resume hint ─────────────────────────────────────────────── --}}
@auth
    @if(Auth::user()->isKunde() && ! request()->query('onboarding_step'))
        @php
            $__ob_customer = Auth::user()->customer ?? null;
            $__ob_show     = $__ob_customer
                && ! ($__ob_customer->display_preferences['onboarding_completed'] ?? false);
        @endphp
        @if($__ob_show)
            @php
                $__ob_steps       = \App\Services\CustomerActivationService::tourSteps();
                $__ob_savedKey    = $__ob_customer->display_preferences['onboarding_current_step'] ?? null;
                $__ob_resumeStep  = collect($__ob_steps)->firstWhere('key', $__ob_savedKey) ?? $__ob_steps[0];
                $__ob_resumeUrl   = route($__ob_resumeStep['route'], $__ob_resumeStep['params']);
            @endphp
            <div class="bg-amber-500 px-4 py-3 text-center text-white flex items-center justify-center gap-3">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
                <span class="text-sm font-medium">Konto-Einrichtung noch nicht abgeschlossen.</span>
                <a href="{{ $__ob_resumeUrl }}"
                   class="bg-white text-amber-700 font-semibold text-sm px-4 py-1.5 rounded-lg hover:bg-amber-50 transition-colors whitespace-nowrap">
                    Jetzt fortsetzen →
                </a>
            </div>
        @endif
    @endif
@endauth

{{-- ─── Sub-user context banner ────────────────────────────────────────────── --}}
@auth
    @if(Auth::user()->isSubUser())
        @php
            $subUser    = Auth::user()->subUser;
            $parent     = $subUser?->parentCustomer;
            $parentName = $parent?->company_name ?: trim(($parent?->first_name ?? '') . ' ' . ($parent?->last_name ?? ''));
        @endphp
        <div class="bg-amber-50 border-b border-amber-200 px-4 py-2 text-center text-xs text-amber-800">
            Sie handeln im Namen von <strong>{{ $parentName }}</strong> als {{ Auth::user()->name }}
        </div>
    @endif
@endauth

{{-- ─── Page content ───────────────────────────────────────────────────────── --}}
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @yield('content')
</main>

{{-- ─── Footer ─────────────────────────────────────────────────────────────── --}}
<footer class="border-t border-gray-200 mt-16 py-8 text-center text-sm text-gray-400">
    &copy; {{ date('Y') }} Kolabri Getränke
    @if($footerPages->isNotEmpty())
        &mdash;
        @foreach($footerPages as $footerPage)
            <a href="{{ route('page.show', $footerPage->slug) }}"
               class="hover:text-gray-600 underline">{{ $footerPage->title }}</a>@if(!$loop->last) &middot; @endif
        @endforeach
    @endif
</footer>

@stack('scripts')

{{-- Dark mode toggle --}}
<script>
function toggleDarkMode() {
    const html = document.documentElement;
    const next = html.dataset.theme !== 'dark';
    html.dataset.theme = next ? 'dark' : '';
    const btn = document.getElementById('shop-dm-btn');
    if (btn) btn.textContent = next ? '☀️' : '🌙';
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

{{-- Mini-cart Alpine.js component (PROJ-3 / BUG-2) --}}
<script>
function miniCart() {
    return {
        open: false,
        count: 0,
        items: [],
        total: '',
        loading: false,

        init() {
            this.refreshCount();
        },

        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.fetchCart();
            }
        },

        async refreshCount() {
            try {
                const res = await fetch('{{ route('cart.mini') }}', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.count = data.count;
            } catch (e) {}
        },

        async fetchCart() {
            this.loading = true;
            try {
                const res = await fetch('{{ route('cart.mini') }}', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.count = data.count;
                this.items = data.items;
                this.total = data.total_display;
            } catch (e) {}
            this.loading = false;
        },
    };
}
</script>

{{-- ── Confirm Modal (replaces browser confirm()) ──────────────────────── --}}
<div id="shop-confirm-backdrop" role="dialog" aria-modal="true" aria-labelledby="shop-confirm-title">
    <div id="shop-confirm-box">
        <h3 id="shop-confirm-title"></h3>
        <p id="shop-confirm-body"></p>
        <div class="btns">
            <button id="shop-confirm-cancel" type="button">Abbrechen</button>
            <button id="shop-confirm-ok"     type="button">Bestätigen</button>
        </div>
    </div>
</div>
<script>
(function () {
    var backdrop = document.getElementById('shop-confirm-backdrop');
    var title    = document.getElementById('shop-confirm-title');
    var body     = document.getElementById('shop-confirm-body');
    var btnOk    = document.getElementById('shop-confirm-ok');
    var btnCancel= document.getElementById('shop-confirm-cancel');
    var _resolve = null;

    function close() {
        backdrop.classList.remove('active');
        document.removeEventListener('keydown', onKey);
    }
    function onKey(e) {
        if (e.key === 'Escape') { close(); if (_resolve) _resolve(false); }
        if (e.key === 'Enter')  { close(); if (_resolve) _resolve(true);  }
    }

    btnCancel.addEventListener('click', function () { close(); if (_resolve) _resolve(false); });
    btnOk.addEventListener('click',     function () { close(); if (_resolve) _resolve(true);  });
    backdrop.addEventListener('click',  function (e) { if (e.target === backdrop) { close(); if (_resolve) _resolve(false); } });

    /**
     * shopConfirm(heading, message, okLabel, okVariant)
     *   okVariant: 'danger' (red, default) | 'amber'
     * Returns a Promise<boolean>.
     */
    window.shopConfirm = function (heading, message, okLabel, okVariant) {
        title.textContent  = heading  || 'Bist du sicher?';
        body.textContent   = message  || '';
        btnOk.textContent  = okLabel  || 'Bestätigen';
        btnOk.className    = (okVariant === 'amber') ? 'ok-amber' : '';
        backdrop.classList.add('active');
        btnOk.focus();
        document.addEventListener('keydown', onKey);
        return new Promise(function (resolve) { _resolve = resolve; });
    };
})();
</script>
</body>
</html>
