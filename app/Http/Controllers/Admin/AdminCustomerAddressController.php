<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Pricing\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminCustomerAddressController extends Controller
{
    /**
     * POST /admin/customers/{customer}/addresses
     */
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $this->validateAddress($request);

        if ($validated['is_default'] ?? false) {
            $customer->addresses()->where('type', $validated['type'])->update(['is_default' => false]);
        }

        $customer->addresses()->create($validated);

        return redirect()->route('admin.customers.edit', $customer)
            ->with('success', 'Adresse gespeichert.');
    }

    /**
     * PUT /admin/customers/{customer}/addresses/{address}
     */
    public function update(Request $request, Customer $customer, Address $address): RedirectResponse
    {
        abort_if($address->customer_id !== $customer->id, 404);

        $validated = $this->validateAddress($request);

        if ($validated['is_default'] ?? false) {
            $customer->addresses()->where('type', $validated['type'])->update(['is_default' => false]);
        }

        $address->update($validated);

        return redirect()->route('admin.customers.edit', $customer)
            ->with('success', 'Adresse aktualisiert.');
    }

    /**
     * DELETE /admin/customers/{customer}/addresses/{address}
     */
    public function destroy(Customer $customer, Address $address): RedirectResponse
    {
        abort_if($address->customer_id !== $customer->id, 404);

        $address->delete();

        return redirect()->route('admin.customers.edit', $customer)
            ->with('success', 'Adresse gelöscht.');
    }

    /**
     * POST /admin/customers/{customer}/addresses/{address}/set-default
     */
    public function setDefault(Customer $customer, Address $address): RedirectResponse
    {
        abort_if($address->customer_id !== $customer->id, 404);

        $customer->addresses()->where('type', $address->type)->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return redirect()->route('admin.customers.edit', $customer)
            ->with('success', 'Standardadresse gesetzt.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'type'          => ['required', 'in:delivery,billing'],
            'is_default'    => ['sometimes', 'boolean'],
            'label'         => ['nullable', 'string', 'max:100'],
            'first_name'    => ['nullable', 'string', 'max:100'],
            'last_name'     => ['nullable', 'string', 'max:100'],
            'company'       => ['nullable', 'string', 'max:200'],
            'street'        => ['required', 'string', 'max:200'],
            'house_number'  => ['nullable', 'string', 'max:20'],
            'zip'           => ['required', 'string', 'max:10'],
            'city'          => ['required', 'string', 'max:100'],
            'country_code'  => ['sometimes', 'string', 'size:2'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'delivery_note' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
