<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\StoreCheckoutRequest;
use App\Models\Address;
use App\Models\Admin\DeferredTask;
use App\Models\Delivery\RegularDeliveryTour;
use App\Models\Employee\PublicHoliday;
use App\Models\Event\EventLocation;
use App\Models\Inventory\Warehouse;
use App\Models\Orders\Order;
use App\Models\Pricing\Customer;
use App\Models\User;
use App\Services\Catalog\JugendschutzService;
use App\Services\Orders\OrderNumberService;
use App\Services\Orders\OrderService;
use App\Services\Payments\IbanValidator;
use App\Services\Payments\SepaMandateService;
use App\Services\Payments\ShopPayPalService;
use App\Services\Payments\ShopStripeService;
use App\Services\Rental\RentalBookingService;
use App\Services\Rental\RentalCartService;
use App\Services\Shop\CartService;
use App\Services\Shop\LeergutCartService;
use App\Services\Shop\TourAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * PROJ-4: Multi-step checkout wizard.
 *
 * Middleware: auth (set in routes)
 * Only users with role=kunde and an associated Customer record may checkout.
 *
 * Routes:
 *   GET  /kasse                              -> index()   checkout wizard
 *   POST /kasse                              -> store()   place order
 *   GET  /bestellung/{order}/abgeschlossen   -> success() thank-you page
 *   GET  /kasse/paypal/success               -> paypalSuccess()
 *   GET  /kasse/paypal/cancel                -> paypalCancel()
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService           $cart,
        private readonly OrderService          $orderService,
        private readonly OrderNumberService    $orderNumberService,
        private readonly TourAssignmentService $tourAssignmentService,
        private readonly RentalCartService     $rentalCart,
        private readonly LeergutCartService    $leergutCart,
        private readonly SepaMandateService    $sepaMandateService,
        private readonly IbanValidator         $ibanValidator,
    ) {}

    /**
     * GET /kasse -- show the checkout wizard.
     */
    public function index(): View|RedirectResponse
    {
        $customer = $this->requireCustomer();
        if ($customer === null) {
            return redirect()->route('shop.index')->withErrors('Kein Kundenkonto gefunden.');
        }

        /** @var User $user */
        $user = Auth::user();

        // BUG-10 fix: clean up orphaned pending Stripe orders when user returns with cancel.
        if (request()->query('stripe') === 'cancelled') {
            Order::where('customer_id', $customer->id)
                ->where('status', Order::STATUS_PENDING)
                ->where('payment_method', Order::PAY_STRIPE)
                ->update(['status' => Order::STATUS_CANCELLED]);
        }

        $rentalSummary  = $this->rentalCart->getItemsSummary();
        $hasRentalItems = $rentalSummary->isNotEmpty();

        if ($this->cart->isEmpty($user) && ! $hasRentalItems) {
            return redirect()->route('cart.index')->with('info', 'Dein Warenkorb ist leer.');
        }

        // Load cart with full pricing data (may be empty for rental-only orders)
        $cartData = $this->cart->isEmpty($user) ? [
            'items'               => [],
            'subtotal_net_milli'  => 0,
            'subtotal_gross_milli'=> 0,
            'pfand_total_milli'   => 0,
            'tax_breakdown'       => [],
            'total_milli'         => 0,
            'has_unavailable'     => false,
        ] : $this->cart->calculate($user);

        // Block checkout if any item is unavailable
        if ($cartData['has_unavailable']) {
            return redirect()->route('cart.index')
                ->withErrors('Einige Produkte in deinem Warenkorb sind nicht mehr verfuegbar. Bitte entferne sie.');
        }

        // Load customer data
        $customer->load(['customerGroup', 'deliveryAddresses', 'billingAddresses']);

        // Determine allowed payment methods for customer's group
        $allowedPaymentMethods = $customer->customerGroup->getEffectivePaymentMethods();

        // Load pickup locations
        $pickupLocations = Warehouse::where('is_pickup_location', true)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $pickupLocationsForJs = $pickupLocations
            ->load('openingHours')
            ->map(fn ($w) => [
                'id'            => $w->id,
                'name'          => $w->name,
                'opening_hours' => $w->openingHours->mapWithKeys(
                    fn ($h) => [$h->day_of_week => [
                        'from' => substr($h->open_from, 0, 5),
                        'to'   => substr($h->open_to,   0, 5),
                    ]]
                ),
            ]);

        $holidayDates = PublicHoliday::whereBetween('date', [today(), today()->addDays(90)])
            ->pluck('date')
            ->map(fn ($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
            ->values()
            ->toArray();

        // Resolve tours for the customer's default delivery address
        $tours = collect();
        $customerTourId = null;
        $defaultAddress = $customer->deliveryAddresses->first(fn ($a) => $a->is_default)
            ?? $customer->deliveryAddresses->first();

        if ($defaultAddress) {
            // BUG-5 fix: pass customer group ID to filter tours by group.
            $tours = $this->tourAssignmentService->resolveTours(
                $defaultAddress->zip,
                $defaultAddress->city,
                $customer->customer_group_id,
            );
        }

        $nextTourDates = $tours->mapWithKeys(
            fn (RegularDeliveryTour $t) => [
                $t->id => $this->tourAssignmentService->nextTourDate($t)?->format('Y-m-d'),
            ]
        );

        // All active tours with delivery areas — used for client-side tour lookup
        // when the customer enters a new address.
        $allToursForJs = RegularDeliveryTour::where('active', true)
            ->with('deliveryAreas')
            ->get()
            ->map(fn (RegularDeliveryTour $t) => [
                'id'        => $t->id,
                'name'      => $t->name,
                'day_de'    => $t->dayOfWeekDe(),
                'freq_de'   => $t->frequencyDe(),
                'next_date' => $this->tourAssignmentService->nextTourDate($t)?->format('Y-m-d'),
                'min_order' => $t->min_order_value_milli,
                'areas'     => $t->deliveryAreas
                    ->map(fn ($a) => [
                        'postal_code' => $a->postal_code,
                        'city_name'   => mb_strtolower($a->city_name ?? ''),
                    ])
                    ->values()
                    ->toArray(),
            ]);

        // Check if customer already has a regular_delivery_tour_id
        // (from a previous order or admin assignment)
        $existingTour = $customer->orders()
            ->whereNotNull('regular_delivery_tour_id')
            ->latest()
            ->first();
        if ($existingTour) {
            $customerTourId = $existingTour->regular_delivery_tour_id;
        }

        $rentalFrom     = $this->rentalCart->getDateFrom();
        $rentalUntil    = $this->rentalCart->getDateUntil();
        $rentalTotal    = $this->rentalCart->totalNetMilli();

        $minAge = JugendschutzService::cartMinAge($cartData['items']);

        return view('shop.checkout', [
            'customer'              => $customer,
            'cartData'              => $cartData,
            'minAge'                => $minAge,
            'allowedPaymentMethods' => $allowedPaymentMethods,
            'pickupLocations'       => $pickupLocations,
            'pickupLocationsForJs'  => $pickupLocationsForJs,
            'holidayDates'          => $holidayDates,
            'tours'                 => $tours,
            'nextTourDates'         => $nextTourDates,
            'allToursForJs'         => $allToursForJs,
            'customerTourId'        => $customerTourId,
            'defaultAddress'        => $defaultAddress,
            'hasRentalItems'        => $hasRentalItems,
            'hasProducts'           => ! empty($cartData['items']),
            'eventLocations'        => EventLocation::where('active', true)->orderBy('name')->get(),
            'rentalSummary'         => $rentalSummary,
            'rentalFrom'            => $rentalFrom,
            'rentalUntil'           => $rentalUntil,
            'rentalTotal'           => $rentalTotal,
            'leergutTotal'          => $this->leergutCart->getCreditTotal(),
            'sepaMandateInfo'       => $this->sepaMandateService->findForCustomer($customer),
        ]);
    }

    /**
     * POST /kasse -- process checkout, create order.
     */
    public function store(StoreCheckoutRequest $request): RedirectResponse
    {
        $customer = $this->requireCustomer();
        if ($customer === null) {
            return redirect()->route('shop.index');
        }

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validated();

        // Capture rental cart state before anything modifies session
        $rentalCartData = $this->rentalCart->get();
        $hasRental      = ! $this->rentalCart->isEmpty();

        if ($this->cart->isEmpty($user) && ! $hasRental) {
            return redirect()->route('cart.index')->with('info', 'Dein Warenkorb ist leer.');
        }
        $eventData      = $hasRental ? array_intersect_key($validated, array_flip([
            'event_location_name', 'event_location_street', 'event_location_zip',
            'event_location_city', 'event_contact_name', 'event_contact_phone',
            'event_delivery_mode', 'event_pickup_mode',
            'event_access_notes', 'event_setup_notes',
            'event_has_power', 'event_suitable_ground',
        ])) : [];

        $customer->load('customerGroup');

        // Validate payment method is allowed for customer's group
        $allowedMethods = $customer->customerGroup->getEffectivePaymentMethods();
        if (! in_array($validated['payment_method'], $allowedMethods, true)) {
            return redirect()->route('checkout')
                ->withErrors('Die gewaehlte Zahlungsmethode ist fuer deine Kundengruppe nicht verfuegbar.');
        }

        // Build items array from cart
        $lines = $this->cart->isEmpty($user) ? [] : $this->cart->items($user);
        $items = [];
        foreach ($lines as $line) {
            $items[] = [
                'product' => $line['product'],
                'qty'     => $line['qty'],
            ];
        }

        // Resolve or create delivery address
        $deliveryAddressId = null;
        if ($validated['delivery_type'] === Order::DELIVERY_HOME) {
            $deliveryAddressId = $this->resolveDeliveryAddress($validated, $customer);
        }

        // If rental order but no explicit event location address given,
        // fall back to the delivery address so the booking has location data.
        if ($hasRental && empty($eventData['event_location_name']) && $deliveryAddressId) {
            $addr = Address::find($deliveryAddressId);
            if ($addr) {
                $eventData['event_location_name']   = $addr->company ?: trim(($addr->first_name ?? '') . ' ' . ($addr->last_name ?? ''));
                $eventData['event_location_street']  = trim(($addr->street ?? '') . ' ' . ($addr->house_number ?? ''));
                $eventData['event_location_zip']     = $addr->zip ?? '';
                $eventData['event_location_city']    = $addr->city ?? '';
            }
        }

        // Resolve tour
        $tourId = $validated['tour_id'] ?? null;

        // BUG-11 fix: enforce minimum order value per tour.
        if ($tourId !== null) {
            $tour = RegularDeliveryTour::find((int) $tourId);
            if ($tour && $tour->min_order_value_milli > 0) {
                $cartData = $this->cart->calculate($user);
                if ($cartData['total_milli'] < $tour->min_order_value_milli) {
                    $minFormatted = number_format($tour->min_order_value_milli / 1_000_000, 2, ',', '.') . ' €';
                    return redirect()->route('checkout')
                        ->withErrors("Der Mindestbestellwert fuer diese Tour betraegt {$minFormatted}. Bitte fuege weitere Artikel hinzu.");
                }
            }
        }

        try {
            $order = DB::transaction(function () use (
                $customer, $items, $validated, $deliveryAddressId, $tourId,
                $hasRental, $rentalCartData, $eventData
            ): Order {
                // 1. Generate order number
                $orderNumber = $this->orderNumberService->generate();

                if (! empty($items)) {
                    // 2a. Standard order: create via OrderService (handles pricing snapshots)
                    $order = $this->orderService->createOrder(
                        customer:     $customer,
                        items:        $items,
                        deliveryDate: $validated['delivery_date']
                            ? \Carbon\Carbon::parse($validated['delivery_date'])
                            : null,
                    );

                    $updateData = [
                        'order_number'             => $orderNumber,
                        'delivery_type'            => $validated['delivery_type'],
                        'payment_method'           => $validated['payment_method'],
                        'customer_notes'           => $validated['customer_notes'] ?? null,
                        'delivery_address_id'      => $deliveryAddressId,
                        'regular_delivery_tour_id' => $tourId,
                    ];

                    if ($validated['delivery_type'] === Order::DELIVERY_PICKUP) {
                        $updateData['pickup_location_id'] = $validated['pickup_warehouse_id'];
                        $updateData['delivery_date']      = isset($validated['pickup_date'])
                            ? \Carbon\Carbon::parse($validated['pickup_date'])
                            : null;
                        $updateData['pickup_time_from']   = $validated['pickup_time_from'] ?? null;
                        $updateData['pickup_time_to']     = $validated['pickup_time_to'] ?? null;
                    }

                    $order->update($updateData);
                } else {
                    // 2b. Rental-only order: create a minimal order without product items
                    $order = Order::create([
                        'company_id'                  => $customer->company_id ?? 1,
                        'order_number'               => $orderNumber,
                        'customer_id'                => $customer->id,
                        'customer_group_id_snapshot' => $customer->customer_group_id,
                        'status'                     => Order::STATUS_PENDING,
                        'delivery_type'              => $validated['delivery_type'],
                        'payment_method'             => $validated['payment_method'],
                        'customer_notes'             => $validated['customer_notes'] ?? null,
                        'delivery_address_id'        => $deliveryAddressId,
                        'regular_delivery_tour_id'   => $tourId,
                        'total_net_milli'            => 0,
                        'total_gross_milli'          => 0,
                    ]);
                }

                // 3. Attach rental items if present (within same transaction)
                if ($hasRental) {
                    app(RentalBookingService::class)
                        ->attachToOrder($order, $customer, $rentalCartData, $eventData);
                }

                return $order;
            });
        } catch (\Throwable $e) {
            Log::error('Checkout failed', [
                'customer_id' => $customer->id,
                'error'       => $e->getMessage(),
            ]);

            return redirect()->route('checkout')
                ->withErrors('Beim Erstellen deiner Bestellung ist ein Fehler aufgetreten. Bitte versuche es erneut.');
        }

        // For redirect-based payments (Stripe, PayPal) the user may cancel — keep the
        // cart intact until payment is confirmed. Clear only for immediate methods.
        if (! in_array($validated['payment_method'], [Order::PAY_STRIPE, Order::PAY_PAYPAL])) {
            $this->cart->clear($user);
            if ($hasRental) {
                $this->rentalCart->clear();
            }
        }

        // BUG-6 fix: persist chosen tour as customer's Stamm-Tour for future orders.
        if ($tourId && $customer->regular_delivery_tour_id === null) {
            $customer->update(['regular_delivery_tour_id' => $tourId]);
        }

        // Save SEPA mandate when customer enters a new one
        if ($validated['payment_method'] === Order::PAY_SEPA
            && ! filter_var($validated['sepa_use_existing'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && ! empty($validated['sepa_iban'])
        ) {
            $iban = $this->ibanValidator->normalize($validated['sepa_iban']);
            $this->sepaMandateService->store($customer, $iban, $validated['sepa_account_holder'] ?? '');
        }

        // Handle payment method
        return $this->handlePayment($order, $validated['payment_method']);
    }

    /**
     * GET /bestellung/{order}/abgeschlossen -- thank-you page.
     */
    public function success(Order $order): View|RedirectResponse
    {
        $customer = $this->requireCustomer();
        if ($customer === null || $order->customer_id !== $customer->id) {
            abort(403);
        }

        $order->load(['items.product', 'deliveryAddress', 'pickupLocation', 'regularDeliveryTour']);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user) {
            $this->cart->clear($user);
            $this->rentalCart->clear();
        }

        return view('shop.checkout-success', compact('order'));
    }

    /**
     * GET /kasse/paypal/success -- PayPal approved, capture payment.
     */
    public function paypalSuccess(): RedirectResponse
    {
        $token = request()->query('token');
        if (! $token) {
            return redirect()->route('checkout')->withErrors('PayPal-Zahlung fehlgeschlagen.');
        }

        // Find order by payment_reference
        $order = Order::where('payment_reference', 'paypal:' . $token)->first();
        if (! $order) {
            return redirect()->route('checkout')->withErrors('Bestellung nicht gefunden.');
        }

        // Verify ownership
        $customer = $this->requireCustomer();
        if ($customer === null || $order->customer_id !== $customer->id) {
            abort(403);
        }

        // Capture the PayPal payment
        $paypal = app(ShopPayPalService::class);
        $captured = $paypal->captureOrder($token);

        if (! $captured) {
            $order->update(['status' => Order::STATUS_CANCELLED]);

            return redirect()->route('checkout')
                ->withErrors('PayPal-Zahlung konnte nicht abgeschlossen werden.');
        }

        // Confirm the order
        $order->update(['status' => Order::STATUS_CONFIRMED]);
        $this->queueConfirmationEmail($order);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user) {
            $this->cart->clear($user);
            $this->rentalCart->clear();
        }

        return redirect()->route('checkout.success', $order);
    }

    /**
     * GET /kasse/paypal/cancel -- PayPal cancelled by user.
     */
    public function paypalCancel(): RedirectResponse
    {
        $token = request()->query('token');

        if ($token) {
            $order = Order::where('payment_reference', 'paypal:' . $token)->first();

            // BUG-12 fix: verify ownership before cancelling — prevents IDOR.
            if ($order && $order->status === Order::STATUS_PENDING) {
                $customer = $this->requireCustomer();
                if ($customer && $order->customer_id === $customer->id) {
                    $order->update(['status' => Order::STATUS_CANCELLED]);
                }
            }
        }

        return redirect()->route('checkout')
            ->with('info', 'PayPal-Zahlung wurde abgebrochen. Du kannst es erneut versuchen.');
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve or create the delivery address, applying drop-off fields.
     */
    private function resolveDeliveryAddress(array $validated, Customer $customer): ?int
    {
        $addressId = $validated['delivery_address_id'] ?? null;

        // EventLocation selected as delivery address
        if ($addressId === 'event_location') {
            $locId = (int) ($validated['event_location_delivery_id'] ?? 0);
            $loc   = EventLocation::find($locId);
            if (! $loc) {
                return null;
            }
            $address = Address::create([
                'customer_id'  => $customer->id,
                'company_id'   => $customer->company_id,
                'type'         => 'delivery',
                'is_default'   => false,
                'company'      => $loc->name,
                'street'       => $loc->street ?? '',
                'zip'          => $loc->zip ?? '',
                'city'         => $loc->city ?? '',
            ]);
            return $address->id;
        }

        if ($addressId === 'new' || $addressId === null) {
            // Create new address from inline form
            $newAddr = $validated['new_address'] ?? [];
            if (empty($newAddr['street']) || empty($newAddr['zip']) || empty($newAddr['city'])) {
                return null;
            }

            $address = Address::create([
                'customer_id'  => $customer->id,
                'company_id'   => $customer->company_id,  // BUG-14 fix
                'type'         => 'delivery',
                'is_default'   => $customer->deliveryAddresses()->count() === 0,
                'first_name'   => $newAddr['first_name'] ?? null,
                'last_name'    => $newAddr['last_name'] ?? null,
                'company'      => $newAddr['company'] ?? null,
                'street'       => $newAddr['street'],
                'house_number' => $newAddr['house_number'] ?? null,
                'zip'          => $newAddr['zip'],
                'city'         => $newAddr['city'],
                'phone'        => $newAddr['phone'] ?? null,
                'drop_off_location'        => $validated['drop_off_location'] ?? null,
                'drop_off_location_custom' => $validated['drop_off_location_custom'] ?? null,
                'leave_at_door'            => (bool) ($validated['leave_at_door'] ?? false),
            ]);

            return $address->id;
        }

        // Verify existing address belongs to customer
        $address = Address::where('id', (int) $addressId)
            ->where('customer_id', $customer->id)
            ->first();

        if (! $address) {
            return null;
        }

        // Update drop-off fields on existing address if provided
        $address->update([
            'drop_off_location'        => $validated['drop_off_location'] ?? $address->drop_off_location,
            'drop_off_location_custom' => $validated['drop_off_location_custom'] ?? $address->drop_off_location_custom,
            'leave_at_door'            => isset($validated['leave_at_door'])
                ? (bool) $validated['leave_at_door']
                : $address->leave_at_door,
        ]);

        return $address->id;
    }

    /**
     * Handle payment based on payment method.
     */
    private function handlePayment(Order $order, string $paymentMethod): RedirectResponse
    {
        switch ($paymentMethod) {
            case Order::PAY_STRIPE:
                return $this->handleStripePayment($order);

            case Order::PAY_PAYPAL:
                return $this->handlePayPalPayment($order);

            default:
                // invoice, sepa, cash, ec -- confirm immediately
                $order->update(['status' => Order::STATUS_CONFIRMED]);
                $this->queueConfirmationEmail($order);

                return redirect()->route('checkout.success', $order);
        }
    }

    /**
     * Create Stripe Checkout Session and redirect.
     */
    private function handleStripePayment(Order $order): RedirectResponse
    {
        try {
            /** @var User $user */
            $user         = Auth::user();
            $cartData     = $this->cart->calculate($user);
            $leergutTotal = $this->leergutCart->getCreditTotal();
            $totalMilli   = max(0, $cartData['total_milli'] - $leergutTotal);

            $stripe = app(ShopStripeService::class);

            $successUrl = route('checkout.success', $order) . '?stripe=success';
            $cancelUrl  = route('checkout') . '?stripe=cancelled';

            $url = $stripe->createCheckoutSession($order, $successUrl, $cancelUrl, $totalMilli);

            return redirect($url);
        } catch (\Throwable $e) {
            Log::error('Stripe checkout session creation failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);

            // BUG-9 fix: do NOT confirm the order — no payment has taken place.
            // Cancel the order and tell the user to try again or choose another method.
            $order->update(['status' => Order::STATUS_CANCELLED]);

            return redirect()->route('checkout')
                ->withErrors('Die Stripe-Zahlung konnte nicht gestartet werden. Bitte versuche eine andere Zahlungsmethode.');
        }
    }

    /**
     * Create PayPal order and redirect.
     */
    private function handlePayPalPayment(Order $order): RedirectResponse
    {
        try {
            $paypal = app(ShopPayPalService::class);

            $returnUrl = route('checkout.paypal.success');
            $cancelUrl = route('checkout.paypal.cancel');

            $url = $paypal->createOrder($order, $returnUrl, $cancelUrl);

            return redirect($url);
        } catch (\Throwable $e) {
            Log::error('PayPal order creation failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);

            // BUG-9 fix: do NOT confirm the order — no payment has taken place.
            $order->update(['status' => Order::STATUS_CANCELLED]);

            return redirect()->route('checkout')
                ->withErrors('Die PayPal-Zahlung konnte nicht gestartet werden. Bitte versuche eine andere Zahlungsmethode.');
        }
    }

    /**
     * Queue a confirmation email via deferred_tasks.
     */
    private function queueConfirmationEmail(Order $order): void
    {
        DeferredTask::create([
            'company_id'   => $order->company_id,  // BUG-15 fix: tenant scope
            'type'         => 'email.order_confirmation',
            'payload_json' => json_encode([
                'order_id'    => $order->id,
                'customer_id' => $order->customer_id,
            ]),
            'status'       => DeferredTask::STATUS_PENDING,
            'attempts'     => 0,
            'max_attempts' => 3,
        ]);
    }

    /**
     * Ensure the current user has a linked Customer record.
     */
    private function requireCustomer(): ?Customer
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        if ($user->isSubUser()) {
            $subUser = $user->subUser;
            if (! $subUser?->active) {
                return null;
            }
            return $subUser->parentCustomer;
        }

        if (! $user->isKunde()) {
            return null;
        }

        return $user->customer;
    }
}
