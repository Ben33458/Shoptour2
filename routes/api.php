<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PosProductController;
use App\Http\Controllers\Api\PosSaleController;
use App\Http\Controllers\Api\ShopProductController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Driver\DriverBootstrapController;
use App\Http\Controllers\Driver\DriverSyncController;
use App\Http\Controllers\Driver\DriverUploadController;
use App\Http\Controllers\Payments\WebhookController;
use App\Http\Middleware\DriverAuth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Driver PWA API routes
|--------------------------------------------------------------------------
|
| All routes under /api/driver require a valid bearer token
| (validated by the DriverAuth middleware).
|
| POST /api/driver/sync       – submit offline event queue (rate: 60/min/token)
| GET  /api/driver/bootstrap  – download today's tour + stops (rate: 120/min/token)
| POST /api/driver/upload     – upload a proof-of-delivery photo (rate: 60/min/token)
|
*/

Route::prefix('driver')
    ->middleware([DriverAuth::class])
    ->group(function (): void {
        Route::get('bootstrap', DriverBootstrapController::class)
            ->middleware('throttle:driver-bootstrap');
        Route::post('sync', DriverSyncController::class)
            ->middleware('throttle:driver-api');
        Route::post('upload', DriverUploadController::class)
            ->middleware('throttle:driver-api');
    });

/*
|--------------------------------------------------------------------------
| POS API routes
|--------------------------------------------------------------------------
|
| GET  /api/pos/products  – product search + barcode lookup
| POST /api/pos/sale      – create a POS sale (order + immediate payment)
|
| No auth middleware for MVP; add token-based auth when POS terminals are
| provisioned with API credentials.
|
*/
Route::prefix('pos')
    ->middleware('throttle:pos-api')
    ->group(function (): void {
        Route::get('products', [PosProductController::class, 'index']);
        Route::post('sale',    PosSaleController::class);
    });

/*
|--------------------------------------------------------------------------
| Shop / Storefront API routes (WP-15)
|--------------------------------------------------------------------------
|
| GET  /api/products/{id}  – full product detail including active LMIV version
|
*/
Route::prefix('products')
    ->group(function (): void {
        Route::get('{id}', [ShopProductController::class, 'show']);
    });

/*
|--------------------------------------------------------------------------
| Payment webhooks (WP-17)
|--------------------------------------------------------------------------
|
| POST /api/payments/webhook/stripe
|   Receives Stripe events (no CSRF, no session — runs under API middleware).
|   Signature is validated inside StripeProvider::handleWebhook().
|
*/
Route::post('/payments/webhook/stripe', WebhookController::class)
    ->middleware('throttle:stripe-webhook')
    ->name('payments.webhook.stripe');

/*
|--------------------------------------------------------------------------
| JTL WaWi Sync
|--------------------------------------------------------------------------
|
| POST /api/sync  – receives entity batches from JTL Wawi export script.
| Authenticated via static bearer token (WAWI_SYNC_TOKEN in .env).
|
*/
Route::post('sync', SyncController::class)
    ->middleware('wawi.token')
    ->name('api.sync');

Route::get('sync/state', \App\Http\Controllers\Api\SyncStateController::class)
    ->middleware('wawi.token')
    ->name('api.sync.state');
