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
            'active'                 => ['nullable', 'boolean'],
            'po_filter_own_products' => ['nullable', 'boolean'],
            'contacts'               => ['nullable', 'array'],
            'contacts.*.name'        => ['nullable', 'string', 'max:150'],
            'contacts.*.phone'       => ['nullable', 'string', 'max:50'],
            'contacts.*.email'       => ['nullable', 'email', 'max:200'],
            'contacts.*.role'        => ['nullable', 'string', 'max:100'],
        ]);

        $supplier = Supplier::create([
            'company_id'             => $company?->id,
            'type'                   => $validated['type'] ?? Supplier::TYPE_SUPPLIER,
            'name'                   => $validated['name'],
            'email'                  => $validated['email'] ?? null,
            'phone'                  => $validated['phone'] ?? null,
            'address'                => $validated['address'] ?? null,
            'currency'               => strtoupper($validated['currency'] ?? 'EUR'),
            'active'                 => $request->boolean('active'),
            'po_filter_own_products' => $request->boolean('po_filter_own_products'),
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
        $supplier->load(['contacts', 'communications.tags']);

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
            'type'                   => $validated['type'] ?? $supplier->type,
            'name'                   => $validated['name'],
            'email'                  => $validated['email'] ?? null,
            'phone'                  => $validated['phone'] ?? null,
            'address'                => $validated['address'] ?? null,
            'currency'               => strtoupper($validated['currency'] ?? $supplier->currency),
            'active'                 => $request->boolean('active'),
            'po_filter_own_products' => $request->boolean('po_filter_own_products'),
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
     * GET /admin/suppliers/{supplier}/merge-preview?source_supplier_id=X
     * Show a side-by-side field comparison before the actual merge.
     */
    public function mergePreview(Request $request, Supplier $target): View|RedirectResponse
    {
        $request->validate([
            'source_supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
        ]);

        $sourceId = (int) $request->input('source_supplier_id');

        if ($sourceId === $target->id) {
            return back()->with('error', 'Quell- und Ziellieferant dürfen nicht identisch sein.');
        }

        $source = Supplier::with('contacts')->findOrFail($sourceId);
        $target->load('contacts');

        return view('admin.suppliers.merge-preview', compact('target', 'source'));
    }

    /**
     * POST /admin/suppliers/{supplier}/merge
     * Execute the merge with explicit field selections from the preview form.
     */
    public function merge(Request $request, Supplier $target): RedirectResponse
    {
        $validated = $request->validate([
            'source_supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'fields'             => ['nullable', 'array'],
            'fields.*'           => ['string', 'in:source,target'],
        ]);

        $sourceId = (int) $validated['source_supplier_id'];

        if ($sourceId === $target->id) {
            return back()->with('error', 'Quell- und Ziellieferant dürfen nicht identisch sein.');
        }

        $source     = Supplier::with('contacts')->findOrFail($sourceId);
        $fieldChoices = $validated['fields'] ?? [];

        // Fields that can be individually chosen
        $mergeableFields = [
            'name', 'email', 'phone', 'address', 'contact_name', 'currency',
            'lieferanten_nr', 'lexoffice_contact_id', 'ninox_lieferanten_id',
            'bestelltag', 'liefertag', 'bestell_schlusszeit', 'lieferintervall',
            'mindestbestellwert_netto_ek_milli', 'kontrollstufe_default',
        ];

        $updates = [];
        foreach ($mergeableFields as $field) {
            $choice = $fieldChoices[$field] ?? 'target';
            if ($choice === 'source') {
                $updates[$field] = $source->{$field};
            }
        }

        $sourceName = $source->name;
        $sourceId2  = $source->id;

        DB::transaction(function () use ($target, $source, $updates): void {
            // Apply selected field values
            if ($updates) {
                $target->update($updates);
            }

            // Transfer all relations to target
            $source->supplierProducts()->update(['supplier_id' => $target->id]);
            $source->purchaseOrders()->update(['supplier_id' => $target->id]);
            LexofficeVoucher::where('supplier_id', $source->id)
                ->update(['supplier_id' => $target->id]);

            // Delete source contacts, then source
            $source->contacts()->delete();
            $source->delete();
        });

        return redirect()->route('admin.suppliers.edit', ['supplier' => $target->id])
            ->with('success', "Lieferant \"{$sourceName}\" (ID {$sourceId2}) wurde zusammengeführt und gelöscht.");
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
