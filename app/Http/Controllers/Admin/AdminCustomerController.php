<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\LexofficeVoucher;
use App\Models\Contact;
use App\Models\Pricing\Customer;
use App\Models\Pricing\CustomerGroup;
use App\Models\Pricing\CustomerNote;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $allowedSorts = ['customer_number', 'company_name', 'email', 'active'];
        $sort      = in_array($request->input('sort'), $allowedSorts, true) ? $request->input('sort') : 'customer_number';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $query = Customer::with('customerGroup')
            ->where('company_id', $company?->id)
            ->orderBy($sort, $direction);

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('customer_number', 'LIKE', $term)
                  ->orWhere('company_name', 'LIKE', $term)
                  ->orWhere('first_name', 'LIKE', $term)
                  ->orWhere('last_name', 'LIKE', $term)
                  ->orWhere('email', 'LIKE', $term)
                  ->orWhere('phone', 'LIKE', $term);
            });
        }

        // Filter: only customers with unreviewed Lexoffice diffs
        $notesFilter = $request->input('notes_filter');
        if ($notesFilter === 'lexoffice_diff') {
            $query->whereHas('notes', function ($q): void {
                $q->where('type', CustomerNote::TYPE_LEXOFFICE_DIFF)->whereNull('reviewed_at');
            });
        }

        // Count for badge in filter tab
        $pendingDiffCount = Customer::where('company_id', $company?->id)
            ->whereHas('notes', fn ($q) => $q->where('type', CustomerNote::TYPE_LEXOFFICE_DIFF)->whereNull('reviewed_at'))
            ->count();

        $customers = $query->paginate(25)->withQueryString();

        // Build a set of lexoffice_contact_ids that are also suppliers (dual-role contacts)
        $supplierLexIds = Supplier::whereNotNull('lexoffice_contact_id')
            ->pluck('lexoffice_contact_id')
            ->flip()
            ->all();

        return view('admin.customers.index', compact('customers', 'pendingDiffCount', 'notesFilter', 'supplierLexIds'));
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
            'customer_group_id'            => ['required', 'exists:customer_groups,id'],
            'customer_number'              => ['nullable', 'string', 'max:50'],
            'company_name'                 => ['nullable', 'string', 'max:200'],
            'first_name'                   => ['nullable', 'string', 'max:100'],
            'last_name'                    => ['nullable', 'string', 'max:100'],
            'email'                        => ['nullable', 'email', 'max:200'],
            'billing_email'                => ['nullable', 'email', 'max:200'],
            'notification_email'           => ['nullable', 'email', 'max:200'],
            'email_notification_shipping'  => ['nullable', 'boolean'],
            'newsletter_consent'           => ['nullable', 'in:all,important_only,none'],
            'phone'                        => ['nullable', 'string', 'max:50'],
            'price_display_mode'           => ['required', 'in:brutto,netto'],
            'delivery_address_text'        => ['nullable', 'string', 'max:1000'],
            'delivery_note'                => ['nullable', 'string', 'max:500'],
            'active'                       => ['nullable', 'boolean'],
            'contacts'                     => ['nullable', 'array'],
            'contacts.*.name'              => ['nullable', 'string', 'max:150'],
            'contacts.*.phone'             => ['nullable', 'string', 'max:50'],
            'contacts.*.email'             => ['nullable', 'email', 'max:200'],
            'contacts.*.role'              => ['nullable', 'string', 'max:100'],
            'kunde_von'                    => ['nullable', 'in:kolabri,kehr'],
            'birth_date'                   => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $customer = Customer::create([
            'company_id'                   => $company?->id,
            'customer_group_id'            => $validated['customer_group_id'],
            'customer_number'              => $validated['customer_number'] ?? '',
            'company_name'                 => $validated['company_name'] ?? null,
            'first_name'                   => $validated['first_name'] ?? null,
            'last_name'                    => $validated['last_name'] ?? null,
            'email'                        => $validated['email'] ?? null,
            'billing_email'                => $validated['billing_email'] ?? null,
            'notification_email'           => $validated['notification_email'] ?? null,
            'email_notification_shipping'  => $request->boolean('email_notification_shipping', true),
            'newsletter_consent'           => $validated['newsletter_consent'] ?? 'important_only',
            'phone'                        => $validated['phone'] ?? null,
            'price_display_mode'           => $validated['price_display_mode'],
            'delivery_address_text'        => $validated['delivery_address_text'] ?? null,
            'delivery_note'                => $validated['delivery_note'] ?? null,
            'active'                       => $request->boolean('active'),
            'kunde_von'                    => $validated['kunde_von'] ?: null,
            'birth_date'                   => $validated['birth_date'] ?? null,
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
        $customer->load(['contacts', 'addresses']);
        $customerGroups = CustomerGroup::where('active', true)->orderBy('name')->get();

        return view('admin.customers.edit', compact('customer', 'customerGroups'));
    }

    /**
     * PUT /admin/customers/{customer}
     */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'customer_group_id'            => ['required', 'exists:customer_groups,id'],
            'customer_number'              => ['nullable', 'string', 'max:50'],
            'company_name'                 => ['nullable', 'string', 'max:200'],
            'first_name'                   => ['nullable', 'string', 'max:100'],
            'last_name'                    => ['nullable', 'string', 'max:100'],
            'email'                        => ['nullable', 'email', 'max:200'],
            'billing_email'                => ['nullable', 'email', 'max:200'],
            'notification_email'           => ['nullable', 'email', 'max:200'],
            'email_notification_shipping'  => ['nullable', 'boolean'],
            'newsletter_consent'           => ['nullable', 'in:all,important_only,none'],
            'phone'                        => ['nullable', 'string', 'max:50'],
            'price_display_mode'           => ['required', 'in:brutto,netto'],
            'delivery_address_text'        => ['nullable', 'string', 'max:1000'],
            'delivery_note'                => ['nullable', 'string', 'max:500'],
            'contacts'                     => ['nullable', 'array'],
            'contacts.*.name'              => ['nullable', 'string', 'max:150'],
            'contacts.*.phone'             => ['nullable', 'string', 'max:50'],
            'contacts.*.email'             => ['nullable', 'email', 'max:200'],
            'contacts.*.role'              => ['nullable', 'string', 'max:100'],
            'kunde_von'                    => ['nullable', 'in:kolabri,kehr'],
            'birth_date'                   => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $customer->update([
            'customer_group_id'            => $validated['customer_group_id'],
            'customer_number'              => $validated['customer_number'] ?? $customer->customer_number,
            'company_name'                 => $validated['company_name'] ?? null,
            'first_name'                   => $validated['first_name'] ?? null,
            'last_name'                    => $validated['last_name'] ?? null,
            'email'                        => $validated['email'] ?? null,
            'billing_email'                => $validated['billing_email'] ?? null,
            'notification_email'           => $validated['notification_email'] ?? null,
            'email_notification_shipping'  => $request->boolean('email_notification_shipping', true),
            'newsletter_consent'           => $validated['newsletter_consent'] ?? 'important_only',
            'phone'                        => $validated['phone'] ?? null,
            'price_display_mode'           => $validated['price_display_mode'],
            'delivery_address_text'        => $validated['delivery_address_text'] ?? null,
            'delivery_note'                => $validated['delivery_note'] ?? null,
            'active'                       => $request->boolean('active'),
            'kunde_von'                    => $validated['kunde_von'] ?: null,
            'birth_date'                   => $validated['birth_date'] ?? null,
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

        // Block Lexoffice re-import before deleting the record
        if ($customer->lexoffice_contact_id) {
            $this->blockLexofficeContact($customer->lexoffice_contact_id, $customer->company_id, 'customer', 'Manuell gelöscht');
        }

        $customer->contacts()->delete();
        $customer->delete();

        return redirect()->route('admin.customers.index')
            ->with('success', 'Kunde gelöscht.');
    }

    /**
     * GET /admin/customers/{customer}
     * Customer detail with history notes.
     */
    public function show(Customer $customer): View
    {
        $customer->load([
            'customerGroup', 'contacts', 'addresses',
            'notes.createdBy', 'notes.reviewedBy',
            'communications.tags',
            'subUsers.user',
        ]);

        $vouchers = LexofficeVoucher::where('customer_id', $customer->id)
            ->with('payments')
            ->orderByDesc('voucher_date')
            ->get();

        $recentOrders = $customer->orders()->latest()->take(5)->get();

        $orderStats = [
            'count'       => $customer->orders()->count(),
            'total_milli' => (int) $customer->orders()->sum('total_gross_milli'),
            'last_date'   => $customer->orders()->latest()->value('created_at'),
        ];

        $openSaldo = $vouchers
            ->whereIn('voucher_status', ['open', 'overdue'])
            ->whereIn('voucher_type', ['salesinvoice', 'downpaymentinvoice'])
            ->sum('open_amount');

        return view('admin.customers.show', compact('customer', 'vouchers', 'recentOrders', 'orderStats', 'openSaldo'));
    }

    /**
     * POST /admin/customers/{customer}/notes/{note}/resolve
     * Mark a customer note as reviewed.
     */
    public function resolveNote(Customer $customer, CustomerNote $note): RedirectResponse
    {
        abort_if($note->customer_id !== $customer->id, 404);

        $note->update([
            'reviewed_at'         => now(),
            'reviewed_by_user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', 'Notiz als geprüft markiert.');
    }

    /**
     * POST /admin/customers/{customer}/merge
     * Merge a duplicate customer (source) into this one (target).
     * All related records are transferred to the target; the source is deleted.
     */
    public function merge(Request $request, Customer $target): RedirectResponse
    {
        $validated = $request->validate([
            'source_customer_number' => ['required', 'string', 'max:50'],
        ]);

        $source = Customer::where('customer_number', $validated['source_customer_number'])
            ->where('id', '!=', $target->id)
            ->first();

        if (! $source) {
            return back()->with('error', 'Kein anderer Kunde mit der Kundennummer "' . $validated['source_customer_number'] . '" gefunden.');
        }

        DB::transaction(function () use ($target, $source): void {
            // Transfer orders
            $source->orders()->update(['customer_id' => $target->id]);

            // Transfer customer notes
            $source->notes()->update(['customer_id' => $target->id]);

            // Transfer lexoffice vouchers
            LexofficeVoucher::where('customer_id', $source->id)
                ->update(['customer_id' => $target->id]);

            // Transfer addresses
            $source->addresses()->update(['customer_id' => $target->id]);

            // Copy lexoffice_contact_id if target doesn't have one
            if (! $target->lexoffice_contact_id && $source->lexoffice_contact_id) {
                $target->update(['lexoffice_contact_id' => $source->lexoffice_contact_id]);
            }

            // Delete source contacts and then source record
            $source->contacts()->delete();
            $source->delete();
        });

        return redirect()->route('admin.customers.show', $target)
            ->with('success', 'Kundendatensatz ' . $source->customer_number . ' wurde in diesen Kunden zusammengeführt und gelöscht.');
    }

    /**
     * POST /admin/customers/{customer}/link-wawi
     * Manually link a customer to a JTL-WaWi record by customer number.
     */
    public function linkWawi(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'wawi_kunden_nr' => ['required', 'string', 'max:100'],
        ]);

        $wawiRecord = DB::table('wawi_kunden')
            ->where('cKundenNr', $validated['wawi_kunden_nr'])
            ->first();

        if ($wawiRecord === null) {
            return back()->withErrors(['wawi_kunden_nr' => 'Keine WaWi-Kundennummer "' . $validated['wawi_kunden_nr'] . '" gefunden.']);
        }

        $customer->update(['wawi_kunden_id' => $wawiRecord->kKunde]);

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', 'WaWi-Verknüpfung gespeichert (ID: ' . $wawiRecord->kKunde . ').');
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

    /**
     * Insert into lexoffice_contact_blocks so the contact is skipped on future imports.
     * Uses INSERT IGNORE / updateOrIgnore to avoid duplicate-key errors.
     */
    private function blockLexofficeContact(string $lexId, ?int $companyId, string $entity, string $reason): void
    {
        DB::table('lexoffice_contact_blocks')->updateOrInsert(
            ['company_id' => $companyId, 'lexoffice_contact_id' => $lexId],
            ['blocked_entity' => $entity, 'reason' => $reason, 'created_at' => now()],
        );
    }
}
