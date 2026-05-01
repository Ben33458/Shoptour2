<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Models\Admin\LexofficeArticle;
use App\Models\Admin\LexofficeContact;
use App\Models\Admin\LexofficeCountry;
use App\Models\Admin\LexofficePayment;
use App\Models\Admin\LexofficePaymentCondition;
use App\Models\Admin\LexofficePostingCategory;
use App\Models\Admin\LexofficePrintLayout;
use App\Models\Admin\LexofficeRecurringTemplate;
use App\Models\Admin\LexofficeVoucher;
use App\Models\Pricing\AppSetting;
use App\Models\Pricing\Customer;
use App\Models\SourceMatch;
use App\Models\Supplier\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Imports ALL Lexoffice data into dedicated lexoffice_* tables.
 *
 * This service NEVER modifies Shoptour domain tables (customers, suppliers, invoices, …).
 * Use reconcileContacts() as a separate step to link lexoffice_contacts to local records.
 */
class LexofficeImport
{
    public function __construct(
        private readonly LexofficeClient $client,
    ) {}

    // =========================================================================
    // importAll — runs all import methods and returns combined stats
    // =========================================================================

    /**
     * @return array<string, array<string, int>>
     */
    public function importAll(?int $companyId = null): array
    {
        $empty = ['created' => 0, 'updated' => 0, 'total' => 0];

        $run = function (callable $fn) use ($empty): array {
            try {
                return $fn();
            } catch (\Throwable) {
                return $empty;
            }
        };

        return [
            'contacts'            => $run(fn () => $this->importContacts(null, $companyId)),
            'vouchers'            => $run(fn () => $this->importVouchers($companyId)),
            'articles'            => $run(fn () => $this->importArticles($companyId)),
            'payment_conditions'  => $run(fn () => $this->importPaymentConditions($companyId)),
            'posting_categories'  => $run(fn () => $this->importPostingCategories($companyId)),
            'print_layouts'       => $run(fn () => $this->importPrintLayouts($companyId)),
            'recurring_templates' => $run(fn () => $this->importRecurringTemplates($companyId)),
            'countries'           => $run(fn () => $this->importCountries()),
            'payments'            => $run(fn () => $this->importPayments($companyId)),
        ];
    }

    // =========================================================================
    // Contacts
    // =========================================================================

    /**
     * Import contacts from Lexoffice into lexoffice_contacts.
     *
     * @param  string|null $role  'customer', 'vendor', or null for both
     * @return array{created: int, updated: int, total: int}
     */
    public function importContacts(?string $role, ?int $companyId): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'total' => 0];
        $seen  = [];   // deduplicate UUIDs when fetching both roles

        $roles = $role ? [$role] : ['customer', 'vendor'];

        foreach ($roles as $r) {
            $page = 0;
            do {
                $data     = $this->client->listContacts($r, $page, 100);
                $contacts = $data['content'] ?? [];

                foreach ($contacts as $contact) {
                    $uuid = $contact['id'] ?? null;
                    if (! $uuid || isset($seen[$uuid])) {
                        continue;
                    }
                    $seen[$uuid] = true;
                    $stats['total']++;

                    $existing = LexofficeContact::where('lexoffice_uuid', $uuid)->exists();

                    LexofficeContact::updateOrCreate(
                        ['lexoffice_uuid' => $uuid],
                        [
                            'company_id'      => $companyId,
                            'version'         => $contact['version'] ?? 0,
                            'archived'        => $contact['archived'] ?? false,
                            'is_customer'     => isset($contact['roles']['customer']),
                            'is_vendor'       => isset($contact['roles']['vendor']),
                            'customer_number' => isset($contact['roles']['customer']['number'])
                                ? (string) $contact['roles']['customer']['number']
                                : null,
                            'vendor_number'   => isset($contact['roles']['vendor']['number'])
                                ? (string) $contact['roles']['vendor']['number']
                                : null,
                            'company_name'    => $contact['company']['name'] ?? null,
                            'salutation'      => $contact['person']['salutation'] ?? null,
                            'first_name'      => $contact['person']['firstName'] ?? null,
                            'last_name'       => $contact['person']['lastName'] ?? null,
                            'primary_email'   => $this->extractFirstEmail($contact),
                            'primary_phone'   => $this->extractFirstPhone($contact),
                            'note'            => $contact['note'] ?? null,
                            'raw_json'        => $contact,
                            'synced_at'       => now(),
                        ]
                    );

                    $existing ? $stats['updated']++ : $stats['created']++;
                }

                $page++;
                $totalPages = $data['totalPages'] ?? 1;
            } while ($page < $totalPages);
        }

        return $stats;
    }

    // =========================================================================
    // Vouchers — ALL types, no shoptour FK population
    // =========================================================================

    /**
     * Import all voucher types from Lexoffice into lexoffice_vouchers.
     * Does NOT populate customer_id / supplier_id (reconciliation step).
     *
     * @return array{created: int, updated: int, total: int, errors: int}
     */
    /**
     * Import vouchers from Lexoffice.
     *
     * Uses `updatedDateFrom` for incremental sync — only fetches vouchers that were
     * created or modified since the last successful run. On first run (no stored
     * timestamp) all vouchers are fetched. After each successful run the timestamp
     * is persisted in app_settings so the next run stays fast.
     *
     * @param  string[]|null  $typesFilter  Limit to specific voucher types (null = all types)
     * @param  bool           $fullResync   Ignore the stored cursor and re-fetch everything
     */
    public function importVouchers(?int $companyId, ?array $typesFilter = null, bool $fullResync = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'total' => 0, 'errors' => 0];

        // Determine incremental filter: only fetch vouchers updated since last run.
        // Lexoffice voucherlist only accepts plain date strings (YYYY-MM-DD) for
        // updatedDateFrom — ISO8601 strings with timezone offset cause HTTP 400.
        $lastRun    = $fullResync ? null : AppSetting::get('lexoffice.voucher_import_last_run');
        $runStarted = now()->toDateString(); // e.g. "2026-04-23"

        $incrementalFilter = $lastRun
            ? ['updatedDateFrom' => $lastRun]
            : [];

        // Lexoffice voucherlist requires BOTH voucherType AND voucherStatus.
        $allTypes = [
            LexofficeVoucher::TYPE_SALES_INVOICE,
            LexofficeVoucher::TYPE_CREDIT_NOTE,
            LexofficeVoucher::TYPE_PURCHASE_INVOICE,
            LexofficeVoucher::TYPE_PURCHASE_CREDITNOTE,
            'quotation',
            'orderconfirmation',
            'deliverynote',
            'dunning',
            'downpaymentinvoice',
        ];

        $types = $typesFilter !== null ? $typesFilter : $allTypes;

        $allStatuses = [
            LexofficeVoucher::STATUS_DRAFT,
            LexofficeVoucher::STATUS_OPEN,
            LexofficeVoucher::STATUS_OVERDUE,
            LexofficeVoucher::STATUS_PAID,
            LexofficeVoucher::STATUS_PAIDOFF,
            LexofficeVoucher::STATUS_VOIDED,
        ];

        foreach ($types as $type) {
            foreach ($allStatuses as $status) {
                $page = 0;
                do {
                    try {
                        $data    = $this->client->listVouchers(
                            array_merge($incrementalFilter, ['voucherType' => $type, 'voucherStatus' => $status]),
                            $page,
                            100
                        );
                        $content = $data['content'] ?? [];
                        $stats['total'] += count($content);

                        foreach ($content as $item) {
                            $this->upsertVoucher($item, $companyId, $stats);
                        }

                        $page++;
                        $totalPages = $data['totalPages'] ?? 1;
                    } catch (\Throwable) {
                        // Type/status combination not supported — skip to next status
                        break;
                    }
                } while ($page < $totalPages);
            }
        }

        // Link vouchers to local customers/suppliers via lexoffice_contact_id.
        // This runs after every import so newly fetched vouchers are always linked.
        DB::statement('
            UPDATE lexoffice_vouchers v
            INNER JOIN customers c ON c.lexoffice_contact_id = v.lexoffice_contact_id
            SET v.customer_id = c.id
            WHERE v.lexoffice_contact_id IS NOT NULL
        ');
        DB::statement('
            UPDATE lexoffice_vouchers v
            INNER JOIN suppliers s ON s.lexoffice_contact_id = v.lexoffice_contact_id
            SET v.supplier_id = s.id
            WHERE v.lexoffice_contact_id IS NOT NULL AND v.customer_id IS NULL
        ');

        // Persist the run timestamp so the next call only fetches changes since now.
        AppSetting::set('lexoffice.voucher_import_last_run', $runStarted);

        return $stats;
    }

    /**
     * @param array<string, int> $stats
     */
    private function upsertVoucher(array $item, ?int $companyId, array &$stats): void
    {
        try {
            $lexId = $item['id'] ?? null;
            if (! $lexId) {
                return;
            }

            $isNew = ! LexofficeVoucher::where('lexoffice_voucher_id', $lexId)->exists();

            LexofficeVoucher::updateOrCreate(
                ['lexoffice_voucher_id' => $lexId],
                [
                    'company_id'           => $companyId,
                    'voucher_type'         => $item['voucherType'] ?? null,
                    'voucher_number'       => $item['voucherNumber'] ?? null,
                    'voucher_date'         => isset($item['voucherDate'])
                        ? substr($item['voucherDate'], 0, 10)
                        : null,
                    'due_date'             => isset($item['dueDate'])
                        ? substr($item['dueDate'], 0, 10)
                        : null,
                    'voucher_status'       => $item['voucherStatus'] ?? null,
                    'total_gross_amount'   => (int) round(($item['totalAmount'] ?? 0) * 1_000_000),
                    'open_amount'          => (int) round(($item['openAmount'] ?? 0) * 1_000_000),
                    'currency'             => $item['currency'] ?? 'EUR',
                    'lexoffice_contact_id' => $item['contactId'] ?? null,
                    'contact_name'         => $item['contactName'] ?? null,
                    'raw_json'             => $item,
                    'synced_at'            => now(),
                ]
            );

            $isNew ? $stats['created']++ : $stats['updated']++;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('LexofficeImport: voucher upsert failed', [
                'id'    => $item['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
            $stats['errors']++;
        }
    }

    // =========================================================================
    // Articles
    // =========================================================================

    /** @return array{created: int, updated: int, total: int} */
    public function importArticles(?int $companyId): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'total' => 0];
        $page  = 0;

        do {
            $data    = $this->client->listArticles($page, 100);
            $items   = $data['content'] ?? [];
            $stats['total'] += count($items);

            foreach ($items as $item) {
                $uuid = $item['id'] ?? null;
                if (! $uuid) {
                    continue;
                }
                $existing = LexofficeArticle::where('lexoffice_uuid', $uuid)->exists();

                $price    = $item['price'] ?? [];
                $netPrice = $price['netPrice'] ?? 0;
                $grossPrice = $price['grossPrice'] ?? 0;

                LexofficeArticle::updateOrCreate(
                    ['lexoffice_uuid' => $uuid],
                    [
                        'company_id'       => $companyId,
                        'version'          => $item['version'] ?? 0,
                        'archived'         => $item['archived'] ?? false,
                        'article_number'   => $item['articleNumber'] ?? null,
                        'title'            => $item['title'] ?? '',
                        'description'      => $item['description'] ?? null,
                        'unit_name'        => $item['unitName'] ?? null,
                        'type'             => $item['type'] ?? null,
                        'gtin'             => $item['gtin'] ?? null,
                        'price_net'        => (int) round($netPrice * 1_000_000),
                        'price_gross'      => (int) round($grossPrice * 1_000_000),
                        'tax_rate_percent' => $price['taxRate'] ?? null,
                        'raw_json'         => $item,
                        'synced_at'        => now(),
                    ]
                );

                $existing ? $stats['updated']++ : $stats['created']++;
            }

            $page++;
            $totalPages = $data['totalPages'] ?? 1;
        } while ($page < $totalPages);

        return $stats;
    }

    // =========================================================================
    // Payment Conditions
    // =========================================================================

    /** @return array{created: int, updated: int, total: int} */
    public function importPaymentConditions(?int $companyId): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'total' => 0];
        $page  = 0;

        do {
            $data  = $this->client->listPaymentConditions($page, 100);
            $items = $data['content'] ?? $data ?? [];
            if (! is_array($items)) {
                break;
            }
            $stats['total'] += count($items);

            foreach ($items as $item) {
                $uuid = $item['id'] ?? null;
                if (! $uuid) {
                    continue;
                }
                $existing = LexofficePaymentCondition::where('lexoffice_uuid', $uuid)->exists();

                LexofficePaymentCondition::updateOrCreate(
                    ['lexoffice_uuid' => $uuid],
                    [
                        'company_id'  => $companyId,
                        'name'        => $item['name'] ?? $item['paymentTermLabelTemplate'] ?? '',
                        'description' => $item['description'] ?? null,
                        'raw_json'    => $item,
                        'synced_at'   => now(),
                    ]
                );

                $existing ? $stats['updated']++ : $stats['created']++;
            }

            // Non-paginated endpoint returns flat array
            if (! isset($data['totalPages'])) {
                break;
            }
            $page++;
            $totalPages = $data['totalPages'] ?? 1;
        } while ($page < $totalPages);

        return $stats;
    }

    // =========================================================================
    // Posting Categories
    // =========================================================================

    /** @return array{created: int, updated: int, total: int} */
    public function importPostingCategories(?int $companyId): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'total' => 0];
        $page  = 0;

        do {
            $data  = $this->client->listPostingCategories($page, 100);
            $items = $data['content'] ?? $data ?? [];
            if (! is_array($items)) {
                break;
            }
            $stats['total'] += count($items);

            foreach ($items as $item) {
                $uuid = $item['id'] ?? null;
                if (! $uuid) {
                    continue;
                }
                $existing = LexofficePostingCategory::where('lexoffice_uuid', $uuid)->exists();

                LexofficePostingCategory::updateOrCreate(
                    ['lexoffice_uuid' => $uuid],
                    [
                        'company_id' => $companyId,
                        'name'       => $item['name'] ?? '',
                        'type'       => $item['type'] ?? null,
                        'raw_json'   => $item,
                        'synced_at'  => now(),
                    ]
                );

                $existing ? $stats['updated']++ : $stats['created']++;
            }

            if (! isset($data['totalPages'])) {
                break;
            }
            $page++;
            $totalPages = $data['totalPages'] ?? 1;
        } while ($page < $totalPages);

        return $stats;
    }

    // =========================================================================
    // Print Layouts
    // =========================================================================

    /** @return array{created: int, updated: int, total: int} */
    public function importPrintLayouts(?int $companyId): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'total' => 0];
        $page  = 0;

        do {
            $data  = $this->client->listPrintLayouts($page, 100);
            $items = $data['content'] ?? $data ?? [];
            if (! is_array($items)) {
                break;
            }
            $stats['total'] += count($items);

            foreach ($items as $item) {
                $uuid = $item['id'] ?? null;
                if (! $uuid) {
                    continue;
                }
                $existing = LexofficePrintLayout::where('lexoffice_uuid', $uuid)->exists();

                LexofficePrintLayout::updateOrCreate(
                    ['lexoffice_uuid' => $uuid],
                    [
                        'company_id' => $companyId,
                        'name'       => $item['name'] ?? '',
                        'raw_json'   => $item,
                        'synced_at'  => now(),
                    ]
                );

                $existing ? $stats['updated']++ : $stats['created']++;
            }

            if (! isset($data['totalPages'])) {
                break;
            }
            $page++;
            $totalPages = $data['totalPages'] ?? 1;
        } while ($page < $totalPages);

        return $stats;
    }

    // =========================================================================
    // Recurring Templates
    // =========================================================================

    /** @return array{created: int, updated: int, total: int} */
    public function importRecurringTemplates(?int $companyId): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'total' => 0];
        $page  = 0;

        do {
            $data  = $this->client->listRecurringTemplates($page, 100);
            $items = $data['content'] ?? [];
            $stats['total'] += count($items);

            foreach ($items as $item) {
                $uuid = $item['id'] ?? null;
                if (! $uuid) {
                    continue;
                }
                $existing = LexofficeRecurringTemplate::where('lexoffice_uuid', $uuid)->exists();

                $total = $item['totalPrice'] ?? [];

                LexofficeRecurringTemplate::updateOrCreate(
                    ['lexoffice_uuid' => $uuid],
                    [
                        'company_id'           => $companyId,
                        'version'              => $item['version'] ?? 0,
                        'name'                 => $item['name'] ?? null,
                        'voucher_type'         => $item['voucherType'] ?? 'salesinvoice',
                        'frequency'            => $item['recurringTemplateSettings']['executionInterval'] ?? null,
                        'start_date'           => $item['recurringTemplateSettings']['startDate'] ?? null,
                        'end_date'             => $item['recurringTemplateSettings']['endDate'] ?? null,
                        'next_execution_date'  => $item['recurringTemplateSettings']['nextExecutionDate'] ?? null,
                        'last_execution_date'  => $item['recurringTemplateSettings']['lastExecutionDate'] ?? null,
                        'total_net_amount'     => isset($total['totalNetAmount'])
                            ? (int) round($total['totalNetAmount'] * 1_000_000)
                            : null,
                        'total_gross_amount'   => isset($total['totalGrossAmount'])
                            ? (int) round($total['totalGrossAmount'] * 1_000_000)
                            : null,
                        'currency'             => $total['currency'] ?? 'EUR',
                        'lexoffice_contact_id' => $item['address']['contactId'] ?? null,
                        'raw_json'             => $item,
                        'synced_at'            => now(),
                    ]
                );

                $existing ? $stats['updated']++ : $stats['created']++;
            }

            $page++;
            $totalPages = $data['totalPages'] ?? 1;
        } while ($page < $totalPages);

        return $stats;
    }

    // =========================================================================
    // Countries
    // =========================================================================

    /** @return array{created: int, updated: int, total: int} */
    public function importCountries(): array
    {
        $stats   = ['created' => 0, 'updated' => 0, 'total' => 0];
        $countries = $this->client->listCountries();

        if (! is_array($countries)) {
            return $stats;
        }

        foreach ($countries as $country) {
            $code = $country['countryCode'] ?? null;
            if (! $code) {
                continue;
            }
            $stats['total']++;
            $existing = LexofficeCountry::where('country_code', $code)->exists();

            LexofficeCountry::updateOrCreate(
                ['country_code' => $code],
                [
                    'country_name_de'    => $country['countryNameDE'] ?? $country['countryNameEN'] ?? '',
                    'country_name_en'    => $country['countryNameEN'] ?? '',
                    'tax_classification' => $country['taxClassification'] ?? null,
                    'synced_at'          => now(),
                ]
            );

            $existing ? $stats['updated']++ : $stats['created']++;
        }

        return $stats;
    }

    // =========================================================================
    // Reconcile — link lexoffice_contacts to local customers/suppliers
    // =========================================================================

    /**
     * Match lexoffice_contacts to local Customer/Supplier records.
     * Sets lexoffice_contacts.customer_id / supplier_id
     * and customers/suppliers.lexoffice_contact_id.
     *
     * @return array{matched_customers: int, created_customers: int, matched_suppliers: int, created_suppliers: int}
     */
    public function reconcileContacts(?int $companyId, bool $createMissing = true): array
    {
        $stats = [
            'matched_customers'  => 0,
            'created_customers'  => 0,
            'matched_suppliers'  => 0,
            'created_suppliers'  => 0,
        ];

        // Pre-load local maps for O(1) lookup
        $customerByLexId  = Customer::whereNotNull('lexoffice_contact_id')
            ->pluck('id', 'lexoffice_contact_id')->all();
        $customerByEmail  = Customer::whereNotNull('email')
            ->get(['id', 'email'])
            ->keyBy(fn ($c) => mb_strtolower($c->email))
            ->map(fn ($c) => $c->id)
            ->all();
        $customerByNumber = Customer::whereNotNull('customer_number')
            ->pluck('id', 'customer_number')->all();

        $supplierByLexId = Supplier::whereNotNull('lexoffice_contact_id')
            ->pluck('id', 'lexoffice_contact_id')->all();
        $supplierByEmail = Supplier::whereNotNull('email')
            ->get(['id', 'email'])
            ->keyBy(fn ($s) => mb_strtolower($s->email))
            ->map(fn ($s) => $s->id)
            ->all();
        $supplierByName  = Supplier::all(['id', 'name'])
            ->keyBy(fn ($s) => mb_strtolower($s->name))
            ->map(fn ($s) => $s->id)
            ->all();

        $defaultGroupId = \App\Models\Pricing\CustomerGroup::where('active', true)
            ->orderBy('id')->value('id') ?? 1;

        LexofficeContact::chunk(200, function ($contacts) use (
            &$stats, $companyId, $defaultGroupId,
            $createMissing,
            &$customerByLexId, &$customerByEmail, &$customerByNumber,
            &$supplierByLexId, &$supplierByEmail, &$supplierByName
        ) {
            foreach ($contacts as $lc) {
                // ── Match customer ────────────────────────────────────────────
                if ($lc->is_customer && ! $lc->customer_id) {
                    $customerId = $this->matchCustomer(
                        $lc, $customerByLexId, $customerByEmail, $customerByNumber
                    );

                    if ($customerId) {
                        $lc->update(['customer_id' => $customerId]);
                        Customer::where('id', $customerId)
                            ->update(['lexoffice_contact_id' => $lc->lexoffice_uuid]);

                        // Reconcile-UI: confirmed source_match eintragen
                        SourceMatch::updateOrCreate(
                            [
                                'entity_type' => SourceMatch::ENTITY_CUSTOMER,
                                'source'      => 'lexoffice',
                                'source_id'   => $lc->lexoffice_uuid,
                            ],
                            [
                                'local_id'        => $customerId,
                                'status'          => SourceMatch::STATUS_CONFIRMED,
                                'matched_by'      => null,
                                'source_snapshot' => (array) $lc->toArray(),
                                'confirmed_at'    => now(),
                            ]
                        );

                        $stats['matched_customers']++;
                    } elseif ($createMissing) {
                        $newCustomer = $this->createCustomerFromContact($lc, $companyId, $defaultGroupId);
                        $lc->update(['customer_id' => $newCustomer->id]);
                        $customerByLexId[$lc->lexoffice_uuid] = $newCustomer->id;
                        if ($newCustomer->email) {
                            $customerByEmail[mb_strtolower($newCustomer->email)] = $newCustomer->id;
                        }
                        $customerByNumber[$newCustomer->customer_number] = $newCustomer->id;
                        $stats['created_customers']++;
                    }
                }

                // ── Match supplier ────────────────────────────────────────────
                if ($lc->is_vendor && ! $lc->supplier_id) {
                    $supplierId = $this->matchSupplier(
                        $lc, $supplierByLexId, $supplierByEmail, $supplierByName
                    );

                    if ($supplierId) {
                        $lc->update(['supplier_id' => $supplierId]);
                        Supplier::where('id', $supplierId)
                            ->update(['lexoffice_contact_id' => $lc->lexoffice_uuid]);
                        $stats['matched_suppliers']++;
                    } elseif ($createMissing) {
                        $newSupplier = $this->createSupplierFromContact($lc, $companyId);
                        $lc->update(['supplier_id' => $newSupplier->id]);
                        $supplierByLexId[$lc->lexoffice_uuid] = $newSupplier->id;
                        if ($newSupplier->email) {
                            $supplierByEmail[mb_strtolower($newSupplier->email)] = $newSupplier->id;
                        }
                        $supplierByName[mb_strtolower($newSupplier->name)] = $newSupplier->id;
                        $stats['created_suppliers']++;
                    }
                }
            }
        });

        return $stats;
    }

    // =========================================================================
    // Reconcile helpers
    // =========================================================================

    /**
     * @param  array<string, int>  $byLexId
     * @param  array<string, int>  $byEmail
     * @param  array<string, int>  $byNumber
     */
    private function matchCustomer(
        LexofficeContact $lc,
        array $byLexId,
        array $byEmail,
        array $byNumber,
    ): ?int {
        // 1. Lexoffice UUID already on a customer
        if (isset($byLexId[$lc->lexoffice_uuid])) {
            return $byLexId[$lc->lexoffice_uuid];
        }
        // 2. Email
        if ($lc->primary_email) {
            $key = mb_strtolower($lc->primary_email);
            if (isset($byEmail[$key])) {
                return $byEmail[$key];
            }
        }
        // 3. K-number from company name
        if ($lc->company_name && preg_match('/\b(K\d{3,5})\b/i', $lc->company_name, $m)) {
            $kn = strtoupper($m[1]);
            if (isset($byNumber[$kn])) {
                return $byNumber[$kn];
            }
        }
        // 4. Customer number from Lexoffice
        if ($lc->customer_number) {
            $kn = is_numeric($lc->customer_number)
                ? 'K' . $lc->customer_number
                : $lc->customer_number;
            if (isset($byNumber[$kn])) {
                return $byNumber[$kn];
            }
        }
        return null;
    }

    /**
     * @param  array<string, int>  $byLexId
     * @param  array<string, int>  $byEmail
     * @param  array<string, int>  $byName
     */
    private function matchSupplier(
        LexofficeContact $lc,
        array $byLexId,
        array $byEmail,
        array $byName,
    ): ?int {
        if (isset($byLexId[$lc->lexoffice_uuid])) {
            return $byLexId[$lc->lexoffice_uuid];
        }
        if ($lc->primary_email) {
            $key = mb_strtolower($lc->primary_email);
            if (isset($byEmail[$key])) {
                return $byEmail[$key];
            }
        }
        if ($lc->company_name && isset($byName[mb_strtolower($lc->company_name)])) {
            return $byName[mb_strtolower($lc->company_name)];
        }
        return null;
    }

    private function createCustomerFromContact(
        LexofficeContact $lc,
        ?int $companyId,
        int $defaultGroupId
    ): Customer {
        $lexNr = $lc->customer_number;
        if ($lexNr && is_numeric($lexNr)) {
            $customerNumber = 'K' . $lexNr;
        } elseif ($lexNr) {
            $customerNumber = $lexNr;
        } else {
            $customerNumber = 'LEX-' . strtoupper(substr($lc->lexoffice_uuid, 0, 8));
        }

        $base = $customerNumber;
        $i    = 1;
        while (Customer::where('customer_number', $customerNumber)->exists()) {
            $customerNumber = $base . '-' . $i++;
        }

        return Customer::create([
            'company_id'           => $companyId,
            'customer_group_id'    => $defaultGroupId,
            'customer_number'      => $customerNumber,
            'company_name'         => $lc->company_name,
            'first_name'           => $lc->first_name,
            'last_name'            => $lc->last_name,
            'email'                => $lc->primary_email,
            'phone'                => $lc->primary_phone,
            'price_display_mode'   => 'gross',
            'lexoffice_contact_id' => $lc->lexoffice_uuid,
            'active'               => true,
        ]);
    }

    private function createSupplierFromContact(LexofficeContact $lc, ?int $companyId): Supplier
    {
        $name = $lc->company_name
            ?? trim(($lc->first_name ?? '') . ' ' . ($lc->last_name ?? ''))
            ?: 'Lexoffice-Lieferant';

        return Supplier::create([
            'company_id'           => $companyId,
            'type'                 => Supplier::TYPE_SUPPLIER,
            'lieferanten_nr'       => $lc->vendor_number,
            'name'                 => $name,
            'email'                => $lc->primary_email,
            'phone'                => $lc->primary_phone,
            'currency'             => 'EUR',
            'lexoffice_contact_id' => $lc->lexoffice_uuid,
            'active'               => true,
        ]);
    }

    // =========================================================================
    // Payments — per-voucher payment history
    // =========================================================================

    /**
     * Import payment records from Lexoffice into lexoffice_payments.
     *
     * Processes vouchers in batches to avoid HTTP gateway timeouts.
     * Already-fetched vouchers are skipped (tracked via payments_fetched_at).
     * Pass $limit = 0 for unlimited (CLI use only — can run for hours).
     *
     * @return array{total_vouchers: int, processed: int, created: int, updated: int, skipped: int, errors: int, remaining: int}
     */
    public function importPayments(?int $companyId, int $limit = 30): array
    {
        $stats = [
            'total_vouchers' => 0,
            'processed'      => 0,
            'created'        => 0,
            'updated'        => 0,
            'skipped'        => 0,
            'errors'         => 0,
            'remaining'      => 0,
        ];

        // Only paid/paidoff vouchers have actual paymentItems — open/overdue return empty arrays
        $query = LexofficeVoucher::whereIn('voucher_status', ['paid', 'paidoff'])
            ->whereNull('payments_fetched_at')
            ->select(['id', 'lexoffice_voucher_id', 'voucher_type', 'contact_name']);

        $stats['total_vouchers'] = LexofficeVoucher::whereIn('voucher_status', ['paid', 'paidoff'])->count();

        if ($limit > 0) {
            $stats['remaining'] = $query->count();
            $query = $query->limit($limit);
        }

        $vouchers = $query->get();

        foreach ($vouchers as $voucher) {
            try {
                $data  = $this->client->getPayments($voucher->lexoffice_voucher_id);
                $items = $data['paymentItems'] ?? [];

                // openAmount and amounts from Lexoffice are decimal EUR → convert to milli-cent (1 EUR = 1_000_000)
                $openAmt = isset($data['openAmount'])
                    ? (int) round((float) $data['openAmount'] * 1_000_000)
                    : 0;

                if (empty($items)) {
                    $stats['skipped']++;
                } else {
                    // Sum all payment items for the total amount column
                    $totalAmount = array_sum(array_map(
                        fn ($item) => (int) round((float) ($item['amount'] ?? 0) * 1_000_000),
                        $items
                    ));

                    $existing = LexofficePayment::where('lexoffice_voucher_id', $voucher->lexoffice_voucher_id)->exists();

                    // paidDate: ISO datetime string from API → extract date portion
                    $paidDate = null;
                    if (isset($data['paidDate'])) {
                        try {
                            $paidDate = (new \DateTime($data['paidDate']))->format('Y-m-d');
                        } catch (\Throwable) {}
                    }

                    LexofficePayment::updateOrCreate(
                        ['lexoffice_voucher_id' => $voucher->lexoffice_voucher_id],
                        [
                            'company_id'            => $companyId,
                            'voucher_type'          => $voucher->voucher_type,
                            'contact_name'          => $voucher->contact_name,
                            'payment_date'          => $paidDate,
                            'amount'                => $totalAmount,
                            'currency'              => $data['currency'] ?? 'EUR',
                            'payment_type'          => $data['paymentStatus'] ?? null,
                            'open_item_description' => null,
                            'open_amount'           => $openAmt,
                            'raw_json'              => $data,
                            'synced_at'             => now(),
                        ]
                    );

                    $existing ? $stats['updated']++ : $stats['created']++;
                }

                $voucher->update(['payments_fetched_at' => now()]);
                $stats['processed']++;

            } catch (\Throwable) {
                $stats['errors']++;
                $voucher->update(['payments_fetched_at' => now()]);
            }
        }

        if ($limit > 0) {
            $stats['remaining'] = max(0, $stats['remaining'] - $stats['processed'] - $stats['errors']);
        }

        return $stats;
    }

    // =========================================================================
    // Extraction helpers
    // =========================================================================

    /** @param array<string, mixed> $contact */
    private function extractFirstEmail(array $contact): ?string
    {
        foreach ($contact['emailAddresses'] ?? [] as $bucket) {
            if (is_array($bucket)) {
                foreach ($bucket as $email) {
                    if (is_string($email) && $email !== '') {
                        return mb_strtolower(trim($email));
                    }
                }
            }
        }
        return null;
    }

    /** @param array<string, mixed> $contact */
    private function extractFirstPhone(array $contact): ?string
    {
        foreach ($contact['phoneNumbers'] ?? [] as $bucket) {
            if (is_array($bucket)) {
                foreach ($bucket as $phone) {
                    if (is_string($phone) && $phone !== '') {
                        return trim($phone);
                    }
                }
            }
        }
        return null;
    }
}
