<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * WP-19 + WP-20: CRUD controller for Suppliers in the admin area.
 */
class AdminSupplierController extends Controller
{
    /**
     * GET /admin/suppliers
     */
    public function index(Request $request): View
    {
        $company = App::make('current_company');

        $query = Supplier::where('company_id', $company?->id)
            ->orderBy('name');

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'LIKE', $term)
                  ->orWhere('email', 'LIKE', $term);
            });
        }

        $suppliers = $query->paginate(25)->withQueryString();

        return view('admin.suppliers.index', compact('suppliers'));
    }

    /**
     * GET /admin/suppliers/create
     */
    public function create(): View
    {
        return view('admin.suppliers.create');
    }

    /**
     * POST /admin/suppliers
     */
    public function store(Request $request): RedirectResponse
    {
        $company = App::make('current_company');

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:200'],
            'email'             => ['nullable', 'email', 'max:200'],
            'phone'             => ['nullable', 'string', 'max:50'],
            'address'           => ['nullable', 'string', 'max:500'],
            'currency'          => ['nullable', 'string', 'size:3'],
            'active'            => ['nullable', 'boolean'],
            'contacts'          => ['nullable', 'array'],
            'contacts.*.name'   => ['nullable', 'string', 'max:150'],
            'contacts.*.phone'  => ['nullable', 'string', 'max:50'],
            'contacts.*.email'  => ['nullable', 'email', 'max:200'],
            'contacts.*.role'   => ['nullable', 'string', 'max:100'],
        ]);

        $supplier = Supplier::create([
            'company_id' => $company?->id,
            'name'       => $validated['name'],
            'email'      => $validated['email'] ?? null,
            'phone'      => $validated['phone'] ?? null,
            'address'    => $validated['address'] ?? null,
            'currency'   => strtoupper($validated['currency'] ?? 'EUR'),
            'active'     => $request->boolean('active'),
        ]);

        $this->syncContacts($supplier, $request->input('contacts', []));

        return redirect()->route('admin.suppliers.index')
            ->with('success', 'Lieferant angelegt: ' . $validated['name']);
    }

    /**
     * GET /admin/suppliers/{supplier}/edit
     */
    public function edit(Supplier $supplier): View
    {
        $supplier->load('contacts');

        return view('admin.suppliers.edit', compact('supplier'));
    }

    /**
     * PUT /admin/suppliers/{supplier}
     */
    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:200'],
            'email'             => ['nullable', 'email', 'max:200'],
            'phone'             => ['nullable', 'string', 'max:50'],
            'address'           => ['nullable', 'string', 'max:500'],
            'currency'          => ['nullable', 'string', 'size:3'],
            'contacts'          => ['nullable', 'array'],
            'contacts.*.name'   => ['nullable', 'string', 'max:150'],
            'contacts.*.phone'  => ['nullable', 'string', 'max:50'],
            'contacts.*.email'  => ['nullable', 'email', 'max:200'],
            'contacts.*.role'   => ['nullable', 'string', 'max:100'],
        ]);

        $supplier->update([
            'name'     => $validated['name'],
            'email'    => $validated['email'] ?? null,
            'phone'    => $validated['phone'] ?? null,
            'address'  => $validated['address'] ?? null,
            'currency' => strtoupper($validated['currency'] ?? $supplier->currency),
            'active'   => $request->boolean('active'),
        ]);

        $this->syncContacts($supplier, $request->input('contacts', []));

        return redirect()->route('admin.suppliers.index')
            ->with('success', 'Lieferant gespeichert.');
    }

    /**
     * DELETE /admin/suppliers/{supplier}
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error', 'Dieser Lieferant kann nicht gelöscht werden, da noch Bestellungen vorhanden sind.');
        }

        $supplier->contacts()->delete();
        $supplier->delete();

        return redirect()->route('admin.suppliers.index')
            ->with('success', 'Lieferant gelöscht.');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Sync contacts from the form submission.
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

        $entity->contacts()->whereNotIn('id', $savedIds)->delete();
    }
}
