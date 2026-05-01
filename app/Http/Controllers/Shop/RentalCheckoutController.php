<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\StoreRentalCheckoutRequest;
use App\Models\Admin\DeferredTask;
use App\Models\Event\EventLocation;
use App\Models\Pricing\Customer;
use App\Models\User;
use App\Services\Rental\RentalBookingService;
use App\Services\Rental\RentalCartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RentalCheckoutController extends Controller
{
    public function __construct(
        private readonly RentalCartService $cart,
        private readonly RentalBookingService $booking,
    ) {}

    public function show(): RedirectResponse
    {
        return redirect()->route('checkout');
    }

    public function store(StoreRentalCheckoutRequest $request): RedirectResponse
    {
        $customer = $this->requireCustomer();
        if (! $customer) {
            return redirect()->route('login');
        }

        if ($this->cart->isEmpty()) {
            return redirect()->route('rental.catalog')
                ->with('info', 'Dein Leih-Warenkorb ist leer.');
        }

        $from  = $this->cart->getDateFrom();
        $until = $this->cart->getDateUntil();
        if (! $from || ! $until) {
            return redirect()->route('rental.landing')
                ->with('info', 'Bitte wähle zuerst den Mietzeitraum.');
        }

        try {
            $order = $this->booking->createFromCart(
                $customer,
                $this->cart->get(),
                $request->validated(),
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()
                ->with('error', $e->getMessage());
        }

        $this->cart->clear();

        DeferredTask::create([
            'company_id'   => $order->company_id,
            'type'         => 'email.order_confirmation',
            'payload_json' => json_encode([
                'order_id'    => $order->id,
                'customer_id' => $customer->id,
            ]),
            'status'       => DeferredTask::STATUS_PENDING,
            'attempts'     => 0,
            'max_attempts' => 3,
        ]);

        return redirect()->route('rental.success', ['order' => $order->order_number])
            ->with('rental_order_id', $order->id);
    }

    public function success(string $order): View
    {
        $orderNumber = $order;
        return view('shop.rental.success', compact('orderNumber'));
    }

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
