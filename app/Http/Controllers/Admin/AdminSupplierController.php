<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\LexofficeVoucher;
use App\Models\Contact;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
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

        $allowedSorts = ['name', 'email', 'phone', 'currency', 'active'];
        $sort      = in_array($request->input('sort'), $allowedSorts, true) ? $request->input('sort') : 'name';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $query = Supplier::where('company_id', $company?->id)
            ->orderBy($sort, $direction);

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'LIKE', $term)
                  ->orWhere('email', 'LIKE', $term);
            });
        }

        if ($request->filled('type_filter')) {
            $query->where('type', $request->input('type_filter'));
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
            'type'              => ['nullable', 'in:supplier,partner'],
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
            'type'       => $validated['type'] ?? Supplier::TYPE_SUPPLIER,
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
            'type'              => ['nullable', 'in:supplier,partner'],
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
            'type'     => $validated['type'] ?? $supplier->type,
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
     * POST /admin/suppliers/bulk-set-type
     * Set the type (supplier|partner) for multiple suppliers at once.
     */
    public function bulkSetType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'  => ['required', 'array', 'min:1'],
            'ids.*'=> ['integer', 'exists:suppliers,id'],
            'type' => ['required', 'in:supplier,partner'],
        ]);

        $count = Supplier::whereIn('id', $validated['ids'])->update(['type' => $validated['type']]);

        $label = $validated['type'] === 'partner' ? 'Geschäftspartner' : 'Warenlieferant';

        return back()->with('success', "{$count} Lieferant(en) als \"{$label}\" markiert.");
    }

    /**
     * POST /admin/suppliers/{supplier}/merge
     * Merge a duplicate supplier (source) into this one (target).
     * Identified by supplier ID to avoid name ambiguity.
     */
    public function merge(Request $request, Supplier $target): RedirectResponse
    {
        $validated = $request->validate([
            'source_supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
        ]);

        $sourceId = (int) $validated['source_supplier_id'];

        if ($sourceId === $target->id) {
            return back()->with('error', 'Quell- und Ziellieferant dürfen nicht identisch sein.');
        }

        $source = Supplier::findOrFail($sourceId);

        DB::transaction(function () use ($target, $source): void {
            // Transfer supplier products
            $source->supplierProducts()->update(['supplier_id' => $target->id]);

            // Transfer purchase orders
            $source->purchaseOrders()->update(['supplier_id' => $target->id]);

            // Transfer lexoffice vouchers
            LexofficeVoucher::where('supplier_id', $source->id)
                ->update(['supplier_id' => $target->id]);

            // Copy lexoffice_contact_id and lieferanten_nr if target doesn't have them
            $updates = [];
            if (! $target->lexoffice_contact_id && $source->lexoffice_contact_id) {
                $updates['lexoffice_contact_id'] = $source->lexoffice_contact_id;
            }
            if (! $target->lieferanten_nr && $source->lieferanten_nr) {
                $updates['lieferanten_nr'] = $source->lieferanten_nr;
            }
            if ($updates) {
                $target->update($updates);
            }

            // Delete source contacts and then source record
            $source->contacts()->delete();
            $source->delete();
        });

        return redirect()->route('admin.suppliers.edit', $target)
            ->with('success', 'Lieferant "' . $source->name . '" (ID ' . $source->id . ') wurde zusammengeführt und gelöscht.');
    }

    /**
     * DELETE /admin/suppliers/{supplier}
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error', 'Dieser Lieferant kann nicht gelöscht werden, da noch Bestellungen vorhanden sind.');
        }

        // Block Lexoffice re-import before deleting the record
        if ($supplier->lexoffice_contact_id) {
            $this->blockLexofficeContact($supplier->lexoffice_contact_id, $supplier->company_id, 'supplier', 'Manuell gelöscht');
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

    private function blockLexofficeContact(string $lexId, ?int $companyId, string $entity, string $reason): void
    {
        DB::table('lexoffice_contact_blocks')->updateOrInsert(
            ['company_id' => $companyId, 'lexoffice_contact_id' => $lexId],
            ['blocked_entity' => $entity, 'reason' => $reason, 'created_at' => now()],
        );
    }
}
