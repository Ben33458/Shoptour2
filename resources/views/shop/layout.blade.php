<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Shop') — Kolabri Getränke</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('head')
</head>
<body class="min-h-screen bg-gray-50 text-gray-800">

{{-- ─── Navbar ─────────────────────────────────────────────────────────────── --}}
<header class="bg-white border-b border-gray-200 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center gap-4 h-16">

        {{-- Logo --}}
        <a href="{{ route('shop.index') }}" class="text-xl font-bold text-amber-600 shrink-0">
            🍺 Kolabri
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
                    <div x-show="!loading && count === 0" class="p-6 text-center text-sm text-gray-400">
                        Ihr Warenkorb ist leer.
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

            {{-- User menu --}}
            @auth
                <div class="relative group">
                    <button class="flex items-center gap-1.5 text-sm text-gray-600 hover:text-amber-600">
                        @if(Auth::user()->avatar_url)
                            <img src="{{ Auth::user()->avatar_url }}" class="w-7 h-7 rounded-full" alt="">
                        @else
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A8 8 0 1118.88 17.804M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        @endif
                        <span class="hidden sm:inline">{{ Str::limit(Auth::user()->name, 15) }}</span>
                    </button>
                    <div class="absolute right-0 mt-1 w-44 bg-white border border-gray-200 rounded-xl shadow-lg py-1 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all text-sm">
                        @if(Auth::user()->hasAdminAccess())
                            <a href="{{ route('admin.orders.index') }}" class="block px-4 py-2 hover:bg-gray-50">Admin-Bereich</a>
                            <div class="border-t border-gray-100 my-1"></div>
                        @else
                            <a href="{{ route('account') }}" class="block px-4 py-2 hover:bg-gray-50">Mein Konto</a>
                            <a href="{{ route('account.orders') }}" class="block px-4 py-2 hover:bg-gray-50">Bestellungen</a>
                            <a href="{{ route('account.addresses') }}" class="block px-4 py-2 hover:bg-gray-50">Adressen</a>
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

{{-- ─── Page content ───────────────────────────────────────────────────────── --}}
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @yield('content')
</main>

{{-- ─── Footer ─────────────────────────────────────────────────────────────── --}}
<footer class="border-t border-gray-200 mt-16 py-8 text-center text-sm text-gray-400">
    &copy; {{ date('Y') }} Kolabri Getränke &mdash;
    <a href="{{ route('page.show', 'impressum') }}" class="hover:text-gray-600 underline">Impressum</a> &middot;
    <a href="{{ route('page.show', 'datenschutz') }}" class="hover:text-gray-600 underline">Datenschutz</a> &middot;
    <a href="{{ route('page.show', 'agb') }}" class="hover:text-gray-600 underline">AGB</a> &middot;
    <a href="{{ route('page.show', 'widerruf') }}" class="hover:text-gray-600 underline">Widerruf</a>
</footer>

@stack('scripts')

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
</body>
</html>
