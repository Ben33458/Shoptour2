<?php

namespace App\Providers;

use App\Models\Page;
use App\Services\Pricing\EloquentPricingRepository;
use App\Services\Pricing\PricingRepositoryInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the pricing repository interface to its Eloquent implementation.
        // Used by PriceResolverService and PosProductController.
        $this->app->bind(PricingRepositoryInterface::class, EloquentPricingRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use the framework's built-in simple pagination (no Tailwind/Bootstrap needed)
        Paginator::defaultView('pagination::default');
        Paginator::defaultSimpleView('pagination::simple-default');

        $this->shareNavigationPages();
        $this->configureRateLimiting();
    }

    // =========================================================================
    // Navigation pages (CMS — PROJ-30)
    // =========================================================================

    private function shareNavigationPages(): void
    {
        // Lazy-share to shop.layout only — admin pages don't need this.
        // Cached for 60 s so every page load doesn't hit the DB.
        View::composer('shop.layout', function (\Illuminate\View\View $view): void {
            $pages = Cache::remember('cms_nav_pages', 60, fn () =>
                Page::where('active', true)
                    ->whereIn('menu', ['main', 'footer'])
                    ->orderBy('sort_order')
                    ->orderBy('title')
                    ->get(['id', 'slug', 'title', 'menu'])
            );

            $view->with([
                'mainMenuPages' => $pages->where('menu', 'main')->values(),
                'footerPages'   => $pages->where('menu', 'footer')->values(),
            ]);
        });
    }

    // =========================================================================
    // Rate limiting
    // =========================================================================

    private function configureRateLimiting(): void
    {
        // ── Driver API: sync + upload ─────────────────────────────────────────
        // 60 requests per minute per driver token.
        // Falls back to IP when token_id is not yet set in request attributes.
        RateLimiter::for('driver-api', function (Request $request) {
            $tokenId = $request->attributes->get('driver_token_id', $request->ip());
            return Limit::perMinute(60)->by('driver:' . $tokenId)
                ->response(fn () => response()->json(
                    ['error' => 'Too many requests. Please wait before retrying.'],
                    429
                ));
        });

        // ── Driver bootstrap (read-heavy, more generous) ──────────────────────
        RateLimiter::for('driver-bootstrap', function (Request $request) {
            $tokenId = $request->attributes->get('driver_token_id', $request->ip());
            return Limit::perMinute(120)->by('driver-bootstrap:' . $tokenId)
                ->response(fn () => response()->json(
                    ['error' => 'Too many requests.'],
                    429
                ));
        });

        // ── OAuth endpoints (PROJ-1) ─────────────────────────────────────────────
        // 10 requests per minute per IP — prevents OAuth abuse.
        RateLimiter::for('oauth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // ── Login endpoint (PROJ-1) ─────────────────────────────────────────────
        // 5 attempts per minute per IP — prevents brute-force on /anmelden.
        // Additionally limit per email to prevent credential stuffing.
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(5)->by('email:' . mb_strtolower((string) $request->input('email', ''))),
            ];
        });

        // ── POS API ───────────────────────────────────────────────────────────
        // 120 requests per minute per IP (POS terminals are trusted internal devices)
        RateLimiter::for('pos-api', function (Request $request) {
            return Limit::perMinute(120)->by('pos:' . $request->ip())
                ->response(fn () => response()->json(
                    ['error' => 'Rate limit exceeded.'],
                    429
                ));
        });

        // ── Cart mutations (PROJ-3 / BUG-7) ──────────────────────────────────
        // 60 requests per minute per user (auth) or IP (guest).
        // Prevents automated cart flooding.
        RateLimiter::for('cart', function (Request $request) {
            $key = $request->user()?->id
                ? 'cart:user:' . $request->user()->id
                : 'cart:ip:' . $request->ip();
            return Limit::perMinute(60)->by($key);
        });

        // ── Checkout POST (PROJ-4 / BUG-17) ──────────────────────────────────
        // 10 orders per minute per authenticated user.
        // Prevents automated rapid-fire order creation.
        RateLimiter::for('checkout', function (Request $request) {
            $userId = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(10)->by('checkout:' . $userId);
        });

        // ── Stripe webhook (WP-17) ────────────────────────────────────────────
        // 60 requests per minute per IP — Stripe sends webhooks from known IPs
        // so this provides a reasonable guard against abuse without blocking retries.
        RateLimiter::for('stripe-webhook', function (Request $request) {
            return Limit::perMinute(60)->by('stripe-webhook:' . $request->ip());
        });

        // ── Customer account activation (PROJ-12-onboarding) ─────────────────
        // 5 attempts per minute per IP to prevent mass email enumeration.
        // Per-email and per-IP hard caps (10/hour) are enforced in the controller.
        RateLimiter::for('activation', function (Request $request) {
            return Limit::perMinute(5)->by('activation:' . $request->ip());
        });
    }
}
