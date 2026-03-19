<?php

use App\Http\Controllers\Admin\AdminPageController;
use App\Http\Controllers\Admin\AdminBrandImportController;
use App\Http\Controllers\Admin\AdminBrandController;
use App\Http\Controllers\Admin\AdminCustomerGroupImportController;
use App\Http\Controllers\Admin\AdminSupplierImportController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminCloseoutController;
use App\Http\Controllers\Admin\AdminCustomerController;
use App\Http\Controllers\Admin\AdminCustomerGroupController;
use App\Http\Controllers\Admin\AdminCustomerImportController;
use App\Http\Controllers\Admin\AdminGebindeController;
use App\Http\Controllers\Admin\AdminPfandItemController;
use App\Http\Controllers\Admin\AdminPfandSetController;
use App\Http\Controllers\Admin\AdminProductLineController;
use App\Http\Controllers\Admin\AdminSupplierController;
use App\Http\Controllers\Admin\AdminDeployController;
use App\Http\Controllers\Admin\AdminDiagnosticsController;
use App\Http\Controllers\Admin\AdminDriverTokenController;
use App\Http\Controllers\Admin\AdminIntegrationController;
use App\Http\Controllers\Admin\AdminInvoiceController;
use App\Http\Controllers\Admin\AdminLmivController;
use App\Http\Controllers\Admin\AdminLmivImportController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminProductImportController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminTasksController;
use App\Http\Controllers\Admin\AdminProductImageController;
use App\Http\Controllers\Admin\AdminWarehouseController;
use App\Http\Controllers\Admin\AdminStockController;
use App\Http\Controllers\Admin\AdminStockMovementController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Health\HealthController;
use App\Http\Controllers\Payments\CheckoutController;
use App\Http\Controllers\Shop\AccountController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController as ShopCheckoutController;
use App\Http\Controllers\Shop\PageController;
use App\Http\Controllers\Shop\ShopController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shop (WP-21) — public
|--------------------------------------------------------------------------
*/
Route::get('/', [ShopController::class, 'index'])->name('shop.index');
Route::get('/produkte', [ShopController::class, 'index'])->name('shop.products');
Route::get('/produkte/{product}', [ShopController::class, 'show'])->name('shop.product');

// PROJ-2: 301 redirect from numeric product ID to slug-based URL
Route::get('/p/{id}', [ShopController::class, 'redirectById'])->where('id', '[0-9]+')->name('shop.product.legacy');

// CMS-Seiten (Impressum, AGB, Datenschutz, Widerruf …)
Route::get('/seite/{slug}', [PageController::class, 'show'])->name('page.show');

// Cart (PROJ-3: session-based for guests, DB-based for authenticated users)
Route::get('/warenkorb/mini', [CartController::class, 'miniCart'])->name('cart.mini');
Route::get('/warenkorb', [CartController::class, 'index'])->name('cart.index');
// Mutation routes: rate-limited (BUG-7 fix — 60/min per user or IP)
Route::post('/warenkorb', [CartController::class, 'add'])->name('cart.add')->middleware('throttle:cart');
Route::patch('/warenkorb/{productId}', [CartController::class, 'update'])->name('cart.update')->middleware('throttle:cart');
Route::delete('/warenkorb/alle', [CartController::class, 'clear'])->name('cart.clear')->middleware('throttle:cart');
Route::delete('/warenkorb/{productId}', [CartController::class, 'remove'])->name('cart.remove')->middleware('throttle:cart');

/*
|--------------------------------------------------------------------------
| Auth: Login, Register, Password Reset, Google OAuth (PROJ-1)
|--------------------------------------------------------------------------
*/
// Login (deutsch: /anmelden)
Route::get('/anmelden', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/anmelden', [LoginController::class, 'login'])->middleware(['guest', 'throttle:login']);
Route::post('/abmelden', [LoginController::class, 'logout'])->name('logout');

// Register (deutsch: /registrieren)
Route::get('/registrieren', [RegisterController::class, 'create'])->name('register')->middleware('guest');
Route::post('/registrieren', [RegisterController::class, 'store'])->middleware(['guest', 'throttle:10,1']);

// Password reset (deutsch: /passwort-vergessen, /passwort-reset)
Route::get('/passwort-vergessen', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request')->middleware('guest');
Route::post('/passwort-vergessen', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email')->middleware(['guest', 'throttle:5,1']);
Route::get('/passwort-reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset')->middleware('guest');
Route::post('/passwort-reset', [ResetPasswordController::class, 'reset'])->name('password.update')->middleware('guest');

// Google OAuth
Route::get('/auth/google', [SocialController::class, 'redirect'])->name('auth.google')->middleware(['guest', 'throttle:oauth']);
Route::get('/auth/google/callback', [SocialController::class, 'callback'])->middleware(['guest', 'throttle:oauth']);

/*
|--------------------------------------------------------------------------
| Shop — authenticated customers
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function (): void {

    // Checkout (PROJ-4: multi-step wizard)
    Route::get('/kasse', [ShopCheckoutController::class, 'index'])->name('checkout');
    // BUG-17 fix: rate-limit POST /kasse to 10 orders/min per user
    Route::post('/kasse', [ShopCheckoutController::class, 'store'])->name('checkout.store')->middleware('throttle:checkout');
    Route::get('/bestellung/{order}/abgeschlossen', [ShopCheckoutController::class, 'success'])
        ->name('checkout.success');

    // PayPal callbacks (PROJ-4)
    Route::get('/kasse/paypal/success', [ShopCheckoutController::class, 'paypalSuccess'])
        ->name('checkout.paypal.success');
    Route::get('/kasse/paypal/cancel', [ShopCheckoutController::class, 'paypalCancel'])
        ->name('checkout.paypal.cancel');

    // Customer account
    Route::get('/mein-konto', [AccountController::class, 'index'])->name('account');
    Route::get('/mein-konto/bestellungen', [AccountController::class, 'orders'])->name('account.orders');
    Route::get('/mein-konto/bestellungen/{order}', [AccountController::class, 'orderDetail'])->name('account.order');

    // Addresses
    Route::get('/mein-konto/adressen', [AccountController::class, 'addresses'])->name('account.addresses');
    Route::post('/mein-konto/adressen', [AccountController::class, 'storeAddress'])->name('account.addresses.store');
    Route::put('/mein-konto/adressen/{address}', [AccountController::class, 'updateAddress'])->name('account.addresses.update');
    Route::delete('/mein-konto/adressen/{address}', [AccountController::class, 'destroyAddress'])->name('account.addresses.destroy');
    Route::post('/mein-konto/adressen/{address}/als-standard', [AccountController::class, 'setDefaultAddress'])->name('account.addresses.setDefault');
});

/*
|--------------------------------------------------------------------------
| Health check
|--------------------------------------------------------------------------
*/
Route::get('/up', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]);
})->name('health');

/*
|--------------------------------------------------------------------------
| Lightweight health probes (WP-18)
|--------------------------------------------------------------------------
|
| JSON-returning endpoints for monitoring / uptime tools.
| No authentication required — these are read-only and return no PII.
|
*/
Route::get('/health/db', [HealthController::class, 'db'])->name('health.db');
Route::get('/health/storage', [HealthController::class, 'storage'])->name('health.storage');

/*
|--------------------------------------------------------------------------
| Driver PWA shell
|--------------------------------------------------------------------------
|
| The /driver URL serves the single-page PWA shell (Blade view).
| All sub-paths (for client-side routing) also return the shell.
|
*/
Route::get('/driver/{any?}', function () {
    return view('driver.app');
})->where('any', '.*');

/*
|--------------------------------------------------------------------------
| Legacy login route redirect (backward compat)
|--------------------------------------------------------------------------
| Redirects /login to /anmelden for any old links or middleware redirects
| that still point to /login.
*/
Route::get('/login', fn () => redirect()->route('login'))->middleware('guest');
Route::get('/register', fn () => redirect()->route('register'))->middleware('guest');

/*
|--------------------------------------------------------------------------
| Admin area
|--------------------------------------------------------------------------
|
| All routes are protected by the 'admin' middleware which requires a
| logged-in user with role = admin or mitarbeiter.
|
*/
/*
|--------------------------------------------------------------------------
| Payments (WP-17)
|--------------------------------------------------------------------------
|
| GET /payments/checkout/{invoice} — redirect to Stripe-hosted checkout page.
| Requires authenticated admin (uses same 'admin' + 'company' middleware).
|
*/
Route::middleware(['web', 'admin', 'company'])
    ->group(function (): void {
        Route::get('/payments/checkout/{invoice}', CheckoutController::class)
            ->name('payments.checkout');
    });

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['web', 'admin', 'company'])
    ->group(function (): void {

        // Dashboard (redirect to orders list)
        Route::get('/', fn () => redirect()->route('admin.orders.index'))->name('dashboard');

        // ── Orders ─────────────────────────────────────────────────────────
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');

        // ── Order editing (WP-22) ──────────────────────────────────────────
        Route::get('/orders/{order}/edit', [AdminOrderController::class, 'edit'])->name('orders.edit');
        Route::post('/orders/{order}/items', [AdminOrderController::class, 'updateItems'])->name('orders.items.update');
        Route::post('/orders/{order}/items/add', [AdminOrderController::class, 'addItem'])->name('orders.items.add');

        // ── Closeout (Leergut / Bruch) ─────────────────────────────────────
        Route::get('/orders/{order}/closeout', [AdminCloseoutController::class, 'show'])
            ->name('orders.closeout');
        Route::post('/orders/{order}/closeout', [AdminCloseoutController::class, 'store'])
            ->name('orders.closeout.store');

        // ── Invoices ───────────────────────────────────────────────────────
        Route::get('/invoices', [AdminInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/orders/{order}/invoice', [AdminInvoiceController::class, 'show'])
            ->name('orders.invoice');
        Route::post('/orders/{order}/invoice/draft', [AdminInvoiceController::class, 'draft'])
            ->name('orders.invoice.draft');
        Route::post('/invoices/{invoice}/finalize', [AdminInvoiceController::class, 'finalize'])
            ->name('invoices.finalize');
        Route::get('/invoices/{invoice}/download', [AdminInvoiceController::class, 'download'])
            ->name('invoices.download');

        // ── Stammdaten (WP-20) ────────────────────────────────────────────
        Route::resource('brands', AdminBrandController::class)
            ->except(['show', 'create', 'edit'])
            ->names([
                'index'   => 'brands.index',
                'store'   => 'brands.store',
                'update'  => 'brands.update',
                'destroy' => 'brands.destroy',
            ]);

        Route::resource('product-lines', AdminProductLineController::class)
            ->except(['show', 'create', 'edit'])
            ->names([
                'index'   => 'product-lines.index',
                'store'   => 'product-lines.store',
                'update'  => 'product-lines.update',
                'destroy' => 'product-lines.destroy',
            ]);

        Route::resource('categories', AdminCategoryController::class)
            ->except(['show', 'create', 'edit'])
            ->names([
                'index'   => 'categories.index',
                'store'   => 'categories.store',
                'update'  => 'categories.update',
                'destroy' => 'categories.destroy',
            ]);

        Route::resource('gebinde', AdminGebindeController::class)
            ->except(['show', 'create', 'edit'])
            ->names([
                'index'   => 'gebinde.index',
                'store'   => 'gebinde.store',
                'update'  => 'gebinde.update',
                'destroy' => 'gebinde.destroy',
            ]);

        Route::resource('pfand-items', AdminPfandItemController::class)
            ->except(['show', 'create', 'edit'])
            ->names([
                'index'   => 'pfand-items.index',
                'store'   => 'pfand-items.store',
                'update'  => 'pfand-items.update',
                'destroy' => 'pfand-items.destroy',
            ]);

        Route::resource('pfand-sets', AdminPfandSetController::class)
            ->except(['create', 'edit'])
            ->names([
                'index'   => 'pfand-sets.index',
                'store'   => 'pfand-sets.store',
                'show'    => 'pfand-sets.show',
                'update'  => 'pfand-sets.update',
                'destroy' => 'pfand-sets.destroy',
            ]);
        Route::post('pfand-sets/{pfandSet}/components',
            [AdminPfandSetController::class, 'storeComponent'])
            ->name('pfand-sets.components.store');
        Route::delete('pfand-sets/{pfandSet}/components/{component}',
            [AdminPfandSetController::class, 'destroyComponent'])
            ->name('pfand-sets.components.destroy');

        Route::resource('customer-groups', AdminCustomerGroupController::class)
            ->except(['show', 'create', 'edit'])
            ->names([
                'index'   => 'customer-groups.index',
                'store'   => 'customer-groups.store',
                'update'  => 'customer-groups.update',
                'destroy' => 'customer-groups.destroy',
            ]);
        // WP-21 — set default customer group for new registrations
        Route::post('customer-groups/{customerGroup}/set-default',
            [AdminCustomerGroupController::class, 'setDefault'])
            ->name('customer-groups.set-default');

        // ── Customers (WP-19) ─────────────────────────────────────────────
        Route::resource('customers', AdminCustomerController::class)
            ->names([
                'index'   => 'customers.index',
                'create'  => 'customers.create',
                'store'   => 'customers.store',
                'show'    => 'customers.show',
                'edit'    => 'customers.edit',
                'update'  => 'customers.update',
                'destroy' => 'customers.destroy',
            ]);
        Route::post('customers/{customer}/notes/{note}/resolve',
            [AdminCustomerController::class, 'resolveNote'])
            ->name('customers.notes.resolve');
        Route::post('customers/{customer}/merge',
            [AdminCustomerController::class, 'merge'])
            ->name('customers.merge');

        // ── Suppliers (WP-19) ──────────────────────────────────────────────
        Route::resource('suppliers', AdminSupplierController::class)
            ->except(['show'])
            ->names([
                'index'   => 'suppliers.index',
                'create'  => 'suppliers.create',
                'store'   => 'suppliers.store',
                'edit'    => 'suppliers.edit',
                'update'  => 'suppliers.update',
                'destroy' => 'suppliers.destroy',
            ]);
        Route::post('suppliers/{supplier}/merge',
            [AdminSupplierController::class, 'merge'])
            ->name('suppliers.merge');
        Route::post('suppliers/bulk-set-type',
            [AdminSupplierController::class, 'bulkSetType'])
            ->name('suppliers.bulk-set-type');

        // ── CSV Imports ────────────────────────────────────────────────────
        Route::get('/imports/customers', [AdminCustomerImportController::class, 'index'])
            ->name('imports.customers');
        Route::post('/imports/customers/upload', [AdminCustomerImportController::class, 'upload'])
            ->name('imports.customers.upload');
        Route::post('/imports/customers/execute', [AdminCustomerImportController::class, 'execute'])
            ->name('imports.customers.execute');

        Route::get('/imports/products', [AdminProductImportController::class, 'index'])
            ->name('imports.products');
        Route::post('/imports/products/upload', [AdminProductImportController::class, 'upload'])
            ->name('imports.products.upload');
        Route::post('/imports/products/execute', [AdminProductImportController::class, 'execute'])
            ->name('imports.products.execute');

        Route::get('/imports/suppliers', [AdminSupplierImportController::class, 'index'])
            ->name('imports.suppliers');
        Route::post('/imports/suppliers/upload', [AdminSupplierImportController::class, 'upload'])
            ->name('imports.suppliers.upload');
        Route::post('/imports/suppliers/execute', [AdminSupplierImportController::class, 'execute'])
            ->name('imports.suppliers.execute');

        Route::get('/imports/brands', [AdminBrandImportController::class, 'index'])
            ->name('imports.brands');
        Route::post('/imports/brands/upload', [AdminBrandImportController::class, 'upload'])
            ->name('imports.brands.upload');
        Route::post('/imports/brands/execute', [AdminBrandImportController::class, 'execute'])
            ->name('imports.brands.execute');

        Route::get('/imports/customer-groups', [AdminCustomerGroupImportController::class, 'index'])
            ->name('imports.customer-groups');
        Route::post('/imports/customer-groups/upload', [AdminCustomerGroupImportController::class, 'upload'])
            ->name('imports.customer-groups.upload');
        Route::post('/imports/customer-groups/execute', [AdminCustomerGroupImportController::class, 'execute'])
            ->name('imports.customer-groups.execute');

        Route::get('/imports/lmiv', [AdminLmivImportController::class, 'index'])
            ->name('imports.lmiv');
        Route::post('/imports/lmiv/upload', [AdminLmivImportController::class, 'upload'])
            ->name('imports.lmiv.upload');
        Route::post('/imports/lmiv/execute', [AdminLmivImportController::class, 'execute'])
            ->name('imports.lmiv.execute');

        // ── Products (WP-15 + WP-19) ──────────────────────────────────────
        // Note: Admin routes use {product:id} to bind by ID, since the
        // Product model's getRouteKeyName() was changed to 'slug' for PROJ-2.
        Route::get('/products', [AdminProductController::class, 'index'])
            ->name('products.index');
        // create/store BEFORE {product} to avoid route-model-binding on "create"
        Route::get('/products/create', [AdminProductController::class, 'create'])
            ->name('products.create');
        Route::post('/products', [AdminProductController::class, 'store'])
            ->name('products.store');
        Route::get('/products/{product:id}', [AdminProductController::class, 'show'])
            ->name('products.show');
        Route::get('/products/{product:id}/edit', [AdminProductController::class, 'edit'])
            ->name('products.edit');
        Route::match(['PUT', 'PATCH'], '/products/{product:id}', [AdminProductController::class, 'update'])
            ->name('products.update');
        Route::post('/products/{product:id}/mark-base-item', [AdminProductController::class, 'markAsBaseItem'])
            ->name('products.mark-base-item');

        // ── Product images (WP-21) ─────────────────────────────────────────
        Route::post('/products/{product:id}/images', [AdminProductImageController::class, 'store'])
            ->name('products.images.store');
        Route::delete('/products/{product:id}/images/{image}', [AdminProductImageController::class, 'destroy'])
            ->name('products.images.destroy');
        Route::post('/products/{product:id}/images/{image}/sort', [AdminProductImageController::class, 'sort'])
            ->name('products.images.sort');

        // ── LMIV (WP-15) ──────────────────────────────────────────────────
        Route::get('/lmiv', [AdminLmivController::class, 'index'])
            ->name('lmiv.index');
        Route::get('/products/{product:id}/lmiv', [AdminLmivController::class, 'edit'])
            ->name('lmiv.edit');
        Route::post('/products/{product:id}/lmiv', [AdminLmivController::class, 'update'])
            ->name('lmiv.update');
        Route::post('/products/{product:id}/lmiv/ean', [AdminLmivController::class, 'updateEan'])
            ->name('lmiv.update-ean');
        Route::post('/products/{product:id}/lmiv/new-version', [AdminLmivController::class, 'newVersion'])
            ->name('lmiv.new-version');
        Route::post('/products/{product:id}/lmiv/{version}/activate', [AdminLmivController::class, 'activate'])
            ->name('lmiv.activate');

        // ── Lager / Warehouse (PROJ-23) ───────────────────────────────────
        Route::get('/warehouses', [AdminWarehouseController::class, 'index'])
            ->name('warehouses.index');
        Route::get('/warehouses/create', [AdminWarehouseController::class, 'create'])
            ->name('warehouses.create');
        Route::post('/warehouses', [AdminWarehouseController::class, 'store'])
            ->name('warehouses.store');
        Route::get('/warehouses/{warehouse}', [AdminWarehouseController::class, 'show'])
            ->name('warehouses.show');
        Route::get('/warehouses/{warehouse}/edit', [AdminWarehouseController::class, 'edit'])
            ->name('warehouses.edit');
        Route::put('/warehouses/{warehouse}', [AdminWarehouseController::class, 'update'])
            ->name('warehouses.update');
        Route::delete('/warehouses/{warehouse}', [AdminWarehouseController::class, 'destroy'])
            ->name('warehouses.destroy');
        Route::get('/stock', [AdminStockController::class, 'index'])
            ->name('stock.index');
        Route::get('/stock-movements', [AdminStockMovementController::class, 'index'])
            ->name('stock-movements.index');

        // ── Reports (WP-16) ───────────────────────────────────────────────
        Route::get('/reports', [AdminReportController::class, 'index'])
            ->name('reports.index');
        Route::get('/reports/export/{type}', [AdminReportController::class, 'exportCsv'])
            ->name('reports.export')
            ->where('type', 'revenue|margin|deposit|tours');

        // ── Integrations (WP-17) ──────────────────────────────────────────
        Route::get('/integrations/lexoffice', [AdminIntegrationController::class, 'lexofficeIndex'])
            ->name('integrations.lexoffice');
        Route::post('/integrations/lexoffice', [AdminIntegrationController::class, 'lexofficeUpdate'])
            ->name('integrations.lexoffice.update');
        Route::post('/integrations/lexoffice/{invoice}/sync', [AdminIntegrationController::class, 'lexofficeSync'])
            ->name('integrations.lexoffice.sync');
        Route::post('/integrations/lexoffice/pull/customers', [AdminIntegrationController::class, 'lexofficePullCustomers'])
            ->name('integrations.lexoffice.pull.customers');
        Route::post('/integrations/lexoffice/pull/suppliers', [AdminIntegrationController::class, 'lexofficePullSuppliers'])
            ->name('integrations.lexoffice.pull.suppliers');
        Route::post('/integrations/lexoffice/pull/vouchers', [AdminIntegrationController::class, 'lexofficePullVouchers'])
            ->name('integrations.lexoffice.pull.vouchers');
        Route::post('/integrations/lexoffice/pull/payments', [AdminIntegrationController::class, 'lexofficePullPayments'])
            ->name('integrations.lexoffice.pull.payments');
        Route::post('/integrations/lexoffice/reset-imported', [AdminIntegrationController::class, 'lexofficeResetImported'])
            ->name('integrations.lexoffice.reset-imported');

        // ── Driver API tokens ──────────────────────────────────────────────
        Route::get('/driver-tokens', [AdminDriverTokenController::class, 'index'])
            ->name('driver-tokens.index');
        Route::get('/driver-tokens/create', [AdminDriverTokenController::class, 'create'])
            ->name('driver-tokens.create');
        Route::post('/driver-tokens', [AdminDriverTokenController::class, 'store'])
            ->name('driver-tokens.store');
        Route::delete('/driver-tokens/{token}', [AdminDriverTokenController::class, 'revoke'])
            ->name('driver-tokens.revoke');

        // ── Deferred tasks (WP-18) ────────────────────────────────────────
        Route::get('/tasks', [AdminTasksController::class, 'index'])
            ->name('tasks.index');
        Route::post('/tasks/{task}/retry', [AdminTasksController::class, 'retry'])
            ->name('tasks.retry');

        // ── Diagnostics ────────────────────────────────────────────────────
        Route::get('/diagnostics', [AdminDiagnosticsController::class, 'index'])
            ->name('diagnostics');

        // ── CMS-Seiten (PROJ-30) ──────────────────────────────────────────
        Route::get('/pages', [AdminPageController::class, 'index'])->name('pages.index');
        Route::get('/pages/create', [AdminPageController::class, 'create'])->name('pages.create');
        Route::post('/pages', [AdminPageController::class, 'store'])->name('pages.store');
        Route::get('/pages/{page}/edit', [AdminPageController::class, 'edit'])->name('pages.edit');
        Route::put('/pages/{page}', [AdminPageController::class, 'update'])->name('pages.update');
        Route::delete('/pages/{page}', [AdminPageController::class, 'destroy'])->name('pages.destroy');

        // ── Deployment operations ──────────────────────────────────────────
        Route::get('/deploy', [AdminDeployController::class, 'index'])
            ->name('deploy.index');
        Route::post('/deploy/migrate', [AdminDeployController::class, 'migrate'])
            ->name('deploy.migrate');
        Route::post('/deploy/cache', [AdminDeployController::class, 'cache'])
            ->name('deploy.cache');
        Route::post('/deploy/clear', [AdminDeployController::class, 'clear'])
            ->name('deploy.clear');
        Route::post('/deploy/backup', [AdminDeployController::class, 'backup'])
            ->name('deploy.backup');
    });
