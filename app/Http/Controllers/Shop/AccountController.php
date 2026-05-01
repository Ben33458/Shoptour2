<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Admin\Invoice;
use App\Models\Admin\LexofficePayment;
use App\Models\Admin\LexofficeVoucher;
use App\Models\Orders\Order;
use App\Models\User;
use App\Services\CustomerActivationService;
use App\Services\Integrations\LexofficeVoucherPdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * WP-21 – Customer account self-service area.
 *
 * All routes require auth middleware (set in routes/web.php).
 * Admin/Mitarbeiter users are redirected to the admin area.
 */
class AccountController extends Controller
{
    public function __construct(
        private readonly LexofficeVoucherPdfService $voucherPdf,
    ) {}
    // -------------------------------------------------------------------------
    // Dashboard
    // -------------------------------------------------------------------------

    public function index(): View|RedirectResponse
    {
        $customer = $this->requireCustomer();
        $customer->load(['customerGroup', 'orders' => fn ($q) => $q->latest()->limit(5)->with('items')]);

        return view('shop.account.dashboard', compact('customer'));
    }

    // -------------------------------------------------------------------------
    // Orders
    // -------------------------------------------------------------------------

    public function orders(): View
    {
        $customer = $this->requireCustomer();
        $orders   = $customer->orders()->latest()->with('items.product')->paginate(20);

        return view('shop.account.orders', compact('customer', 'orders'));
    }

    public function orderDetail(Order $order): View
    {
        $customer = $this->requireCustomer();
        if ($order->customer_id !== $customer->id) {
            abort(403);
        }
        $order->load(['items.product', 'items.product.mainImage']);

        return view('shop.account.order-detail', compact('customer', 'order'));
    }

    // -------------------------------------------------------------------------
    // Invoices (Lexoffice)
    // -------------------------------------------------------------------------

    public function invoices(Request $request): View
    {
        $customer = $this->requireCustomer();

        // Auto-complete onboarding when last tour step is reached
        if ($request->query('onboarding_step') === 'rechnungen') {
            app(CustomerActivationService::class)->completeOnboarding($customer);
            $customer->refresh();
        }

        if (! $customer->lexoffice_contact_id) {
            // During onboarding tour show empty invoice page instead of 404
            if ($request->query('onboarding_step') === 'rechnungen') {
                return view('shop.account.invoices', [
                    'customer'      => $customer,
                    'vouchers'      => collect(),
                    'payments'      => collect(),
                    'localInvoices' => collect(),
                    'saldo'         => 0,
                ]);
            }
            abort(404);
        }

        $baseQuery = LexofficeVoucher::where(function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)
                  ->orWhere('lexoffice_contact_id', $customer->lexoffice_contact_id);
            })
            ->whereIn('voucher_type', ['salesinvoice', 'salescreditnote', 'downpaymentinvoice'])
            ->whereNotIn('voucher_status', ['voided', 'draft'])
            ->orderByDesc('voucher_date');

        // Saldo across ALL vouchers (not just current page)
        $saldo = (clone $baseQuery)
            ->whereIn('voucher_status', ['open', 'overdue'])
            ->sum('open_amount');

        // Paginated vouchers
        $vouchers = $baseQuery->paginate(15)->withQueryString();

        $uuids = $vouchers->pluck('lexoffice_voucher_id')->all();

        // Payments grouped by voucher UUID (only for current page)
        $payments = $uuids
            ? LexofficePayment::whereIn('lexoffice_voucher_id', $uuids)
                ->orderBy('payment_date')
                ->get()
                ->groupBy('lexoffice_voucher_id')
            : collect();

        // Local invoices linked to current page vouchers
        $localInvoices = $uuids
            ? Invoice::whereIn('lexoffice_voucher_id', $uuids)
                ->get()
                ->keyBy('lexoffice_voucher_id')
            : collect();

        return view('shop.account.invoices', compact('customer', 'vouchers', 'payments', 'localInvoices', 'saldo'));
    }

    // -------------------------------------------------------------------------
    // Profile
    // -------------------------------------------------------------------------

    public function showProfile(): View
    {
        $customer = $this->requireCustomer();

        return view('shop.account.profile', compact('customer'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $customer = $this->requireCustomer();

        $validated = $request->validate([
            'first_name'                  => ['nullable', 'string', 'max:100'],
            'last_name'                   => ['nullable', 'string', 'max:100'],
            'company_name'                => ['nullable', 'string', 'max:200'],
            'phone'                       => ['nullable', 'string', 'max:50'],
            'birth_date'                  => ['nullable', 'date', 'before_or_equal:today'],
            'email'                       => ['nullable', 'email', 'max:200'],
            'billing_email'               => ['nullable', 'email', 'max:200'],
            'notification_email'          => ['nullable', 'email', 'max:200'],
            'newsletter_consent'          => ['required', 'in:all,important_only,none'],
            'price_display_mode'          => ['required', 'in:brutto,netto'],
        ]);

        $validated['email_notification_shipping'] = $request->boolean('email_notification_shipping');

        $customer->update($validated);

        // Keep User name in sync with Customer
        /** @var User $user */
        $user = Auth::user();
        $user->update([
            'first_name' => $validated['first_name'] ?? $user->first_name,
            'last_name'  => $validated['last_name']  ?? $user->last_name,
        ]);

        $params = array_filter(['onboarding_step' => $request->input('onboarding_step')]);
        return redirect()->route('account.profile', $params)->with('success', 'Einstellungen gespeichert.');
    }

    public function updateDisplayPreferences(Request $request): RedirectResponse
    {
        $availableViews = json_decode(
            \App\Models\Pricing\AppSetting::get('shop.display.available_views', '["grid_large","grid_compact","list_images","list_no_images","table"]'),
            true
        );

        $validated = $request->validate([
            'view_mode'      => ['required', 'string', 'in:' . implode(',', $availableViews)],
            'items_per_page' => ['required', 'integer', 'in:24,48,96'],
        ]);

        $customer = $this->requireCustomer();
        $prefs    = $customer->display_preferences ?? [];

        $customer->update([
            'display_preferences' => array_merge($prefs, [
                'view_mode'      => $validated['view_mode'],
                'items_per_page' => (int) $validated['items_per_page'],
            ]),
        ]);

        $params = array_filter(['onboarding_step' => $request->input('onboarding_step')]);
        return redirect()->route('account.profile', $params)->with('success', 'Ansicht gespeichert.');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password'      => ['required', 'string'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Das aktuelle Passwort ist falsch.'])->withFragment('passwort');
        }

        $user->update(['password' => $request->password]);

        $params = array_filter(['onboarding_step' => $request->input('onboarding_step')]);
        return redirect()->route('account.profile', $params)
            ->with('success', 'Passwort wurde geändert.')
            ->withFragment('passwort');
    }

    // -------------------------------------------------------------------------
    // Invoice download (PROJ-20)
    // -------------------------------------------------------------------------

    public function downloadInvoice(Invoice $invoice): StreamedResponse
    {
        $customer = $this->requireCustomer();

        // Verify this invoice belongs to the customer (via the linked order)
        $invoice->loadMissing('order');
        if (! $invoice->order || $invoice->order->customer_id !== $customer->id) {
            abort(403);
        }

        if (! $invoice->pdf_path || ! Storage::exists($invoice->pdf_path)) {
            abort(404, 'PDF nicht verfügbar.');
        }

        $filename = 'Rechnung-' . ($invoice->invoice_number ?? $invoice->id) . '.pdf';

        return Storage::download($invoice->pdf_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * GET /mein-konto/rechnungen/lexoffice/{voucher}/download
     * Download a Lexoffice invoice PDF (fetched on demand, customer-ownership check).
     */
    public function downloadVoucherPdf(LexofficeVoucher $voucher): Response
    {
        $customer = $this->requireCustomer();

        $ownedById      = (int) $voucher->customer_id === $customer->id;
        $ownedByContact = $customer->lexoffice_contact_id
            && $voucher->lexoffice_contact_id === $customer->lexoffice_contact_id;

        if (! $ownedById && ! $ownedByContact) {
            abort(403);
        }

        try {
            $relative = $this->voucherPdf->getOrFetch($voucher);
            $content  = Storage::disk('local')->get($relative);
            $filename = ($voucher->voucher_number ?? 'Rechnung') . '.pdf';

            return response($content, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control'       => 'private, max-age=3600',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('VoucherPdf download failed', [
                'voucher_id' => $voucher->id,
                'error'      => $e->getMessage(),
            ]);
            abort(500, 'PDF konnte nicht geladen werden.');
        }
    }

    // -------------------------------------------------------------------------
    // Addresses
    // -------------------------------------------------------------------------

    public function addresses(): View
    {
        $customer = $this->requireCustomer();
        $customer->load(['addresses' => fn ($q) => $q->orderBy('type')->orderByDesc('is_default')->orderBy('id')]);

        return view('shop.account.addresses', compact('customer'));
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $customer  = $this->requireCustomer();
        $validated = $this->validateAddress($request);

        if ($validated['is_default'] ?? false) {
            // Un-set existing default for this type
            $customer->addresses()
                ->where('type', $validated['type'])
                ->update(['is_default' => false]);
        }

        $customer->addresses()->create(array_merge(['is_default' => false], $validated));

        $params = array_filter(['onboarding_step' => $request->input('onboarding_step')]);
        return redirect()->route('account.addresses', $params)->with('success', 'Adresse gespeichert.');
    }

    public function updateAddress(Request $request, Address $address): RedirectResponse
    {
        $customer = $this->requireCustomer();
        if ($address->customer_id !== $customer->id) {
            abort(403);
        }

        $validated = $this->validateAddress($request);

        if ($validated['is_default'] ?? false) {
            $customer->addresses()
                ->where('type', $validated['type'])
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        $address->update(array_merge(['is_default' => false], $validated));

        $params = array_filter(['onboarding_step' => $request->input('onboarding_step')]);
        return redirect()->route('account.addresses', $params)->with('success', 'Adresse aktualisiert.');
    }

    public function destroyAddress(Request $request, Address $address): RedirectResponse
    {
        $customer = $this->requireCustomer();
        if ($address->customer_id !== $customer->id) {
            abort(403);
        }
        $address->delete();

        $params = array_filter(['onboarding_step' => $request->input('onboarding_step')]);
        return redirect()->route('account.addresses', $params)->with('success', 'Adresse gelöscht.');
    }

    public function setDefaultAddress(Request $request, Address $address): RedirectResponse
    {
        $customer = $this->requireCustomer();
        if ($address->customer_id !== $customer->id) {
            abort(403);
        }

        // Un-set old defaults for this type
        $customer->addresses()
            ->where('type', $address->type)
            ->update(['is_default' => false]);

        $address->update(['is_default' => true]);

        $params = array_filter(['onboarding_step' => $request->input('onboarding_step')]);
        return redirect()->route('account.addresses', $params)->with('success', 'Standard-Adresse gesetzt.');
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    /**
     * Load the current user's Customer record.
     * Supports both main customers (role=kunde) and sub-users (role=sub_user).
     * Aborts 403 if not found or sub-user is inactive.
     */
    private function requireCustomer(): \App\Models\Pricing\Customer
    {
        /** @var User $user */
        $user = Auth::user();

        // Sub-user: resolve to parent customer
        if ($user->isSubUser()) {
            $subUser = $user->subUser;
            if (! $subUser?->active) {
                abort(403, 'Ihr Zugang wurde deaktiviert.');
            }
            $customer = $subUser->parentCustomer;
            if (! $customer) {
                abort(403, 'Kein Kundenkonto vorhanden.');
            }
            return $customer;
        }

        $customer = $user->customer;

        if ($customer === null) {
            abort(403, 'Kein Kundenkonto vorhanden.');
        }

        return $customer;
    }

    /**
     * Validate and return address fields from the request.
     *
     * @return array<string, mixed>
     */
    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'type'         => ['required', 'in:delivery,billing'],
            'is_default'   => ['sometimes', 'boolean'],
            'label'        => ['nullable', 'string', 'max:100'],
            'first_name'   => ['nullable', 'string', 'max:100'],
            'last_name'    => ['nullable', 'string', 'max:100'],
            'company'      => ['nullable', 'string', 'max:200'],
            'street'       => ['required', 'string', 'max:200'],
            'house_number' => ['nullable', 'string', 'max:20'],
            'zip'          => ['required', 'string', 'max:10'],
            'city'         => ['required', 'string', 'max:100'],
            'country_code'  => ['sometimes', 'string', 'size:2'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'delivery_note' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
