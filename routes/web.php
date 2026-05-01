<?php

use App\Http\Controllers\Admin\CatalogOverviewController;
use App\Http\Controllers\Admin\AdminBulkAlkoholController;
use App\Http\Controllers\Admin\AdminPageController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\ReconcileCustomerController;
use App\Http\Controllers\Admin\ReconcileHubController;
use App\Http\Controllers\Admin\ReconcileProductController;
use App\Http\Controllers\Admin\ReconcileSupplierController;
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
use App\Http\Controllers\Admin\CommunicationController;
use App\Http\Controllers\Admin\CommunicationRuleController;
use App\Http\Controllers\Admin\CommunicationSettingsController;
use App\Http\Controllers\Admin\AdminInvoiceController;
use App\Http\Controllers\Admin\DebtorController;
use App\Http\Controllers\Admin\DebtorNoteController;
use App\Http\Controllers\Admin\DebtorSettingsController;
use App\Http\Controllers\Admin\DunningRunController;
use App\Http\Controllers\Admin\AdminLmivController;
use App\Http\Controllers\Admin\AdminLmivImportController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminProductImportController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminStatisticsController;
use App\Http\Controllers\Admin\AdminTasksController;
use App\Http\Controllers\Admin\AdminProductImageController;
use App\Http\Controllers\Admin\AdminWarehouseController;
use App\Http\Controllers\Admin\AdminStockController;
use App\Http\Controllers\Admin\AdminStockMovementController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\CustomerActivationController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Health\HealthController;
use App\Http\Controllers\Payments\CheckoutController;
use App\Http\Controllers\Shop\AccountController;
use App\Http\Controllers\Shop\FavoriteController;
use App\Http\Controllers\Shop\SubUserController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController as ShopCheckoutController;
use App\Http\Controllers\Shop\PageController;
use App\Http\Controllers\Shop\ShopController;
use App\Http\Controllers\UserPreferenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Dark-mode preference (works for guests via cookie, for users also persists to DB)
Route::post('/preferences/dark-mode', [UserPreferenceController::class, 'darkMode'])
    ->name('preferences.dark-mode')
    ->middleware('throttle:30,1');

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

// Shop display preferences — public (guests store in session, customers in DB)
Route::post('/ansicht', [ShopController::class, 'updateDisplayPreferences'])->name('shop.display_preferences.update');

// Cart (PROJ-3: session-based for guests, DB-based for authenticated users)
Route::get('/warenkorb/mini', [CartController::class, 'miniCart'])->name('cart.mini');
Route::get('/warenkorb', [CartController::class, 'index'])->name('cart.index');
// Mutation routes: rate-limited (BUG-7 fix — 60/min per user or IP)
Route::post('/warenkorb', [CartController::class, 'add'])->name('cart.add')->middleware(['throttle:cart', 'shop.order']);
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

// Unterbenutzer-Einladung (öffentlich, kein Auth nötig)
Route::get('/einladung/{token}', [InvitationController::class, 'show'])->name('sub-users.invitation.show');
Route::post('/einladung/{token}', [InvitationController::class, 'accept'])->name('sub-users.invitation.accept')->middleware('throttle:10,1');

// Bestehendes Kundenkonto aktivieren (guest only)
Route::middleware('guest')->group(function (): void {
    Route::get('/konto-aktivieren', [CustomerActivationController::class, 'showEmailForm'])->name('activation.show');
    Route::post('/konto-aktivieren', [CustomerActivationController::class, 'submitEmail'])->name('activation.submit')->middleware('throttle:activation');
    Route::get('/konto-aktivieren/code', [CustomerActivationController::class, 'showCodeForm'])->name('activation.code.show');
    Route::post('/konto-aktivieren/code', [CustomerActivationController::class, 'verifyCode'])->name('activation.code.verify')->middleware('throttle:10,1');
    Route::post('/konto-aktivieren/code/neu', [CustomerActivationController::class, 'resendCode'])->name('activation.code.resend')->middleware('throttle:5,1');
    Route::get('/konto-aktivieren/passwort', [CustomerActivationController::class, 'showPasswordForm'])->name('activation.password.show');
    Route::post('/konto-aktivieren/passwort', [CustomerActivationController::class, 'setPassword'])->name('activation.password.set')->middleware('throttle:10,1');
});

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

    // Invoices (Lexoffice)
    Route::get('/mein-konto/rechnungen', [AccountController::class, 'invoices'])->name('account.invoices');

    // Profile & email preferences
    Route::get('/mein-konto/profil', [AccountController::class, 'showProfile'])->name('account.profile');
    Route::post('/mein-konto/profil', [AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::post('/mein-konto/ansicht', [AccountController::class, 'updateDisplayPreferences'])->name('account.display_preferences.update');
    Route::post('/mein-konto/profil/passwort', [AccountController::class, 'changePassword'])->name('account.profile.password')->middleware('throttle:5,1');

    // Addresses
    Route::get('/mein-konto/adressen', [AccountController::class, 'addresses'])->name('account.addresses');
    Route::post('/mein-konto/adressen', [AccountController::class, 'storeAddress'])->name('account.addresses.store');
    Route::put('/mein-konto/adressen/{address}', [AccountController::class, 'updateAddress'])->name('account.addresses.update');
    Route::delete('/mein-konto/adressen/{address}', [AccountController::class, 'destroyAddress'])->name('account.addresses.destroy');
    Route::post('/mein-konto/adressen/{address}/als-standard', [AccountController::class, 'setDefaultAddress'])->name('account.addresses.setDefault');

    // Stammsortiment (PROJ-20)
    Route::get('/mein-konto/stammsortiment', [FavoriteController::class, 'index'])->name('account.favorites');
    Route::post('/mein-konto/stammsortiment', [FavoriteController::class, 'store'])->name('account.favorites.store');
    Route::delete('/mein-konto/stammsortiment/{favorite}', [FavoriteController::class, 'destroy'])->name('account.favorites.destroy');
    Route::patch('/mein-konto/stammsortiment/{favorite}/istbestand', [FavoriteController::class, 'updateActualStock'])->name('account.favorites.actual-stock');
    Route::patch('/mein-konto/stammsortiment/{favorite}/sollbestand', [FavoriteController::class, 'updateTargetStock'])->name('account.favorites.target-stock');
    Route::post('/mein-konto/stammsortiment/sortierung', [FavoriteController::class, 'reorder'])->name('account.favorites.reorder');
    Route::post('/mein-konto/stammsortiment/alle-in-warenkorb', [FavoriteController::class, 'addAllToCart'])->name('account.favorites.add-all');
    Route::post('/mein-konto/stammsortiment/direkt-bestellen', [FavoriteController::class, 'orderAll'])->name('account.favorites.order-all');
    Route::get('/mein-konto/stammsortiment/suche', [FavoriteController::class, 'search'])->name('account.favorites.search');

    // Invoice download (PROJ-20)
    Route::get('/mein-konto/rechnungen/{invoice}/download', [AccountController::class, 'downloadInvoice'])->name('account.invoice.download');
    Route::get('/mein-konto/rechnungen/lexoffice/{voucher}/download', [AccountController::class, 'downloadVoucherPdf'])->name('account.voucher.download');

    // Unterbenutzer (PROJ-21)
    Route::get('/mein-konto/unterbenutzer', [SubUserController::class, 'index'])->name('account.sub-users');
    Route::post('/mein-konto/unterbenutzer/einladen', [SubUserController::class, 'invite'])->name('account.sub-users.invite');
    Route::post('/mein-konto/unterbenutzer/{subUser}/rechte', [SubUserController::class, 'updatePermissions'])->name('account.sub-users.permissions');
    Route::post('/mein-konto/unterbenutzer/{subUser}/toggle', [SubUserController::class, 'toggleActive'])->name('account.sub-users.toggle');
    Route::delete('/mein-konto/unterbenutzer/{subUser}', [SubUserController::class, 'destroy'])->name('account.sub-users.destroy');

    // Onboarding tour helpers
    Route::post('/mein-konto/onboarding/{step}/hilfebox-schliessen', [CustomerActivationController::class, 'dismissHelpbox'])
        ->name('onboarding.helpbox.dismiss')
        ->middleware('throttle:30,1');
    Route::post('/mein-konto/onboarding/abschliessen', [CustomerActivationController::class, 'completeOnboarding'])
        ->name('onboarding.complete')
        ->middleware('throttle:10,1');
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

        // Dashboard
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Datenabgleich-Hub
        Route::get('/datenabgleich', [ReconcileHubController::class, 'index'])->name('reconcile.hub');

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

        // ── Debitoren / Mahnwesen (PROJ-31) ───────────────────────────────
        Route::get('/debitoren', [DebtorController::class, 'index'])->name('debtor.index');
        Route::get('/debitoren/{customer}', [DebtorController::class, 'show'])->name('debtor.show');
        Route::post('/debitoren/{customer}/hold', [DebtorController::class, 'toggleHold'])->name('debtor.hold');
        Route::post('/debitoren/{customer}/lieferfreigabe', [DebtorController::class, 'updateDeliveryStatus'])->name('debtor.delivery');
        Route::post('/debitoren/{customer}/kontoauszug', [DebtorController::class, 'sendAccountStatement'])->name('debtor.account_statement');
        Route::post('/debitoren/vouchers/{voucher}/block', [DebtorController::class, 'toggleVoucherBlock'])->name('debtor.voucher.block');
        Route::get('/debitoren/vouchers/{voucher}/pdf', [DebtorController::class, 'downloadVoucherPdf'])->name('debtor.voucher.pdf');

        // Debtor notes
        Route::post('/debitoren/{customer}/notizen', [DebtorNoteController::class, 'store'])->name('debtor.notes.store');
        Route::patch('/debitoren/notizen/{note}/status', [DebtorNoteController::class, 'updateStatus'])->name('debtor.notes.status');
        Route::delete('/debitoren/notizen/{note}', [DebtorNoteController::class, 'destroy'])->name('debtor.notes.destroy');

        // Dunning runs
        Route::get('/mahnlaeufe', [DunningRunController::class, 'index'])->name('dunning.index');
        Route::get('/mahnlaeufe/neu', [DunningRunController::class, 'create'])->name('dunning.create');
        Route::post('/mahnlaeufe', [DunningRunController::class, 'store'])->name('dunning.store');
        Route::get('/mahnlaeufe/{run}', [DunningRunController::class, 'show'])->name('dunning.show');
        Route::post('/debitoren/{customer}/mahnung', [DunningRunController::class, 'sendQuick'])->name('dunning.send_quick');
        Route::post('/mahnlaeufe/{run}/execute', [DunningRunController::class, 'execute'])->name('dunning.execute');
        Route::post('/mahnlaeufe/{run}/reset', [DunningRunController::class, 'reset'])->name('dunning.reset');
        Route::post('/mahnlaeufe/{run}/cancel', [DunningRunController::class, 'cancel'])->name('dunning.cancel');
        Route::post('/mahnlaeufe/{run}/items/{item}/skip', [DunningRunController::class, 'skipItem'])->name('dunning.skip');
        Route::get('/mahnlaeufe/{run}/items/{item}/pdf', [DunningRunController::class, 'downloadPdf'])->name('dunning.pdf');

        // Debtor settings
        Route::get('/einstellungen/mahnwesen', [DebtorSettingsController::class, 'edit'])->name('settings.dunning.edit');
        Route::post('/einstellungen/mahnwesen', [DebtorSettingsController::class, 'update'])->name('settings.dunning.update');

        // Shop display settings
        Route::get('/einstellungen/shop-ansicht', [\App\Http\Controllers\Admin\AdminShopDisplaySettingsController::class, 'edit'])->name('settings.shop_display.edit');
        Route::post('/einstellungen/shop-ansicht', [\App\Http\Controllers\Admin\AdminShopDisplaySettingsController::class, 'update'])->name('settings.shop_display.update');

        // Integration settings (API keys for all external services)
        Route::get('/einstellungen/integrationen',
            [\App\Http\Controllers\Admin\AdminIntegrationsSettingsController::class, 'index'])
            ->name('settings.integrations');
        Route::post('/einstellungen/integrationen',
            [\App\Http\Controllers\Admin\AdminIntegrationsSettingsController::class, 'update'])
            ->name('settings.integrations.update');

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

        Route::post('pfand-items/import', [AdminPfandItemController::class, 'import'])
            ->name('pfand-items.import');

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
        Route::post('customers/{customer}/link-wawi',
            [AdminCustomerController::class, 'linkWawi'])
            ->name('customers.link-wawi');

        // ── Customer Addresses ─────────────────────────────────────────────
        Route::post('customers/{customer}/addresses',
            [\App\Http\Controllers\Admin\AdminCustomerAddressController::class, 'store'])
            ->name('customers.addresses.store');
        Route::put('customers/{customer}/addresses/{address}',
            [\App\Http\Controllers\Admin\AdminCustomerAddressController::class, 'update'])
            ->name('customers.addresses.update');
        Route::delete('customers/{customer}/addresses/{address}',
            [\App\Http\Controllers\Admin\AdminCustomerAddressController::class, 'destroy'])
            ->name('customers.addresses.destroy');
        Route::post('customers/{customer}/addresses/{address}/set-default',
            [\App\Http\Controllers\Admin\AdminCustomerAddressController::class, 'setDefault'])
            ->name('customers.addresses.setDefault');

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
        Route::get('suppliers/{supplier}/merge-preview',
            [AdminSupplierController::class, 'mergePreview'])
            ->name('suppliers.merge-preview');
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

        // ── Catalog Overview ───────────────────────────────────────────────
        Route::get('/catalog/overview', [CatalogOverviewController::class, 'index'])
            ->name('catalog.overview');
        Route::post('/catalog/quick-create', [CatalogOverviewController::class, 'quickCreate'])
            ->name('catalog.quick-create');

        // ── Bulk Alkohol Editor ────────────────────────────────────────────
        Route::get('/products/bulk-alkohol', [AdminBulkAlkoholController::class, 'index'])
            ->name('products.bulk-alkohol');
        Route::post('/products/bulk-alkohol', [AdminBulkAlkoholController::class, 'update'])
            ->name('products.bulk-alkohol.update');

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
        Route::get('/products/{product:id}/create-basis-artikel', [AdminProductController::class, 'createBasisArtikel'])
            ->name('products.create-basis-artikel');
        Route::post('/products/{product:id}/store-basis-artikel', [AdminProductController::class, 'storeBasisArtikel'])
            ->name('products.store-basis-artikel');

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

        // ── Einkauf / Purchase Orders (PROJ-32) ──────────────────────────
        Route::prefix('einkauf')->name('einkauf.')->group(function () {
            Route::get('/bestellvorschlaege', [\App\Http\Controllers\Admin\AdminBestellvorschlagController::class, 'index'])
                ->name('bestellvorschlaege');
            Route::get('/', [\App\Http\Controllers\Admin\AdminEinkaufController::class, 'index'])
                ->name('index');
            Route::get('/create', [\App\Http\Controllers\Admin\AdminEinkaufController::class, 'create'])
                ->name('create');
            Route::get('/api/product-search', [\App\Http\Controllers\Admin\AdminEinkaufController::class, 'productSearch'])
                ->name('product-search');
            Route::post('/api/supplier-filter', [\App\Http\Controllers\Admin\AdminEinkaufController::class, 'toggleSupplierFilter'])
                ->name('supplier-filter');
            Route::post('/api/import-wawi', [\App\Http\Controllers\Admin\AdminEinkaufController::class, 'importWawiProduct'])
                ->name('import-wawi');
            Route::post('/', [\App\Http\Controllers\Admin\AdminEinkaufController::class, 'store'])
                ->name('store');
            Route::get('/{purchaseOrder}', [\App\Http\Controllers\Admin\AdminEinkaufController::class, 'show'])
                ->name('show');
            Route::get('/{purchaseOrder}/edit', [\App\Http\Controllers\Admin\AdminEinkaufController::class, 'edit'])
                ->name('edit');
            Route::put('/{purchaseOrder}', [\App\Http\Controllers\Admin\AdminEinkaufController::class, 'update'])
                ->name('update');
            Route::delete('/{purchaseOrder}', [\App\Http\Controllers\Admin\AdminEinkaufController::class, 'destroy'])
                ->name('destroy');
            Route::post('/{purchaseOrder}/send', [\App\Http\Controllers\Admin\AdminEinkaufController::class, 'send'])
                ->name('send');
            Route::post('/{purchaseOrder}/cancel', [\App\Http\Controllers\Admin\AdminEinkaufController::class, 'cancel'])
                ->name('cancel');
            Route::get('/{purchaseOrder}/pdf', [\App\Http\Controllers\Admin\AdminEinkaufController::class, 'pdf'])
                ->name('pdf');
            Route::post('/{purchaseOrder}/items/reorder', [\App\Http\Controllers\Admin\AdminEinkaufController::class, 'reorderItems'])
                ->name('items.reorder');
            Route::patch('/{purchaseOrder}/items/{item}/price', [\App\Http\Controllers\Admin\AdminEinkaufController::class, 'updateItemPrice'])
                ->name('items.price');
            Route::post('/{purchaseOrder}/wareneingang', [\App\Http\Controllers\Admin\AdminEinkaufWareneingangController::class, 'store'])
                ->name('wareneingang');
            Route::post('/{purchaseOrder}/wareneingang/correct', [\App\Http\Controllers\Admin\AdminEinkaufWareneingangController::class, 'correct'])
                ->name('wareneingang.correct');
        });

        // ── Reports (WP-16) ───────────────────────────────────────────────
        Route::get('/reports', [AdminReportController::class, 'index'])
            ->name('reports.index');
        Route::get('/reports/export/{type}', [AdminReportController::class, 'exportCsv'])
            ->name('reports.export')
            ->where('type', 'revenue|margin|deposit|tours');

        // ── Statistik (POS-Verkauf + Einkaufsplanung) ─────────────────────
        Route::prefix('statistics')->name('statistics.')->group(function () {
            Route::get('/pos-top',           [AdminStatisticsController::class, 'posTop'])            ->name('pos_top');
            Route::get('/purchase-planning', [AdminStatisticsController::class, 'purchasePlanning'])  ->name('purchase_planning');
            Route::get('/purchase-planning/export', [AdminStatisticsController::class, 'exportPurchasePlanning'])->name('purchase_planning.export');
            Route::get('/warengruppen',      [AdminStatisticsController::class, 'warengruppen'])      ->name('warengruppen');
            Route::get('/pfand',             [AdminStatisticsController::class, 'pfandStatistik'])   ->name('pfand');
            Route::get('/artikel/{artnr}',        [AdminStatisticsController::class, 'artikelDetail'])      ->name('artikel')->where('artnr', '.+');
            Route::get('/mhd-abschreibungen',     [AdminStatisticsController::class, 'mhdAbschreibungen']) ->name('mhd_abschreibungen');
        });

        // ── Kommunikation / Gmail ─────────────────────────────────────────
        Route::prefix('communications')->name('communications.')->group(function () {
            Route::get('/',         [CommunicationController::class, 'index'])        ->name('index');
            Route::post('/manual',  [CommunicationController::class, 'createManual']) ->name('manual.store');

            // Rules — before /{communication} wildcard
            Route::get('/rules/list',           [CommunicationRuleController::class, 'index'])   ->name('rules.index');
            Route::post('/rules',               [CommunicationRuleController::class, 'store'])   ->name('rules.store');
            Route::put('/rules/{rule}',         [CommunicationRuleController::class, 'update'])  ->name('rules.update');
            Route::delete('/rules/{rule}',      [CommunicationRuleController::class, 'destroy']) ->name('rules.destroy');

            // Settings / Gmail OAuth — before /{communication} wildcard
            Route::get('/settings',                      [CommunicationSettingsController::class, 'index'])          ->name('settings');
            Route::post('/settings/gmail-connect',       [CommunicationSettingsController::class, 'gmailConnect'])   ->name('settings.gmail.connect');
            Route::get('/settings/gmail-callback',       [CommunicationSettingsController::class, 'gmailCallback'])  ->name('settings.gmail.callback');
            Route::post('/settings/gmail-sync',          [CommunicationSettingsController::class, 'gmailSync'])      ->name('settings.gmail.sync');
            Route::post('/settings/gmail-disconnect',    [CommunicationSettingsController::class, 'gmailDisconnect'])->name('settings.gmail.disconnect');

            // Wildcard detail routes — last
            Route::get('/{communication}',             [CommunicationController::class, 'show'])    ->name('show');
            Route::post('/{communication}/assign',     [CommunicationController::class, 'assign'])       ->name('assign');
            Route::post('/{communication}/review',     [CommunicationController::class, 'review'])       ->name('review');
            Route::post('/{communication}/archive',    [CommunicationController::class, 'archive'])      ->name('archive');
            Route::post('/{communication}/status',     [CommunicationController::class, 'updateStatus']) ->name('status');
            Route::post('/{communication}/reply',      [CommunicationController::class, 'reply'])        ->name('reply');
        });

        // ── Integrations (WP-17) ──────────────────────────────────────────
        Route::get('/integrations/lexoffice', [AdminIntegrationController::class, 'lexofficeIndex'])
            ->name('integrations.lexoffice');
        Route::post('/integrations/lexoffice', [AdminIntegrationController::class, 'lexofficeUpdate'])
            ->name('integrations.lexoffice.update');
        Route::post('/integrations/lexoffice/{invoice}/sync', [AdminIntegrationController::class, 'lexofficeSync'])
            ->name('integrations.lexoffice.sync');
        Route::post('/integrations/lexoffice/import-all', [AdminIntegrationController::class, 'lexofficeImportAll'])
            ->name('integrations.lexoffice.import-all');
        Route::post('/integrations/lexoffice/import-payments', [AdminIntegrationController::class, 'lexofficeImportPayments'])
            ->name('integrations.lexoffice.import-payments');
        Route::post('/integrations/lexoffice/reconcile', [AdminIntegrationController::class, 'lexofficeReconcile'])
            ->name('integrations.lexoffice.reconcile');
        Route::post('/integrations/lexoffice/pull/customers', [AdminIntegrationController::class, 'lexofficePullCustomers'])
            ->name('integrations.lexoffice.pull.customers');
        Route::post('/integrations/lexoffice/pull/suppliers', [AdminIntegrationController::class, 'lexofficePullSuppliers'])
            ->name('integrations.lexoffice.pull.suppliers');
        Route::post('/integrations/lexoffice/run-sync', [AdminIntegrationController::class, 'lexofficeRunSync'])
            ->name('integrations.lexoffice.run-sync');
        Route::post('/integrations/lexoffice/pull/vouchers', [AdminIntegrationController::class, 'lexofficePullVouchers'])
            ->name('integrations.lexoffice.pull.vouchers');
        Route::post('/integrations/lexoffice/pull/payments', [AdminIntegrationController::class, 'lexofficePullPayments'])
            ->name('integrations.lexoffice.pull.payments');
        Route::post('/integrations/lexoffice/reset-imported', [AdminIntegrationController::class, 'lexofficeResetImported'])
            ->name('integrations.lexoffice.reset-imported');

        // ── Lexoffice Bank Matching ───────────────────────────────────────
        Route::get('/integrations/lexoffice/bank-matching', [\App\Http\Controllers\Admin\LexofficeBankMatchingController::class, 'index'])
            ->name('integrations.lexoffice.bank-matching');
        Route::post('/integrations/lexoffice/bank-matching/pull', [\App\Http\Controllers\Admin\LexofficeBankMatchingController::class, 'pull'])
            ->name('integrations.lexoffice.bank-matching.pull');
        Route::post('/integrations/lexoffice/bank-matching/{voucher}/link', [\App\Http\Controllers\Admin\LexofficeBankMatchingController::class, 'link'])
            ->name('integrations.lexoffice.bank-matching.link');
        Route::post('/integrations/lexoffice/bank-matching/{voucher}/confirm', [\App\Http\Controllers\Admin\LexofficeBankMatchingController::class, 'confirm'])
            ->name('integrations.lexoffice.bank-matching.confirm');
        Route::post('/integrations/lexoffice/bank-matching/{voucher}/note', [\App\Http\Controllers\Admin\LexofficeBankMatchingController::class, 'note'])
            ->name('integrations.lexoffice.bank-matching.note');

        // ── Cash registers & driver settings ──────────────────────────────
        Route::prefix('cash-registers')->name('cash-registers.')->group(function () {
            Route::get('/',                        [\App\Http\Controllers\Admin\CashRegisterController::class, 'index'])          ->name('index');
            Route::post('/',                       [\App\Http\Controllers\Admin\CashRegisterController::class, 'store'])          ->name('store');
            Route::post('{register}/toggle',       [\App\Http\Controllers\Admin\CashRegisterController::class, 'toggle'])         ->name('toggle');
            Route::post('{register}/assign',       [\App\Http\Controllers\Admin\CashRegisterController::class, 'assignEmployee']) ->name('assign-employee');
            Route::get('{register}/transactions',  [\App\Http\Controllers\Admin\CashRegisterController::class, 'transactions'])   ->name('transactions');
            Route::post('settings',                [\App\Http\Controllers\Admin\CashRegisterController::class, 'saveSettings'])   ->name('save-settings');
        });

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

        // ── Feature-Übersicht ──────────────────────────────────────────────
        Route::get('/features', fn () => view('admin.features'))->name('features');

        // ── CMS-Seiten (PROJ-30) ──────────────────────────────────────────
        Route::get('/pages', [AdminPageController::class, 'index'])->name('pages.index');
        Route::get('/pages/create', [AdminPageController::class, 'create'])->name('pages.create');
        Route::post('/pages', [AdminPageController::class, 'store'])->name('pages.store');
        Route::get('/pages/{page}/edit', [AdminPageController::class, 'edit'])->name('pages.edit');
        Route::put('/pages/{page}', [AdminPageController::class, 'update'])->name('pages.update');
        Route::delete('/pages/{page}', [AdminPageController::class, 'destroy'])->name('pages.destroy');

        // ── Datenabgleich (Reconcile) ─────────────────────────────────────
        Route::get('/reconcile/customers', [ReconcileCustomerController::class, 'index'])
            ->name('reconcile.customers');
        Route::post('/reconcile/customers/auto-match', [ReconcileCustomerController::class, 'autoMatch'])
            ->name('reconcile.customers.auto-match');
        Route::post('/reconcile/customers/confirm-all', [ReconcileCustomerController::class, 'confirmAllAbove'])
            ->name('reconcile.customers.confirm-all');
        Route::post('/reconcile/customers/confirm-all-100', [ReconcileCustomerController::class, 'confirmAll100'])
            ->name('reconcile.customers.confirm-all-100');
        Route::post('/reconcile/customers/confirm', [ReconcileCustomerController::class, 'confirm'])
            ->name('reconcile.customers.confirm');
        Route::post('/reconcile/customers/create-from', [ReconcileCustomerController::class, 'createFrom'])
            ->name('reconcile.customers.create-from');
        Route::post('/reconcile/customers/ignore', [ReconcileCustomerController::class, 'ignore'])
            ->name('reconcile.customers.ignore');
        Route::post('/reconcile/customers/sync-all', [ReconcileCustomerController::class, 'syncAll'])
            ->name('reconcile.customers.sync-all');

        Route::get('/reconcile/suppliers', [ReconcileSupplierController::class, 'index'])
            ->name('reconcile.suppliers');
        Route::post('/reconcile/suppliers/confirm', [ReconcileSupplierController::class, 'confirm'])
            ->name('reconcile.suppliers.confirm');
        Route::post('/reconcile/suppliers/create-from', [ReconcileSupplierController::class, 'createFrom'])
            ->name('reconcile.suppliers.create-from');
        Route::post('/reconcile/suppliers/ignore', [ReconcileSupplierController::class, 'ignore'])
            ->name('reconcile.suppliers.ignore');

        Route::get('/reconcile/products', [ReconcileProductController::class, 'index'])
            ->name('reconcile.products');
        Route::post('/reconcile/products/auto-match', [ReconcileProductController::class, 'autoMatch'])
            ->name('reconcile.products.auto-match');
        Route::post('/reconcile/products/confirm-all-100', [ReconcileProductController::class, 'confirmAll100'])
            ->name('reconcile.products.confirm-all-100');
        Route::post('/reconcile/products/confirm', [ReconcileProductController::class, 'confirm'])
            ->name('reconcile.products.confirm');
        Route::post('/reconcile/products/ignore', [ReconcileProductController::class, 'ignore'])
            ->name('reconcile.products.ignore');
        Route::post('/reconcile/products/import-confirmed', [ReconcileProductController::class, 'importConfirmed'])
            ->name('reconcile.products.import-confirmed');
        Route::post('/reconcile/products/bulk-confirm', [ReconcileProductController::class, 'bulkConfirm'])
            ->name('reconcile.products.bulk-confirm');
        Route::post('/reconcile/products/bulk-ignore', [ReconcileProductController::class, 'bulkIgnore'])
            ->name('reconcile.products.bulk-ignore');
        Route::post('/reconcile/products/reset', [ReconcileProductController::class, 'reset'])
            ->name('reconcile.products.reset');
        Route::get('/reconcile/products/wawi-search', [ReconcileProductController::class, 'wawiSearch'])
            ->name('reconcile.products.wawi-search');
        Route::get('/reconcile/products/new-product-form-data', [ReconcileProductController::class, 'newProductFormData'])
            ->name('reconcile.products.new-product-form-data');
        Route::post('/reconcile/products/create-product', [ReconcileProductController::class, 'createProduct'])
            ->name('reconcile.products.create-product');
        Route::get('/reconcile/products/suggest-rules', [ReconcileProductController::class, 'suggestRules'])
            ->name('reconcile.products.suggest-rules');
        Route::get('/reconcile/products/rules', [ReconcileProductController::class, 'listRules'])
            ->name('reconcile.products.rules.index');
        Route::post('/reconcile/products/rules', [ReconcileProductController::class, 'storeRule'])
            ->name('reconcile.products.rules.store');
        Route::delete('/reconcile/products/rules/{id}', [ReconcileProductController::class, 'deleteRule'])
            ->name('reconcile.products.rules.delete');

        // ── Mitarbeiter-Abgleich ──────────────────────────────────────────
        Route::get('/reconcile/employees', [\App\Http\Controllers\Admin\ReconcileEmployeeController::class, 'index'])
            ->name('reconcile.employees');
        Route::post('/reconcile/employees/auto-match', [\App\Http\Controllers\Admin\ReconcileEmployeeController::class, 'autoMatch'])
            ->name('reconcile.employees.auto-match');
        Route::post('/reconcile/employees/confirm-all', [\App\Http\Controllers\Admin\ReconcileEmployeeController::class, 'confirmAllAuto'])
            ->name('reconcile.employees.confirm-all');
        Route::post('/reconcile/employees/confirm', [\App\Http\Controllers\Admin\ReconcileEmployeeController::class, 'confirm'])
            ->name('reconcile.employees.confirm');
        Route::post('/reconcile/employees/create-from', [\App\Http\Controllers\Admin\ReconcileEmployeeController::class, 'createFrom'])
            ->name('reconcile.employees.create-from');
        Route::post('/reconcile/employees/ignore', [\App\Http\Controllers\Admin\ReconcileEmployeeController::class, 'ignore'])
            ->name('reconcile.employees.ignore');

        // ── GetraenkeDB-Abgleich ───────────────────────────────────────────
        Route::prefix('reconcile/getraenkedb')->name('reconcile.getraenkedb.')->group(function () {
            Route::get('/',        [\App\Http\Controllers\Admin\ReconcileGetraenkeDbController::class, 'index'])  ->name('index');
            Route::post('confirm',      [\App\Http\Controllers\Admin\ReconcileGetraenkeDbController::class, 'confirm'])     ->name('confirm');
            Route::post('ignore',       [\App\Http\Controllers\Admin\ReconcileGetraenkeDbController::class, 'ignore'])      ->name('ignore');
            Route::post('bulk-confirm', [\App\Http\Controllers\Admin\ReconcileGetraenkeDbController::class, 'bulkConfirm'])->name('bulk-confirm');
            Route::post('bulk-ignore',  [\App\Http\Controllers\Admin\ReconcileGetraenkeDbController::class, 'bulkIgnore']) ->name('bulk-ignore');
            Route::post('sync',              [\App\Http\Controllers\Admin\ReconcileGetraenkeDbController::class, 'sync'])          ->name('sync');
            Route::post('sync-categories',   [\App\Http\Controllers\Admin\ReconcileGetraenkeDbController::class, 'syncCategories'])->name('sync-categories');
            Route::post('clear-cache',       [\App\Http\Controllers\Admin\ReconcileGetraenkeDbController::class, 'clearCache'])      ->name('clear-cache');
        });

        // ── Benutzerverwaltung ─────────────────────────────────────────────
        Route::get('/users', [\App\Http\Controllers\Admin\AdminUserController::class, 'index'])
            ->name('users.index');
        Route::post('/users', [\App\Http\Controllers\Admin\AdminUserController::class, 'store'])
            ->name('users.store');
        Route::patch('/users/{user}', [\App\Http\Controllers\Admin\AdminUserController::class, 'update'])
            ->name('users.update');
        Route::post('/users/{user}/reset-password', [\App\Http\Controllers\Admin\AdminUserController::class, 'resetPassword'])
            ->name('users.reset-password');
        Route::post('/users/{user}/set-password', [\App\Http\Controllers\Admin\AdminUserController::class, 'setPassword'])
            ->name('users.set-password');

        // ── Mitarbeiter-Aufgaben (Admin-CRUD) ────────────────────────────────
        Route::resource('/employee-tasks', \App\Http\Controllers\Admin\AdminEmployeeTaskController::class)
            ->names('emp-tasks')
            ->parameters(['employee-tasks' => 'task']);
        Route::post('/employee-tasks/{task}/comments', [\App\Http\Controllers\Admin\AdminEmployeeTaskController::class, 'addComment'])
            ->name('emp-tasks.comment');
        Route::post('/employee-tasks/{task}/complete', [\App\Http\Controllers\Admin\AdminEmployeeTaskController::class, 'completeTask'])
            ->name('emp-tasks.complete');

        // ── Wiederkehrende Aufgaben (Ninox) ───────────────────────────────
        Route::get('/recurring-tasks', [\App\Http\Controllers\Admin\AdminRecurringTasksController::class, 'index'])
            ->name('recurring-tasks.index');
        Route::post('/recurring-tasks/users/{user}', [\App\Http\Controllers\Admin\AdminRecurringTasksController::class, 'updateUser'])
            ->name('recurring-tasks.update-user');
        Route::post('/recurring-tasks/settings', [\App\Http\Controllers\Admin\AdminRecurringTasksController::class, 'updateTaskSetting'])
            ->name('recurring-tasks.update-setting');

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

/*
|--------------------------------------------------------------------------
| Mitarbeiter-Bereich
|--------------------------------------------------------------------------
|
| Für eingeloggte Mitarbeiter (admin + mitarbeiter). Zeigt nur Daten
| passend zur Zuständigkeit des eingeloggten Nutzers.
|
*/
Route::prefix('mitarbeiter')
    ->name('employee.')
    ->middleware(['web', 'admin', 'company'])
    ->group(function (): void {
        Route::get('/aufgaben', [\App\Http\Controllers\Employee\EmployeeTaskController::class, 'index'])
            ->name('tasks.index');
        Route::post('/aufgaben/erledigen', [\App\Http\Controllers\Employee\EmployeeTaskController::class, 'complete'])
            ->name('tasks.complete');

        Route::get('/kasse',              [\App\Http\Controllers\Employee\EmployeeCashController::class, 'index'])->name('cash.index');
        Route::post('/kasse/buchen',      [\App\Http\Controllers\Employee\EmployeeCashController::class, 'store'])->name('cash.store');
        Route::post('/kasse/kassensturz', [\App\Http\Controllers\Employee\EmployeeCashController::class, 'kassensturz'])->name('cash.kassensturz');
    });

// ── Mitarbeiterverwaltung ─────────────────────────────────────────────────
Route::prefix('admin/employees')->name('admin.employees.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',             [\App\Http\Controllers\Admin\EmployeeDashboardController::class, 'index'])->name('dashboard');
    Route::get('/list',         [\App\Http\Controllers\Admin\EmployeeController::class, 'index'])->name('index');
    Route::get('/create',       [\App\Http\Controllers\Admin\EmployeeController::class, 'create'])->name('create');
    Route::post('/',            [\App\Http\Controllers\Admin\EmployeeController::class, 'store'])->name('store');
    Route::get('/{employee}',               [\App\Http\Controllers\Admin\EmployeeController::class, 'edit'])->name('edit');
    Route::patch('/{employee}',             [\App\Http\Controllers\Admin\EmployeeController::class, 'update'])->name('update');
    Route::delete('/{employee}',            [\App\Http\Controllers\Admin\EmployeeController::class, 'destroy'])->name('destroy');
    Route::post('/{employee}/sync-ninox',         [\App\Http\Controllers\Admin\EmployeeController::class, 'syncNinox'])->name('sync-ninox');
    Route::post('/{employee}/reset-onboarding',   [\App\Http\Controllers\Admin\EmployeeController::class, 'resetOnboarding'])->name('reset-onboarding');
});

Route::prefix('admin/shifts')->name('admin.shifts.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',             [\App\Http\Controllers\Admin\ShiftController::class, 'index'])->name('index');
    Route::post('/',            [\App\Http\Controllers\Admin\ShiftController::class, 'store'])->name('store');
    Route::patch('/{shift}',    [\App\Http\Controllers\Admin\ShiftController::class, 'update'])->name('update');
    Route::delete('/{shift}',   [\App\Http\Controllers\Admin\ShiftController::class, 'destroy'])->name('destroy');
});

Route::prefix('admin/shifts/reports')->name('admin.shifts.reports.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',                  [App\Http\Controllers\Admin\ShiftReportController::class, 'index'])->name('index');
    Route::get('/create/{shift}',    [App\Http\Controllers\Admin\ShiftReportController::class, 'create'])->name('create');
    Route::post('/shift/{shift}',    [App\Http\Controllers\Admin\ShiftReportController::class, 'store'])->name('store');
    Route::get('/{report}/edit',     [App\Http\Controllers\Admin\ShiftReportController::class, 'edit'])->name('edit');
    Route::patch('/{report}',        [App\Http\Controllers\Admin\ShiftReportController::class, 'update'])->name('update');
    Route::post('/{report}/submit',  [App\Http\Controllers\Admin\ShiftReportController::class, 'submit'])->name('submit');
});

Route::prefix('admin/vacation')->name('admin.vacation.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',                       [\App\Http\Controllers\Admin\VacationAdminController::class, 'index'])->name('index');
    Route::post('/',                      [\App\Http\Controllers\Admin\VacationAdminController::class, 'store'])->name('store');
    Route::patch('/balance',              [\App\Http\Controllers\Admin\VacationAdminController::class, 'updateBalance'])->name('balance');
    Route::post('/{request}/approve',     [\App\Http\Controllers\Admin\VacationAdminController::class, 'approve'])->name('approve');
    Route::post('/{request}/reject',      [\App\Http\Controllers\Admin\VacationAdminController::class, 'reject'])->name('reject');
});

Route::patch('admin/feedback/{feedback}', [\App\Http\Controllers\Admin\FeedbackAdminController::class, 'update'])
    ->name('admin.feedback.update')
    ->middleware(['auth', 'admin']);

Route::prefix('admin/time-tracking')->name('admin.time.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',                                  [\App\Http\Controllers\Admin\TimeTrackingAdminController::class, 'index'])->name('index');
    Route::patch('/entries/{entry}',                 [\App\Http\Controllers\Admin\TimeTrackingAdminController::class, 'correct'])->name('correct');
});

// Employee-facing (no login required for timeclock — PIN-based)
Route::prefix('timeclock')->name('timeclock.')->group(function () {
    Route::get('/',                [\App\Http\Controllers\Employee\TimeClockController::class, 'index'])->name('index');
    Route::post('/authenticate',   [\App\Http\Controllers\Employee\TimeClockController::class, 'authenticate'])->name('authenticate');
    Route::post('/action',         [\App\Http\Controllers\Employee\TimeClockController::class, 'action'])->name('action');
    Route::post('/device/init',    [\App\Http\Controllers\Employee\TimeClockController::class, 'deviceInit'])->name('device.init');
    Route::get('/device/{token}',  [\App\Http\Controllers\Employee\TimeClockController::class, 'deviceGet'])->name('device.get');
    Route::post('/device/set',     [\App\Http\Controllers\Employee\TimeClockController::class, 'deviceSet'])->name('device.set');
});

// Employee self-service portal (/mein — PIN-session auth)
Route::prefix('mein')->name('mein.')->middleware(['employee-session'])->group(function () {
    Route::get('/',                                      [\App\Http\Controllers\Employee\MeinController::class, 'dashboard'])->name('dashboard');
    Route::get('/schicht',                               [\App\Http\Controllers\Employee\MeinController::class, 'schicht'])->name('schicht');
    Route::post('/schicht',                              [\App\Http\Controllers\Employee\MeinController::class, 'schichtSave'])->name('schicht.save');
    Route::get('/aufgaben',                              [\App\Http\Controllers\Employee\MeinController::class, 'aufgaben'])->name('aufgaben');
    Route::post('/aufgaben',                             [\App\Http\Controllers\Employee\MeinController::class, 'aufgabeStore'])->name('aufgabe.store');
    Route::post('/aufgaben/complete',                    [\App\Http\Controllers\Employee\MeinController::class, 'taskComplete'])->name('task.complete');
    Route::get('/aufgaben/{task}',                       [\App\Http\Controllers\Employee\MeinController::class, 'aufgabeDetail'])->name('aufgabe.show');
    Route::post('/aufgaben/{task}/start',                [\App\Http\Controllers\Employee\MeinController::class, 'aufgabeStart'])->name('aufgabe.start');
    Route::post('/aufgaben/{task}/complete',             [\App\Http\Controllers\Employee\MeinController::class, 'aufgabeComplete'])->name('aufgabe.complete');
    Route::post('/aufgaben/{task}/reopen',               [\App\Http\Controllers\Employee\MeinController::class, 'aufgabeReopen'])->name('aufgabe.reopen');
    Route::post('/aufgaben/{task}/comments',             [\App\Http\Controllers\Employee\MeinController::class, 'aufgabeComment'])->name('aufgabe.comment');
    Route::get('/news',                                  [\App\Http\Controllers\Employee\MeinController::class, 'liveblog'])->name('news');
    Route::post('/timeclock-action',                     [\App\Http\Controllers\Employee\MeinController::class, 'timeclockAction'])->name('timeclock.action');
    Route::post('/logout',                               [\App\Http\Controllers\Employee\MeinController::class, 'logout'])->name('logout');
    Route::post('/notifications/{notification}/read',    [\App\Http\Controllers\Employee\MeinController::class, 'markNotificationRead'])->name('notification.read');
    Route::post('/feedback',                             [\App\Http\Controllers\Employee\MeinController::class, 'feedbackStore'])->name('feedback.store');
    Route::get('/urlaub',                                [\App\Http\Controllers\Employee\MeinVacationController::class, 'index'])->name('urlaub');
    Route::post('/urlaub',                               [\App\Http\Controllers\Employee\MeinVacationController::class, 'store'])->name('urlaub.store');
    Route::post('/urlaub/{request}/cancel',              [\App\Http\Controllers\Employee\MeinVacationController::class, 'cancel'])->name('urlaub.cancel');

    Route::get('/kasse',              [\App\Http\Controllers\Employee\MeinCashController::class, 'index'])->name('kasse');
    Route::post('/kasse/buchen',      [\App\Http\Controllers\Employee\MeinCashController::class, 'store'])->name('kasse.store');
    Route::post('/kasse/kassensturz', [\App\Http\Controllers\Employee\MeinCashController::class, 'kassensturz'])->name('kasse.kassensturz');
});

Route::prefix('vacation')->name('vacation.')->middleware(['auth'])->group(function () {
    Route::get('/',  [\App\Http\Controllers\Employee\VacationRequestController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\Employee\VacationRequestController::class, 'store'])->name('store');
});

// ── Mitarbeiter-Onboarding (public, no auth) ───────────────────────────────
Route::prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/',                 [\App\Http\Controllers\Onboarding\OnboardingController::class, 'start'])->name('start');
    Route::post('/start',           [\App\Http\Controllers\Onboarding\OnboardingController::class, 'postStart'])->name('post-start');
    Route::get('/verify',           [\App\Http\Controllers\Onboarding\OnboardingController::class, 'verifyForm'])->name('verify');
    Route::post('/verify',          [\App\Http\Controllers\Onboarding\OnboardingController::class, 'postVerify'])->name('post-verify');
    Route::get('/verify/{token}',   [\App\Http\Controllers\Onboarding\OnboardingController::class, 'verifyLink'])->name('verify.link');
    Route::get('/formular',         [\App\Http\Controllers\Onboarding\OnboardingController::class, 'form'])->name('form');
    Route::post('/formular',        [\App\Http\Controllers\Onboarding\OnboardingController::class, 'postForm'])->name('post-form');
    Route::get('/abgeschlossen',    [\App\Http\Controllers\Onboarding\OnboardingController::class, 'done'])->name('done');
});

// ── Admin: Ninox-Import ────────────────────────────────────────────────────
Route::prefix('admin/ninox-import')->name('admin.ninox-import.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',         [\App\Http\Controllers\Admin\NinoxImportController::class, 'index'])->name('index');
    Route::post('/',        [\App\Http\Controllers\Admin\NinoxImportController::class, 'run'])->name('run');
    Route::get('/{run}',    [\App\Http\Controllers\Admin\NinoxImportController::class, 'show'])->name('show');
});

// ── Admin: Onboarding-Freigabe ─────────────────────────────────────────────
Route::prefix('admin/onboarding')->name('admin.onboarding.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',                         [\App\Http\Controllers\Admin\OnboardingAdminController::class, 'index'])->name('index');
    Route::get('/{employee}',               [\App\Http\Controllers\Admin\OnboardingAdminController::class, 'show'])->name('show');
    Route::post('/{employee}/approve',      [\App\Http\Controllers\Admin\OnboardingAdminController::class, 'approve'])->name('approve');
    Route::post('/{employee}/reject',       [\App\Http\Controllers\Admin\OnboardingAdminController::class, 'reject'])->name('reject');
});

// ── Admin: Rental — Bestandsverwaltung ───────────────────────────────────────
Route::prefix('admin/rental/inventory')->name('admin.rental.inventory.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',                                     [\App\Http\Controllers\Admin\AdminRentalInventoryController::class, 'index'])->name('index');
    Route::put('/items/{item}/qty',                     [\App\Http\Controllers\Admin\AdminRentalInventoryController::class, 'updateQty'])->name('updateQty');
    Route::put('/packaging-units/{packagingUnit}/packs',[\App\Http\Controllers\Admin\AdminRentalInventoryController::class, 'updatePacks'])->name('updatePacks');
    Route::post('/items/{item}/units',                  [\App\Http\Controllers\Admin\AdminRentalInventoryController::class, 'storeUnit'])->name('storeUnit');
    Route::put('/units/{unit}',                         [\App\Http\Controllers\Admin\AdminRentalInventoryController::class, 'updateUnit'])->name('updateUnit');
    Route::delete('/units/{unit}',                      [\App\Http\Controllers\Admin\AdminRentalInventoryController::class, 'destroyUnit'])->name('destroyUnit');
});

// ── Admin: Rental — Kategorien ────────────────────────────────────────────────
Route::prefix('admin/rental/categories')->name('admin.rental.categories.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',               [\App\Http\Controllers\Admin\AdminRentalItemCategoryController::class, 'index'])->name('index');
    Route::get('/create',         [\App\Http\Controllers\Admin\AdminRentalItemCategoryController::class, 'create'])->name('create');
    Route::post('/',              [\App\Http\Controllers\Admin\AdminRentalItemCategoryController::class, 'store'])->name('store');
    Route::get('/{category}/edit',[\App\Http\Controllers\Admin\AdminRentalItemCategoryController::class, 'edit'])->name('edit');
    Route::put('/{category}',     [\App\Http\Controllers\Admin\AdminRentalItemCategoryController::class, 'update'])->name('update');
    Route::delete('/{category}',  [\App\Http\Controllers\Admin\AdminRentalItemCategoryController::class, 'destroy'])->name('destroy');
});

// ── Admin: Rental — Leihartikel ───────────────────────────────────────────────
Route::prefix('admin/rental/items')->name('admin.rental.items.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',               [\App\Http\Controllers\Admin\AdminRentalItemController::class, 'index'])->name('index');
    Route::get('/create',         [\App\Http\Controllers\Admin\AdminRentalItemController::class, 'create'])->name('create');
    Route::post('/',              [\App\Http\Controllers\Admin\AdminRentalItemController::class, 'store'])->name('store');
    Route::get('/{item}',         [\App\Http\Controllers\Admin\AdminRentalItemController::class, 'show'])->name('show');
    Route::get('/{item}/edit',    [\App\Http\Controllers\Admin\AdminRentalItemController::class, 'edit'])->name('edit');
    Route::put('/{item}',         [\App\Http\Controllers\Admin\AdminRentalItemController::class, 'update'])->name('update');
    Route::delete('/{item}',      [\App\Http\Controllers\Admin\AdminRentalItemController::class, 'destroy'])->name('destroy');
});

// ── Admin: Rental — Inventareinheiten ────────────────────────────────────────
Route::prefix('admin/rental/inventory-units')->name('admin.rental.inventory-units.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',                        [\App\Http\Controllers\Admin\AdminRentalInventoryUnitController::class, 'index'])->name('index');
    Route::get('/create',                  [\App\Http\Controllers\Admin\AdminRentalInventoryUnitController::class, 'create'])->name('create');
    Route::post('/',                       [\App\Http\Controllers\Admin\AdminRentalInventoryUnitController::class, 'store'])->name('store');
    Route::get('/{inventoryUnit}',         [\App\Http\Controllers\Admin\AdminRentalInventoryUnitController::class, 'show'])->name('show');
    Route::get('/{inventoryUnit}/edit',    [\App\Http\Controllers\Admin\AdminRentalInventoryUnitController::class, 'edit'])->name('edit');
    Route::put('/{inventoryUnit}',         [\App\Http\Controllers\Admin\AdminRentalInventoryUnitController::class, 'update'])->name('update');
    Route::delete('/{inventoryUnit}',      [\App\Http\Controllers\Admin\AdminRentalInventoryUnitController::class, 'destroy'])->name('destroy');
});

// ── Admin: Rental — Zeitmodelle ───────────────────────────────────────────────
Route::prefix('admin/rental/time-models')->name('admin.rental.time-models.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',                    [\App\Http\Controllers\Admin\AdminRentalTimeModelController::class, 'index'])->name('index');
    Route::get('/create',              [\App\Http\Controllers\Admin\AdminRentalTimeModelController::class, 'create'])->name('create');
    Route::post('/',                   [\App\Http\Controllers\Admin\AdminRentalTimeModelController::class, 'store'])->name('store');
    Route::get('/{timeModel}/edit',    [\App\Http\Controllers\Admin\AdminRentalTimeModelController::class, 'edit'])->name('edit');
    Route::put('/{timeModel}',         [\App\Http\Controllers\Admin\AdminRentalTimeModelController::class, 'update'])->name('update');
    Route::delete('/{timeModel}',      [\App\Http\Controllers\Admin\AdminRentalTimeModelController::class, 'destroy'])->name('destroy');
});

// ── Admin: Rental — Preisregeln ───────────────────────────────────────────────
Route::prefix('admin/rental/price-rules')->name('admin.rental.price-rules.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',                    [\App\Http\Controllers\Admin\AdminRentalPriceRuleController::class, 'index'])->name('index');
    Route::get('/create',              [\App\Http\Controllers\Admin\AdminRentalPriceRuleController::class, 'create'])->name('create');
    Route::post('/',                   [\App\Http\Controllers\Admin\AdminRentalPriceRuleController::class, 'store'])->name('store');
    Route::get('/{priceRule}/edit',    [\App\Http\Controllers\Admin\AdminRentalPriceRuleController::class, 'edit'])->name('edit');
    Route::put('/{priceRule}',         [\App\Http\Controllers\Admin\AdminRentalPriceRuleController::class, 'update'])->name('update');
    Route::delete('/{priceRule}',      [\App\Http\Controllers\Admin\AdminRentalPriceRuleController::class, 'destroy'])->name('destroy');
});

// ── Admin: Rental — Verpackungseinheiten ──────────────────────────────────────
Route::prefix('admin/rental/packaging-units')->name('admin.rental.packaging-units.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',                        [\App\Http\Controllers\Admin\AdminRentalPackagingUnitController::class, 'index'])->name('index');
    Route::get('/create',                  [\App\Http\Controllers\Admin\AdminRentalPackagingUnitController::class, 'create'])->name('create');
    Route::post('/',                       [\App\Http\Controllers\Admin\AdminRentalPackagingUnitController::class, 'store'])->name('store');
    Route::get('/{packagingUnit}/edit',    [\App\Http\Controllers\Admin\AdminRentalPackagingUnitController::class, 'edit'])->name('edit');
    Route::put('/{packagingUnit}',         [\App\Http\Controllers\Admin\AdminRentalPackagingUnitController::class, 'update'])->name('update');
    Route::delete('/{packagingUnit}',      [\App\Http\Controllers\Admin\AdminRentalPackagingUnitController::class, 'destroy'])->name('destroy');
});

// ── Admin: Rental — Rückgabescheine ──────────────────────────────────────────
Route::prefix('admin/rental/return-slips')->name('admin.rental.return-slips.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',                                          [\App\Http\Controllers\Admin\AdminRentalReturnSlipController::class, 'index'])->name('index');
    Route::get('/{returnSlip}',                             [\App\Http\Controllers\Admin\AdminRentalReturnSlipController::class, 'show'])->name('show');
    Route::put('/{returnSlip}/items/{itemId}/charge',       [\App\Http\Controllers\Admin\AdminRentalReturnSlipController::class, 'updateItemCharge'])->name('items.charge');
    Route::post('/{returnSlip}/mark-reviewed',              [\App\Http\Controllers\Admin\AdminRentalReturnSlipController::class, 'markReviewed'])->name('mark-reviewed');
    Route::post('/{returnSlip}/mark-charged',               [\App\Http\Controllers\Admin\AdminRentalReturnSlipController::class, 'markCharged'])->name('mark-charged');
});

// ── Admin: Rental — Lieferungsrücknahmen ─────────────────────────────────────
Route::prefix('admin/rental/delivery-returns')->name('admin.rental.delivery-returns.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',               [\App\Http\Controllers\Admin\AdminDeliveryReturnController::class, 'index'])->name('index');
    Route::get('/{deliveryReturn}',[\App\Http\Controllers\Admin\AdminDeliveryReturnController::class, 'show'])->name('show');
});

// ── Admin: Event — Veranstaltungsorte ─────────────────────────────────────────
Route::prefix('admin/event/locations')->name('admin.event.locations.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',               [\App\Http\Controllers\Admin\AdminEventLocationController::class, 'index'])->name('index');
    Route::get('/create',         [\App\Http\Controllers\Admin\AdminEventLocationController::class, 'create'])->name('create');
    Route::post('/',              [\App\Http\Controllers\Admin\AdminEventLocationController::class, 'store'])->name('store');
    Route::get('/{location}/edit',[\App\Http\Controllers\Admin\AdminEventLocationController::class, 'edit'])->name('edit');
    Route::put('/{location}',     [\App\Http\Controllers\Admin\AdminEventLocationController::class, 'update'])->name('update');
    Route::delete('/{location}',  [\App\Http\Controllers\Admin\AdminEventLocationController::class, 'destroy'])->name('destroy');
});

// ── Admin: Rental — Schadenstarife ────────────────────────────────────────────
Route::prefix('admin/rental/damage-tariffs')->name('admin.rental.damage-tariffs.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',               [\App\Http\Controllers\Admin\AdminDamageTariffController::class, 'index'])->name('index');
    Route::get('/create',         [\App\Http\Controllers\Admin\AdminDamageTariffController::class, 'create'])->name('create');
    Route::post('/',              [\App\Http\Controllers\Admin\AdminDamageTariffController::class, 'store'])->name('store');
    Route::get('/{damageTariff}/edit',  [\App\Http\Controllers\Admin\AdminDamageTariffController::class, 'edit'])->name('edit');
    Route::put('/{damageTariff}',       [\App\Http\Controllers\Admin\AdminDamageTariffController::class, 'update'])->name('update');
    Route::delete('/{damageTariff}',    [\App\Http\Controllers\Admin\AdminDamageTariffController::class, 'destroy'])->name('destroy');
});

// ── Admin: Rental — Reinigungsgebühren ───────────────────────────────────────
Route::prefix('admin/rental/cleaning-fee-rules')->name('admin.rental.cleaning-fee-rules.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',                            [\App\Http\Controllers\Admin\AdminCleaningFeeRuleController::class, 'index'])->name('index');
    Route::get('/create',                      [\App\Http\Controllers\Admin\AdminCleaningFeeRuleController::class, 'create'])->name('create');
    Route::post('/',                           [\App\Http\Controllers\Admin\AdminCleaningFeeRuleController::class, 'store'])->name('store');
    Route::get('/{cleaningFeeRule}/edit',      [\App\Http\Controllers\Admin\AdminCleaningFeeRuleController::class, 'edit'])->name('edit');
    Route::put('/{cleaningFeeRule}',           [\App\Http\Controllers\Admin\AdminCleaningFeeRuleController::class, 'update'])->name('update');
    Route::delete('/{cleaningFeeRule}',        [\App\Http\Controllers\Admin\AdminCleaningFeeRuleController::class, 'destroy'])->name('destroy');
});

// ── Admin: Rental — Pfandregeln ──────────────────────────────────────────────
Route::prefix('admin/rental/deposit-rules')->name('admin.rental.deposit-rules.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',               [\App\Http\Controllers\Admin\AdminDepositRuleController::class, 'index'])->name('index');
    Route::get('/create',         [\App\Http\Controllers\Admin\AdminDepositRuleController::class, 'create'])->name('create');
    Route::post('/',              [\App\Http\Controllers\Admin\AdminDepositRuleController::class, 'store'])->name('store');
    Route::get('/{depositRule}/edit',    [\App\Http\Controllers\Admin\AdminDepositRuleController::class, 'edit'])->name('edit');
    Route::put('/{depositRule}',         [\App\Http\Controllers\Admin\AdminDepositRuleController::class, 'update'])->name('update');
    Route::delete('/{depositRule}',      [\App\Http\Controllers\Admin\AdminDepositRuleController::class, 'destroy'])->name('destroy');
});

// ── Admin: Fahrzeuge ──────────────────────────────────────────────────────────
Route::prefix('admin/vehicles')->name('admin.vehicles.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',                                  [\App\Http\Controllers\Admin\AdminVehicleController::class, 'index'])->name('index');
    Route::get('/create',                            [\App\Http\Controllers\Admin\AdminVehicleController::class, 'create'])->name('create');
    Route::post('/',                                 [\App\Http\Controllers\Admin\AdminVehicleController::class, 'store'])->name('store');
    Route::get('/{vehicle}',                         [\App\Http\Controllers\Admin\AdminVehicleController::class, 'show'])->name('show');
    Route::get('/{vehicle}/edit',                    [\App\Http\Controllers\Admin\AdminVehicleController::class, 'edit'])->name('edit');
    Route::put('/{vehicle}',                         [\App\Http\Controllers\Admin\AdminVehicleController::class, 'update'])->name('update');
    Route::delete('/{vehicle}',                      [\App\Http\Controllers\Admin\AdminVehicleController::class, 'destroy'])->name('destroy');
    Route::post('/{vehicle}/documents',              [\App\Http\Controllers\Admin\AdminVehicleController::class, 'storeDocument'])->name('documents.store');
    Route::delete('/{vehicle}/documents/{document}', [\App\Http\Controllers\Admin\AdminVehicleController::class, 'destroyDocument'])->name('documents.destroy');
});

// ── Shop: Leihen (Rental Booking Flow) ───────────────────────────────────────
Route::prefix('leihen')->name('rental.')->group(function () {
    Route::get('/',                 [\App\Http\Controllers\Shop\RentalCatalogController::class, 'landing'])->name('landing');
    Route::post('/zeitraum',        [\App\Http\Controllers\Shop\RentalCatalogController::class, 'setDates'])->name('set-dates');
    Route::get('/katalog',          [\App\Http\Controllers\Shop\RentalCatalogController::class, 'catalog'])->name('catalog');
    Route::get('/katalog/{item}',   [\App\Http\Controllers\Shop\RentalCatalogController::class, 'item'])->name('item');

    Route::get('/warenkorb',        [\App\Http\Controllers\Shop\RentalCartController::class, 'show'])->name('cart');
    Route::post('/warenkorb',       [\App\Http\Controllers\Shop\RentalCartController::class, 'add'])->name('cart.add');
    Route::put('/warenkorb/{itemId}', [\App\Http\Controllers\Shop\RentalCartController::class, 'update'])->name('cart.update');
    Route::delete('/warenkorb/{itemId}', [\App\Http\Controllers\Shop\RentalCartController::class, 'remove'])->name('cart.remove');
    Route::post('/warenkorb/leeren', [\App\Http\Controllers\Shop\RentalCartController::class, 'clear'])->name('cart.clear');

    Route::get('/bestellung',       [\App\Http\Controllers\Shop\RentalCheckoutController::class, 'show'])->name('checkout')->middleware('auth');
    Route::post('/bestellung',      [\App\Http\Controllers\Shop\RentalCheckoutController::class, 'store'])->name('checkout.store')->middleware('auth');
    Route::get('/bestaetigung/{order}', [\App\Http\Controllers\Shop\RentalCheckoutController::class, 'success'])->name('success');
});

// ── Admin: Asset-Mängel ───────────────────────────────────────────────────────
Route::prefix('admin/assets/issues')->name('admin.assets.issues.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',             [\App\Http\Controllers\Admin\AdminAssetIssueController::class, 'index'])->name('index');
    Route::get('/create',       [\App\Http\Controllers\Admin\AdminAssetIssueController::class, 'create'])->name('create');
    Route::post('/',            [\App\Http\Controllers\Admin\AdminAssetIssueController::class, 'store'])->name('store');
    Route::get('/{issue}/edit', [\App\Http\Controllers\Admin\AdminAssetIssueController::class, 'edit'])->name('edit');
    Route::put('/{issue}',      [\App\Http\Controllers\Admin\AdminAssetIssueController::class, 'update'])->name('update');
});

// ── Admin: Systemprotokoll (Audit Log) ───────────────────────────────────────
Route::get('admin/audit-logs', [\App\Http\Controllers\Admin\AdminAuditLogController::class, 'index'])
    ->name('admin.audit-logs.index')
    ->middleware(['auth', 'admin']);

// ── Admin: Bestandsaufnahme (PROJ-38) ───────────────────────────────────────
Route::prefix('admin/bestandsaufnahme')->name('admin.bestandsaufnahme.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',       [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminBestandsaufnahmeController::class, 'index'])->name('index');
    Route::get('/create', [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminBestandsaufnahmeController::class, 'create'])->name('create');
    Route::post('/',      [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminBestandsaufnahmeController::class, 'store'])->name('store');

    // Verpackungseinheiten (vor /{bestandsaufnahme}, sonst 404)
    Route::get('/verpackungseinheiten',           [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminArtikelVerpackungseinheitController::class, 'index'])->name('verpackungseinheiten.index');
    Route::post('/verpackungseinheiten',          [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminArtikelVerpackungseinheitController::class, 'store'])->name('verpackungseinheiten.store');
    Route::patch('/verpackungseinheiten/{artikelVerpackungseinheit}', [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminArtikelVerpackungseinheitController::class, 'update'])->name('verpackungseinheiten.update');
    Route::delete('/verpackungseinheiten/{artikelVerpackungseinheit}', [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminArtikelVerpackungseinheitController::class, 'destroy'])->name('verpackungseinheiten.destroy');

    // ODS-Import (vor /{bestandsaufnahme}, sonst 404)
    Route::get('/ods-import',                              [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminOdsImportController::class, 'index'])->name('ods-import.index');
    Route::post('/ods-import/upload',                      [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminOdsImportController::class, 'upload'])->name('ods-import.upload');
    Route::get('/ods-import/{lauf}',                       [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminOdsImportController::class, 'lauf'])->name('ods-import.lauf');
    Route::post('/ods-import/konflikte/{konflikt}/aktion', [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminOdsImportController::class, 'konfliktAktion'])->name('ods-import.konflikt-aktion');
    Route::post('/ods-import/mappings',                    [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminOdsImportController::class, 'storeMapping'])->name('ods-import.store-mapping');

    // Session-spezifische Routen (Wildcard zuletzt)
    Route::get('/{bestandsaufnahme}',                    [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminBestandsaufnahmeController::class, 'show'])->name('show');
    Route::post('/{bestandsaufnahme}/position',          [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminBestandsaufnahmeController::class, 'savePosition'])->name('save-position');
    Route::post('/{bestandsaufnahme}/pause',             [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminBestandsaufnahmeController::class, 'pause'])->name('pause');
    Route::post('/{bestandsaufnahme}/close',             [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminBestandsaufnahmeController::class, 'close'])->name('close');
});

// ── Admin: Ladenhüter & MHD-Regeln ──────────────────────────────────────────
Route::prefix('admin/ladenhueter')->name('admin.ladenhueter.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',                             [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminLadenhueterController::class, 'index'])->name('index');
    Route::get('/regeln',                       [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminLadenhueterController::class, 'regeln'])->name('regeln');
    Route::post('/regeln/ladenhueter',          [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminLadenhueterController::class, 'updateLadenhueterRegel'])->name('update-regel');
    Route::post('/regeln/mhd',                  [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminLadenhueterController::class, 'storeMhdRegel'])->name('store-mhd-regel');
    Route::delete('/regeln/mhd/{mhdRegel}',     [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminLadenhueterController::class, 'destroyMhdRegel'])->name('destroy-mhd-regel');
    Route::post('/{product}/status',            [\App\Http\Controllers\Admin\Bestandsaufnahme\AdminLadenhueterController::class, 'setStatus'])->name('set-status');
});

// ── Admin: Primeur-Archiv (IT-Drink-Altdaten) ────────────────────────────────
Route::prefix('admin/primeur-archiv')->name('admin.primeur.')->middleware(['auth', 'admin'])->group(function (): void {
    Route::get('/',                [\App\Http\Controllers\Admin\Primeur\PrimeurDashboardController::class, '__invoke'])->name('dashboard');

    // Kunden
    Route::get('/kunden',          [\App\Http\Controllers\Admin\Primeur\PrimeurCustomerController::class, 'index'])->name('customers.index');
    Route::get('/kunden/{id}',     [\App\Http\Controllers\Admin\Primeur\PrimeurCustomerController::class, 'show'])->name('customers.show');

    // Aufträge
    Route::get('/auftraege',       [\App\Http\Controllers\Admin\Primeur\PrimeurOrderController::class, 'index'])->name('orders.index');
    Route::get('/auftraege/{id}',  [\App\Http\Controllers\Admin\Primeur\PrimeurOrderController::class, 'show'])->name('orders.show');

    // Kasse
    Route::get('/kasse/tage',      [\App\Http\Controllers\Admin\Primeur\PrimeurCashController::class, 'daily'])->name('cash.daily');
    Route::get('/kasse/belege',    [\App\Http\Controllers\Admin\Primeur\PrimeurCashController::class, 'receipts'])->name('cash.receipts');
    Route::get('/kasse/monate',    [\App\Http\Controllers\Admin\Primeur\PrimeurCashController::class, 'monthly'])->name('cash.monthly');
    Route::get('/kasse/export/monate', [\App\Http\Controllers\Admin\Primeur\PrimeurCashController::class, 'exportMonthly'])->name('cash.export.monthly');
    Route::get('/kasse/export/tage',   [\App\Http\Controllers\Admin\Primeur\PrimeurCashController::class, 'exportDaily'])->name('cash.export.daily');

    // Artikel-Übersicht & Detail
    Route::get('/artikel',                        [\App\Http\Controllers\Admin\Primeur\PrimeurStatsController::class,  'articlesList'])->name('articles.index');
    Route::get('/artikel/{id}',                   [\App\Http\Controllers\Admin\Primeur\PrimeurArticleController::class, 'show'])->name('articles.show')->where('id', '[0-9]+');
    Route::get('/warengruppe/{name}',             [\App\Http\Controllers\Admin\Primeur\PrimeurArticleController::class, 'warengruppe'])->name('articles.warengruppe');
    Route::get('/warengruppe/{wg}/ug/{ug}',       [\App\Http\Controllers\Admin\Primeur\PrimeurArticleController::class, 'untergruppe'])->name('articles.untergruppe');
    Route::get('/hersteller/{name}',              [\App\Http\Controllers\Admin\Primeur\PrimeurArticleController::class, 'hersteller'])->name('articles.hersteller');

    // Statistiken
    Route::get('/statistik/kunden',    [\App\Http\Controllers\Admin\Primeur\PrimeurStatsController::class, 'customers'])->name('stats.customers');
    Route::get('/statistik/umsatz',    [\App\Http\Controllers\Admin\Primeur\PrimeurStatsController::class, 'revenue'])->name('stats.revenue');
    Route::get('/statistik/artikel',   [\App\Http\Controllers\Admin\Primeur\PrimeurStatsController::class, 'articles'])->name('stats.articles');
    Route::get('/statistik/kunden/export', [\App\Http\Controllers\Admin\Primeur\PrimeurStatsController::class, 'exportCustomers'])->name('stats.customers.export');
});
