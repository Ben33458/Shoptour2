<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Orders\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * WP-21 – Customer account self-service area.
 *
 * All routes require auth middleware (set in routes/web.php).
 * Admin/Mitarbeiter users are redirected to the admin area.
 */
class AccountController extends Controller
{
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

        return redirect()->route('account.addresses')->with('success', 'Adresse gespeichert.');
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

        return redirect()->route('account.addresses')->with('success', 'Adresse aktualisiert.');
    }

    public function destroyAddress(Address $address): RedirectResponse
    {
        $customer = $this->requireCustomer();
        if ($address->customer_id !== $customer->id) {
            abort(403);
        }
        $address->delete();

        return redirect()->route('account.addresses')->with('success', 'Adresse gelöscht.');
    }

    public function setDefaultAddress(Address $address): RedirectResponse
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

        return redirect()->route('account.addresses')->with('success', 'Standard-Adresse gesetzt.');
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    /**
     * Load the current user's Customer record. Aborts 403 if not found.
     */
    private function requireCustomer(): \App\Models\Pricing\Customer
    {
        /** @var User $user */
        $user     = Auth::user();
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
            'country_code' => ['sometimes', 'string', 'size:2'],
            'phone'        => ['nullable', 'string', 'max:50'],
        ]);
    }
}
