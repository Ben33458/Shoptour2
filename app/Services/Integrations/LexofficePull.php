<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Models\Admin\Invoice;
use App\Models\Admin\LexofficeVoucher;
use App\Models\Pricing\Customer;
use App\Models\Pricing\CustomerGroup;
use App\Models\Pricing\CustomerNote;
use App\Models\Supplier\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Pulls data FROM Lexoffice into the local database.
 *
 * Customer matching (in order):
 *   1. lexoffice_contact_id already stored on Customer
 *   2. Email match
 *   3. K-number pattern in company name (e.g. "Müller GmbH (K1234)")
 *   4. Lexoffice numeric customer number prefixed with K (e.g. 1234 → K1234)
 *   → if matched: compare fields, create CustomerNote on any diff
 *   → if not matched: create new Customer
 *
 * Supplier matching (in order):
 *   1. lexoffice_contact_id already stored
 *   2. Email match
 *   3. Exact name match (case-insensitive)
 *   → if not matched: create new Supplier
 *
 * Payment status:
 *   Iterates invoices with lexoffice_voucher_id, updates payment status.
 */
class LexofficePull
{
    public function __construct(
        private readonly LexofficeClient $client,
    ) {}

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * @return array{matched: int, contact_id_assigned: int, created: int, diffs_logged: int, total_lexoffice: int}
     */
    public function pullCustomers(?int $companyId = null): array
    {
        $stats = [
            'matched'             => 0,
            'contact_id_assigned' => 0,
            'created'             => 0,
            'diffs_logged'        => 0,
            'total_lexoffice'     => 0,
        ];

        $defaultGroup = CustomerGroup::where('active', true)->orderBy('id')->first();

        // Pre-load blocked Lexoffice contact IDs — these are never re-imported
        $blockedIds = DB::table('lexoffice_contact_blocks')
            ->whereIn('blocked_entity', ['customer', 'both'])
            ->pluck('lexoffice_contact_id')
            ->flip()   // convert to hash-set for O(1) lookup
            ->all();

        // Pre-load ALL local customers into memory maps — avoids one DB query per contact.
        // Keys are lowercase for case-insensitive lookups.
        $byLexId  = [];   // lexoffice_contact_id → Customer
        $byEmail  = [];   // lowercase email      → Customer
        $byNumber = [];   // customer_number       → Customer

        Customer::select(['id','company_id','customer_number','lexoffice_contact_id',
                           'company_name','first_name','last_name','email','phone'])
            ->each(function (Customer $c) use (&$byLexId, &$byEmail, &$byNumber): void {
                if ($c->lexoffice_contact_id) {
                    $byLexId[$c->lexoffice_contact_id] = $c;
                }
                if ($c->email) {
                    $byEmail[mb_strtolower($c->email)] = $c;
                }
                if ($c->customer_number) {
                    $byNumber[$c->customer_number] = $c;
                }
            });

        $page = 0;
        do {
            $data     = $this->client->listContacts('customer', $page, 100);
            $contacts = $data['content'] ?? [];
            $stats['total_lexoffice'] += count($contacts);

            foreach ($contacts as $contact) {
                $lexId = $contact['id'] ?? null;
                if (! $lexId || isset($blockedIds[$lexId])) {
                    continue;
                }

                // API filter may return all contacts — enforce customer role client-side
                if (! isset($contact['roles']['customer'])) {
                    continue;
                }

                $customer = $this->findCustomerInMaps($contact, $byLexId, $byEmail, $byNumber);

                if ($customer) {
                    $stats['matched']++;
                    if ($customer->lexoffice_contact_id !== $lexId) {
                        $customer->update(['lexoffice_contact_id' => $lexId]);
                        $byLexId[$lexId] = $customer; // keep map current
                        $stats['contact_id_assigned']++;
                    }
                    if ($this->compareAndLogCustomerDiff($customer, $contact, $lexId)) {
                        $stats['diffs_logged']++;
                    }
                } else {
                    $newCustomer = $this->createCustomerFromContact($contact, $lexId, $defaultGroup?->id ?? 1, $companyId);
                    // Register new customer in maps so duplicates in the same import are caught
                    $byLexId[$lexId] = $newCustomer;
                    if ($newCustomer->email) {
                        $byEmail[mb_strtolower($newCustomer->email)] = $newCustomer;
                    }
                    $byNumber[$newCustomer->customer_number] = $newCustomer;
                    $stats['created']++;
                }

            }

            $page++;
            $totalPages = $data['totalPages'] ?? 1;
        } while ($page < $totalPages);

        return $stats;
    }

    /**
     * @return array{matched: int, contact_id_assigned: int, created: int, total_lexoffice: int}
     */
    public function pullSuppliers(?int $companyId = null): array
    {
        $stats = ['matched' => 0, 'contact_id_assigned' => 0, 'created' => 0, 'total_lexoffice' => 0];

        // Pre-load blocked Lexoffice contact IDs
        $blockedIds = DB::table('lexoffice_contact_blocks')
            ->whereIn('blocked_entity', ['supplier', 'both'])
            ->pluck('lexoffice_contact_id')
            ->flip()
            ->all();

        // Pre-load all customer lexoffice_contact_ids — contacts that are already
        // imported as customers are skipped here (pure vendors only in supplier list).
        $customerLexIds = Customer::whereNotNull('lexoffice_contact_id')
            ->pluck('lexoffice_contact_id')
            ->flip()
            ->all();

        // Pre-load all suppliers into memory maps
        $byLexId   = [];
        $byLexNr   = [];  // lieferanten_nr (Lexoffice vendor number as string) → Supplier
        $byEmail   = [];
        $byName    = [];

        Supplier::select(['id', 'name', 'email', 'lieferanten_nr', 'lexoffice_contact_id'])
            ->each(function (Supplier $s) use (&$byLexId, &$byLexNr, &$byEmail, &$byName): void {
                if ($s->lexoffice_contact_id) {
                    $byLexId[$s->lexoffice_contact_id] = $s;
                }
                if ($s->lieferanten_nr) {
                    $byLexNr[$s->lieferanten_nr] = $s;
                }
                if ($s->email) {
                    $byEmail[mb_strtolower($s->email)] = $s;
                }
                $byName[mb_strtolower($s->name)] = $s;
            });

        $page = 0;
        do {
            $data     = $this->client->listContacts('vendor', $page, 100);
            $contacts = $data['content'] ?? [];
            $stats['total_lexoffice'] += count($contacts);

            foreach ($contacts as $contact) {
                $lexId = $contact['id'] ?? null;
                if (! $lexId || isset($blockedIds[$lexId])) {
                    continue;
                }

                // API filter may return all contacts — enforce vendor role client-side
                if (! isset($contact['roles']['vendor'])) {
                    continue;
                }

                // Skip contacts that already exist as customers — pure vendors only.
                if (isset($customerLexIds[$lexId])) {
                    continue;
                }

                $supplier = $this->findSupplierInMaps($contact, $byLexId, $byLexNr, $byEmail, $byName);

                if ($supplier) {
                    $stats['matched']++;
                    $updates = [];
                    if ($supplier->lexoffice_contact_id !== $lexId) {
                        $updates['lexoffice_contact_id'] = $lexId;
                        $byLexId[$lexId] = $supplier;
                        $stats['contact_id_assigned']++;
                    }
                    // Keep type as-is if user manually changed it; only set automatically on first import
                    if ($updates) {
                        $supplier->update($updates);
                    }
                } else {
                    $newSupplier = $this->createSupplierFromContact($contact, $lexId, $companyId);
                    $byLexId[$lexId] = $newSupplier;
                    if ($newSupplier->lieferanten_nr) {
                        $byLexNr[$newSupplier->lieferanten_nr] = $newSupplier;
                    }
                    if ($newSupplier->email) {
                        $byEmail[mb_strtolower($newSupplier->email)] = $newSupplier;
                    }
                    $byName[mb_strtolower($newSupplier->name)] = $newSupplier;
                    $stats['created']++;
                }
            }

            $page++;
            $totalPages = $data['totalPages'] ?? 1;
        } while ($page < $totalPages);

        return $stats;
    }

    /**
     * @return array{updated: int, already_up_to_date: int, not_found: int, errors: int}
     */
    public function pullPaymentStatus(): array
    {
        $stats = ['updated' => 0, 'already_up_to_date' => 0, 'not_found' => 0, 'errors' => 0];

        Invoice::whereNotNull('lexoffice_voucher_id')
            ->where(function ($q) {
                $q->whereNull('lexoffice_payment_status')
                  ->orWhere('lexoffice_payment_status', 'open');
            })
            ->orderByDesc('finalized_at')
            ->chunk(50, function ($invoices) use (&$stats) {
                foreach ($invoices as $invoice) {
                    try {
                        $voucher   = $this->client->getVoucher($invoice->lexoffice_voucher_id);
                        $newStatus = $voucher['voucherStatus'] ?? null;
                        $paidDate  = null;

                        if (in_array($newStatus, ['paid', 'paidoff'], true)) {
                            $paidDate = now();
                        }

                        if ($invoice->lexoffice_payment_status === $newStatus) {
                            $stats['already_up_to_date']++;
                            continue;
                        }

                        $invoice->update([
                            'lexoffice_payment_status' => $newStatus,
                            'lexoffice_paid_at'        => $paidDate,
                            'lexoffice_synced_at'      => now(),
                            'lexoffice_sync_error'     => null,
                        ]);
                        $stats['updated']++;
                    } catch (\Throwable $e) {
                        if (str_contains($e->getMessage(), 'HTTP 404')) {
                            $stats['not_found']++;
                        } else {
                            $stats['errors']++;
                        }
                    }
                }
            });

        return $stats;
    }

    /**
     * Pull all vouchers (sales invoices, credit notes, purchase invoices,
     * purchase credit notes) from Lexoffice and upsert into local DB.
     *
     * @return array{created: int, updated: int, total_lexoffice: int, errors: int}
     */
    public function pullVouchers(?int $companyId = null): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'total_lexoffice' => 0, 'errors' => 0];

        // Pre-load contact → customer/supplier maps for matching
        $customerMap = Customer::whereNotNull('lexoffice_contact_id')
            ->pluck('id', 'lexoffice_contact_id')
            ->all();

        $supplierMap = Supplier::whereNotNull('lexoffice_contact_id')
            ->pluck('id', 'lexoffice_contact_id')
            ->all();

        $types = [
            LexofficeVoucher::TYPE_SALES_INVOICE,
            LexofficeVoucher::TYPE_CREDIT_NOTE,
            LexofficeVoucher::TYPE_PURCHASE_INVOICE,
            LexofficeVoucher::TYPE_PURCHASE_CREDITNOTE,
        ];

        foreach ($types as $type) {
            $page = 0;
            do {
                $data    = $this->client->listVouchers(['voucherType' => $type], $page, 100);
                $content = $data['content'] ?? [];
                $stats['total_lexoffice'] += count($content);

                foreach ($content as $item) {
                    $this->upsertVoucher($item, $companyId, $customerMap, $supplierMap, $stats);
                }

                $page++;
                $totalElements = $data['totalElements'] ?? 0;
                $totalPages    = $data['totalPages'] ?? (int) ceil($totalElements / 100) ?: 1;
            } while ($page < $totalPages);
        }

        return $stats;
    }

    /**
     * Upsert a single Lexoffice voucher into the local database.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, int>    $customerMap  lexoffice_contact_id => customer_id
     * @param  array<string, int>    $supplierMap  lexoffice_contact_id => supplier_id
     * @param  array<string, int>    $stats
     */
    private function upsertVoucher(
        array $item,
        ?int $companyId,
        array $customerMap,
        array $supplierMap,
        array &$stats,
    ): void {
        try {
            $lexId     = $item['id'] ?? null;
            $contactId = $item['contactId'] ?? null;

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
                    'voucher_date'         => isset($item['voucherDate']) ? substr($item['voucherDate'], 0, 10) : null,
                    'due_date'             => isset($item['dueDate']) ? substr($item['dueDate'], 0, 10) : null,
                    'voucher_status'       => $item['voucherStatus'] ?? null,
                    'total_gross_amount'   => (int) round(($item['totalAmount'] ?? 0) * 1_000_000),
                    'open_amount'          => (int) round(($item['openAmount'] ?? 0) * 1_000_000),
                    'currency'             => $item['currency'] ?? 'EUR',
                    'lexoffice_contact_id' => $contactId,
                    'contact_name'         => $item['contactName'] ?? null,
                    'customer_id'          => $customerMap[$contactId] ?? null,
                    'supplier_id'          => $supplierMap[$contactId] ?? null,
                    'synced_at'            => now(),
                ],
            );

            $isNew ? $stats['created']++ : $stats['updated']++;
        } catch (\Throwable $e) {
            $stats['errors']++;
        }
    }

    // =========================================================================
    // Diff comparison
    // =========================================================================

    /**
     * Compare a matched customer's fields against the Lexoffice contact.
     * Creates a CustomerNote of type 'lexoffice_diff' if any fields differ.
     * Returns true if a diff note was created.
     *
     * @param  array<string, mixed> $contact
     */
    private function compareAndLogCustomerDiff(Customer $customer, array $contact, string $lexId): bool
    {
        $lexEmail       = $this->extractEmails($contact)[0] ?? null;
        $lexPhone       = $this->extractPhones($contact)[0] ?? null;
        $lexFirstName   = $contact['person']['firstName'] ?? null;
        $lexLastName    = $contact['person']['lastName']  ?? null;
        $lexCompanyName = $contact['company']['name']     ?? null;

        $diffs = [];

        $this->checkDiff($diffs, 'E-Mail',      $customer->email,        $lexEmail);
        $this->checkDiff($diffs, 'Telefon',     $customer->phone,        $lexPhone);
        $this->checkDiff($diffs, 'Vorname',     $customer->first_name,   $lexFirstName);
        $this->checkDiff($diffs, 'Nachname',    $customer->last_name,    $lexLastName);
        $this->checkDiff($diffs, 'Firmenname',  $customer->company_name, $lexCompanyName);

        if (empty($diffs)) {
            return false;
        }

        // Build readable body
        $lines = ["Abweichungen beim Lexoffice-Abgleich (Kontakt-ID: {$lexId}):"];
        foreach ($diffs as $diff) {
            $local = $diff['local'] !== null ? "\"{$diff['local']}\"" : '(leer)';
            $lex   = $diff['lexoffice'] !== null ? "\"{$diff['lexoffice']}\"" : '(leer)';
            $lines[] = "- {$diff['field']}: lokal {$local} → Lexoffice {$lex}";
        }

        CustomerNote::create([
            'company_id'  => $customer->company_id,
            'customer_id' => $customer->id,
            'type'        => CustomerNote::TYPE_LEXOFFICE_DIFF,
            'subject'     => 'Lexoffice-Abweichung (' . count($diffs) . ' Feld' . (count($diffs) > 1 ? 'er' : '') . ')',
            'body'        => implode("\n", $lines),
            'meta_json'   => [
                'lexoffice_id' => $lexId,
                'diffs'        => $diffs,
            ],
        ]);

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $diffs
     */
    private function checkDiff(array &$diffs, string $label, ?string $local, ?string $lexoffice): void
    {
        // Normalize: trim + lowercase for comparison, but store original for display
        $localNorm = $local !== null ? mb_strtolower(trim($local)) : null;
        $lexNorm   = $lexoffice !== null ? mb_strtolower(trim($lexoffice)) : null;

        if ($localNorm !== $lexNorm) {
            $diffs[] = [
                'field'     => $label,
                'local'     => $local,
                'lexoffice' => $lexoffice,
            ];
        }
    }

    // =========================================================================
    // Record creation
    // =========================================================================

    /** @param array<string, mixed> $contact */
    private function createCustomerFromContact(array $contact, string $lexId, int $defaultGroupId, ?int $companyId = null): Customer
    {
        $emails      = $this->extractEmails($contact);
        $phones      = $this->extractPhones($contact);
        $kNumber     = $this->extractKNumber($contact);
        $companyName = $contact['company']['name'] ?? null;
        $firstName   = $contact['person']['firstName'] ?? null;
        $lastName    = $contact['person']['lastName']  ?? null;

        // Derive customer_number (priority: K-number in name → Lexoffice numeric → LEX-UUID)
        $lexNr = $contact['roles']['customer']['number'] ?? null;
        if ($kNumber) {
            $customerNumber = $kNumber;
        } elseif ($lexNr && is_numeric($lexNr)) {
            $customerNumber = 'K' . $lexNr;
        } else {
            $customerNumber = 'LEX-' . strtoupper(substr($lexId, 0, 8));
        }

        // Safety: uniqueness check via a single DB query (edge case only)
        $base = $customerNumber;
        $i    = 1;
        while (Customer::where('customer_number', $customerNumber)->exists()) {
            $customerNumber = $base . '-' . $i++;
        }

        return Customer::create([
            'company_id'           => $companyId,
            'customer_group_id'    => $defaultGroupId,
            'customer_number'      => $customerNumber,
            'company_name'         => $companyName,
            'first_name'           => $firstName,
            'last_name'            => $lastName,
            'email'                => $emails[0] ?? null,
            'phone'                => $phones[0] ?? null,
            'price_display_mode'   => 'gross',
            'lexoffice_contact_id' => $lexId,
            'active'               => true,
        ]);
    }

    /**
     * @param array<string, mixed> $contact
     * @param string $type  Supplier::TYPE_SUPPLIER or TYPE_PARTNER
     */
    private function createSupplierFromContact(array $contact, string $lexId, ?int $companyId = null, string $type = Supplier::TYPE_SUPPLIER): Supplier
    {
        $emails   = $this->extractEmails($contact);
        $phones   = $this->extractPhones($contact);
        $name     = $contact['company']['name']
            ?? trim(($contact['person']['firstName'] ?? '') . ' ' . ($contact['person']['lastName'] ?? ''))
            ?: 'Lexoffice-Lieferant';
        $vendorNr = isset($contact['roles']['vendor']['number'])
            ? (string) $contact['roles']['vendor']['number']
            : null;

        return Supplier::create([
            'company_id'           => $companyId,
            'type'                 => $type,
            'lieferanten_nr'       => $vendorNr,
            'name'                 => $name,
            'email'                => $emails[0] ?? null,
            'phone'                => $phones[0] ?? null,
            'currency'             => 'EUR',
            'lexoffice_contact_id' => $lexId,
            'active'               => true,
        ]);
    }

    // =========================================================================
    // Matching helpers (in-memory maps — no per-contact DB queries)
    // =========================================================================

    /**
     * @param  array<string, Customer>  $byLexId
     * @param  array<string, Customer>  $byEmail
     * @param  array<string, Customer>  $byNumber
     */
    private function findCustomerInMaps(
        array $contact,
        array $byLexId,
        array $byEmail,
        array $byNumber,
    ): ?Customer {
        $lexId   = $contact['id'];
        $emails  = $this->extractEmails($contact);
        $kNumber = $this->extractKNumber($contact);
        $lexNr   = $contact['roles']['customer']['number'] ?? null;

        // 1. Already linked by Lexoffice ID
        if (isset($byLexId[$lexId])) return $byLexId[$lexId];

        // 2. Email match
        foreach ($emails as $email) {
            if (isset($byEmail[$email])) return $byEmail[$email];
        }

        // 3. K-number from company name
        if ($kNumber && isset($byNumber[$kNumber])) return $byNumber[$kNumber];

        // 4. Lexoffice numeric → K-prefixed
        if ($lexNr && is_numeric($lexNr) && isset($byNumber['K' . $lexNr])) {
            return $byNumber['K' . $lexNr];
        }

        return null;
    }

    /**
     * @param  array<string, Supplier>  $byLexId
     * @param  array<string, Supplier>  $byLexNr
     * @param  array<string, Supplier>  $byEmail
     * @param  array<string, Supplier>  $byName
     */
    private function findSupplierInMaps(
        array $contact,
        array $byLexId,
        array $byLexNr,
        array $byEmail,
        array $byName,
    ): ?Supplier {
        $lexId    = $contact['id'];
        $emails   = $this->extractEmails($contact);
        $name     = $contact['company']['name'] ?? null;
        $vendorNr = isset($contact['roles']['vendor']['number'])
            ? (string) $contact['roles']['vendor']['number']
            : null;

        // 1. Already linked by Lexoffice UUID
        if (isset($byLexId[$lexId])) return $byLexId[$lexId];

        // 2. Lexoffice vendor number matches lieferanten_nr
        if ($vendorNr && isset($byLexNr[$vendorNr])) return $byLexNr[$vendorNr];

        // 3. Email match
        foreach ($emails as $email) {
            if (isset($byEmail[$email])) return $byEmail[$email];
        }

        // 4. Exact name match (last resort)
        if ($name && isset($byName[mb_strtolower($name)])) {
            return $byName[mb_strtolower($name)];
        }

        return null;
    }

    // =========================================================================
    // Data extraction
    // =========================================================================

    /** @return string[] */
    private function extractEmails(array $contact): array
    {
        $emails = [];
        foreach ($contact['emailAddresses'] ?? [] as $bucket) {
            if (is_array($bucket)) {
                foreach ($bucket as $email) {
                    if (is_string($email) && $email !== '') {
                        $emails[] = mb_strtolower(trim($email));
                    }
                }
            }
        }
        return array_values(array_unique(array_filter($emails)));
    }

    /** @return string[] */
    private function extractPhones(array $contact): array
    {
        $phones = [];
        foreach ($contact['phoneNumbers'] ?? [] as $bucket) {
            if (is_array($bucket)) {
                foreach ($bucket as $phone) {
                    if (is_string($phone) && $phone !== '') {
                        $phones[] = trim($phone);
                    }
                }
            }
        }
        return array_values(array_unique(array_filter($phones)));
    }

    private function extractKNumber(array $contact): ?string
    {
        $haystack = implode(' ', array_filter([
            $contact['company']['name']        ?? null,
            $contact['company']['description'] ?? null,
            $contact['note']                   ?? null,
        ]));

        if (preg_match('/\b(K\d{3,5})\b/i', $haystack, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }
}
