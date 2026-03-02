<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Pricing\Customer;
use App\Models\Pricing\CustomerGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * WP-19 + WP-20: CRUD controller for Customers in the admin area.
 */
class AdminCustomerController extends Controller
{
    /**
     * GET /admin/customers
     * Paginated list of customers with optional search.
     */
    public function index(Request $request): View
    {
        $company = App::make('current_company');

        $query = Customer::with('customerGroup')
            ->where('company_id', $company?->id)
            ->orderBy('customer_number');

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('customer_number', 'LIKE', $term)
                  ->orWhere('first_name', 'LIKE', $term)
                  ->orWhere('last_name', 'LIKE', $term)
                  ->orWhere('email', 'LIKE', $term)
                  ->orWhere('phone', 'LIKE', $term);
            });
        }

        $customers = $query->paginate(25)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    /**
     * GET /admin/customers/create
     */
    public function create(): View
    {
        $customerGroups = CustomerGroup::where('active', true)->orderBy('name')->get();

        return view('admin.customers.create', compact('customerGroups'));
    }

    /**
     * POST /admin/customers
     */
    public function store(Request $request): RedirectResponse
    {
        $company = App::make('current_company');

        $validated = $request->validate([
            'customer_group_id'     => ['required', 'exists:customer_groups,id'],
            'customer_number'       => ['nullable', 'string', 'max:50'],
            'first_name'            => ['nullable', 'string', 'max:100'],
            'last_name'             => ['nullable', 'string', 'max:100'],
            'email'                 => ['nullable', 'email', 'max:200'],
            'phone'                 => ['nullable', 'string', 'max:50'],
            'price_display_mode'    => ['required', 'in:gross,net'],
            'delivery_address_text' => ['nullable', 'string', 'max:1000'],
            'delivery_note'         => ['nullable', 'string', 'max:500'],
            'active'                => ['nullable', 'boolean'],
            'contacts'              => ['nullable', 'array'],
            'contacts.*.name'       => ['nullable', 'string', 'max:150'],
            'contacts.*.phone'      => ['nullable', 'string', 'max:50'],
            'contacts.*.email'      => ['nullable', 'email', 'max:200'],
            'contacts.*.role'       => ['nullable', 'string', 'max:100'],
        ]);

        $customer = Customer::create([
            'company_id'            => $company?->id,
            'customer_group_id'     => $validated['customer_group_id'],
            'customer_number'       => $validated['customer_number'] ?? '',
            'first_name'            => $validated['first_name'] ?? null,
            'last_name'             => $validated['last_name'] ?? null,
            'email'                 => $validated['email'] ?? null,
            'phone'                 => $validated['phone'] ?? null,
            'price_display_mode'    => $validated['price_display_mode'],
            'delivery_address_text' => $validated['delivery_address_text'] ?? null,
            'delivery_note'         => $validated['delivery_note'] ?? null,
            'active'                => $request->boolean('active'),
        ]);

        // Auto-generate customer_number if blank
        if (empty($validated['customer_number'])) {
            $customer->update(['customer_number' => 'K' . date('Y') . '-' . str_pad((string) $customer->id, 5, '0', STR_PAD_LEFT)]);
        }

        $this->syncContacts($customer, $request->input('contacts', []));

        return redirect()->route('admin.customers.index')
            ->with('success', 'Kunde angelegt: ' . trim($customer->first_name . ' ' . $customer->last_name) ?: $customer->customer_number);
    }

    /**
     * GET /admin/customers/{customer}/edit
     */
    public function edit(Customer $customer): View
    {
        $customer->load('contacts');
        $customerGroups = CustomerGroup::where('active', true)->orderBy('name')->get();

        return view('admin.customers.edit', compact('customer', 'customerGroups'));
    }

    /**
     * PUT /admin/customers/{customer}
     */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'customer_group_id'     => ['required', 'exists:customer_groups,id'],
            'customer_number'       => ['nullable', 'string', 'max:50'],
            'first_name'            => ['nullable', 'string', 'max:100'],
            'last_name'             => ['nullable', 'string', 'max:100'],
            'email'                 => ['nullable', 'email', 'max:200'],
            'phone'                 => ['nullable', 'string', 'max:50'],
            'price_display_mode'    => ['required', 'in:gross,net'],
            'delivery_address_text' => ['nullable', 'string', 'max:1000'],
            'delivery_note'         => ['nullable', 'string', 'max:500'],
            'contacts'              => ['nullable', 'array'],
            'contacts.*.name'       => ['nullable', 'string', 'max:150'],
            'contacts.*.phone'      => ['nullable', 'string', 'max:50'],
            'contacts.*.email'      => ['nullable', 'email', 'max:200'],
            'contacts.*.role'       => ['nullable', 'string', 'max:100'],
        ]);

        $customer->update([
            'customer_group_id'     => $validated['customer_group_id'],
            'customer_number'       => $validated['customer_number'] ?? $customer->customer_number,
            'first_name'            => $validated['first_name'] ?? null,
            'last_name'             => $validated['last_name'] ?? null,
            'email'                 => $validated['email'] ?? null,
            'phone'                 => $validated['phone'] ?? null,
            'price_display_mode'    => $validated['price_display_mode'],
            'delivery_address_text' => $validated['delivery_address_text'] ?? null,
            'delivery_note'         => $validated['delivery_note'] ?? null,
            'active'                => $request->boolean('active'),
        ]);

        $this->syncContacts($customer, $request->input('contacts', []));

        return redirect()->route('admin.customers.index')
            ->with('success', 'Kunde gespeichert.');
    }

    /**
     * DELETE /admin/customers/{customer}
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->orders()->exists()) {
            return back()->with('error', 'Dieser Kunde kann nicht gelöscht werden, da noch Bestellungen vorhanden sind.');
        }

        $customer->contacts()->delete();
        $customer->delete();

        return redirect()->route('admin.customers.index')
            ->with('success', 'Kunde gelöscht.');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Sync contacts from the form submission.
     * Creates new contacts, updates existing ones, deletes removed ones.
     *
     * @param  array<int,array<string,mixed>>  $contactsData
     */
    private function syncContacts(Model $entity, array $contactsData): void
    {
        $savedIds = [];

        foreach ($contactsData as $i => $data) {
            if (empty(trim((string) ($data['name'] ?? '')))) {
                continue;
            }

            $contact = !empty($data['id'])
                ? $entity->contacts()->find((int) $data['id']) ?? new Contact()
                : new Contact();

            $contact->fill([
                'name'       => $data['name'],
                'phone'      => $data['phone'] ?? null,
                'email'      => $data['email'] ?? null,
                'role'       => $data['role'] ?? null,
                'sort_order' => (int) ($data['sort_order'] ?? $i),
            ]);

            $entity->contacts()->save($contact);
            $savedIds[] = $contact->id;
        }

        // Remove contacts that were deleted in the form
        $entity->contacts()->whereNotIn('id', $savedIds)->delete();
    }
}
