<?php

declare(strict_types=1);

namespace App\Services\Reconcile;

use App\Models\Pricing\Customer;
use App\Models\ReconcileFeedbackLog;
use App\Models\SourceMatch;
use Illuminate\Support\Facades\DB;

/**
 * Matches external customer records (Ninox, JTL-WaWi, Lexoffice) against local customers.
 *
 * Matching priority:
 *   0. Customer-Number  (100%)  — direct match on source customer_number field
 *   0b. Trailing-Number (100%)  — 5–6-digit number in local company_name + name similarity ≥ 60%
 *   1. Email + Name ≥ 50% (90%)
 *   2. Email + Phone on same candidate (92%)
 *   3. Phone (85%)
 *   4. Email alone (70%)
 *   5. Fuzzy Name ≥ 80% (as-is %)
 *
 * Sources:
 *   ninox     — ninox_kunden (kundennummer field, any format)
 *   wawi      — wawi_kunden JOIN wawi_dbo_tadresse (nStandard=1) for contact data
 *   lexoffice — lexoffice_contacts (customer_number extracted from company_name trailing)
 *
 * Excluded emails:
 *   - Addresses appearing more than once in source table (shared addresses)
 *   - Generic prefixes: info@, verwaltung@, buchhaltung@ etc.
 *
 * Phone numbers are normalised (digits only, +49/0049 → 0…).
 */
class CustomerReconcileService
{
    // ── Generic email prefixes ───────────────────────────────────────────────

    private const GENERIC_EMAIL_PREFIXES = [
        'info', 'office', 'kontakt', 'contact', 'mail', 'post', 'service',
        'verwaltung', 'buchhaltung', 'sekretariat', 'empfang', 'reception',
        'hallo', 'hello', 'anfrage', 'anfragen', 'team', 'support',
        'noreply', 'no-reply', 'newsletter', 'bestellung', 'bestellungen',
        'rechnung', 'rechnungen', 'fibu', 'vertrieb', 'einkauf',
        'zentrale', 'allgemein', 'general', 'redaktion', 'presse',
    ];

    // ── Lazy-loaded lookup maps ───────────────────────────────────────────────

    /**
     * lower(trim(customer_number)) → customer.id
     * Covers ALL non-empty customer_number values — no format restriction.
     */
    private array $byCustomerNumber = [];

    /** lower(email) → customer.id  (unique, non-generic emails only) */
    private array $byEmail = [];

    /** normalized_phone → customer.id */
    private array $byPhone = [];

    /**
     * [{id, name, trailing_nr}]  für Fuzzy-Pass.
     * name         = company_name after stripKnr + normalizeName
     * trailing_nr  = stripped 5–6-digit number (e.g. "11314") or null
     */
    private array $allNames = [];

    /** plain_number (5–6 digits) → customer.id  (trailing number in local company_name) */
    private array $byTrailingNumber = [];

    /** id → Customer */
    private array $byId = [];

    /** Emails appearing more than once in source table — never use for matching */
    private array $sharedSourceEmails = [];

    private bool $mapsBuilt = false;

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * @param  string  $source   'ninox' | 'wawi' | 'lexoffice'
     * @param  array{unmatched_only?: bool, status?: string}  $filters
     * @return list<array{source:string, source_id:string, source_data:array,
     *   match:Customer|null, confidence:int, match_method:string,
     *   diff:array, existing_match:SourceMatch|null}>
     */
    public function proposeMatches(string $source, array $filters = []): array
    {
        $this->buildLookupMaps($source);

        $rows = $this->sourceRows($source);

        $existingMatches = SourceMatch::where('entity_type', SourceMatch::ENTITY_CUSTOMER)
            ->where('source', $source)
            ->get()
            ->keyBy('source_id');

        // local_id → source_id für bereits bestätigte Matches (Duplikat-Erkennung)
        $confirmedByLocalId = $existingMatches
            ->where('status', SourceMatch::STATUS_CONFIRMED)
            ->pluck('source_id', 'local_id')
            ->all();

        $results = [];

        foreach ($rows as $row) {
            $sourceId = $this->sourceId($source, $row);
            $existing = $existingMatches->get($sourceId);

            if (($filters['unmatched_only'] ?? false) && $existing) {
                continue;
            }
            if (isset($filters['status']) && $existing?->status !== $filters['status']) {
                continue;
            }

            $sourceData = (array) $row;

            [$customer, $confidence, $method] = $this->findMatch($source, $row);

            if ($existing?->status === SourceMatch::STATUS_CONFIRMED && $existing->local_id) {
                $customer   = $this->byId[$existing->local_id] ?? $customer;
                $confidence = max($confidence, 100);
                $method     = $method ?: 'confirmed';
            }

            // Prüfen ob der vorgeschlagene Kunde bereits mit einem ANDEREN Datensatz bestätigt ist
            $takenBySourceId = null;
            if ($customer && !$existing) {
                $otherSourceId = $confirmedByLocalId[$customer->id] ?? null;
                if ($otherSourceId && $otherSourceId !== $sourceId) {
                    $takenBySourceId = $otherSourceId;
                    $customer        = null;
                    $confidence      = 0;
                    $method          = 'duplicate';
                }
            }

            $diff = $customer ? $this->detectDiff($sourceData, $customer, $source) : [];

            $results[] = [
                'source'             => $source,
                'source_id'          => $sourceId,
                'source_data'        => $sourceData,
                'match'              => $customer,
                'confidence'         => $confidence,
                'match_method'       => $method,
                'diff'               => $diff,
                'existing_match'     => $existing,
                'taken_by_source_id' => $takenBySourceId,
            ];
        }

        return $results;
    }

    /**
     * Auto-match alle noch nicht entschiedenen Zeilen mit confidence >= $minConfidence.
     *
     * @return array{auto_matched:int, skipped:int, already_done:int}
     */
    public function autoMatchAll(string $source, int $minConfidence = 90): array
    {
        $this->buildLookupMaps($source);

        $existingBySourceId = SourceMatch::where('entity_type', SourceMatch::ENTITY_CUSTOMER)
            ->where('source', $source)
            ->pluck('source_id')->filter(fn ($v) => $v !== null)->flip()->all();

        $claimedLocalIds = SourceMatch::where('entity_type', SourceMatch::ENTITY_CUSTOMER)
            ->where('source', $source)
            ->pluck('local_id')->filter(fn ($v) => $v !== null)->flip()->all();

        $autoMatched = 0;
        $skipped     = 0;
        $alreadyDone = 0;

        foreach ($this->sourceRows($source) as $row) {
            $sourceId = $this->sourceId($source, $row);

            if (isset($existingBySourceId[$sourceId])) {
                $alreadyDone++;
                continue;
            }

            [$customer, $confidence, $method] = $this->findMatch($source, $row);

            if (! $customer || $confidence < $minConfidence) {
                $skipped++;
                continue;
            }

            if (isset($claimedLocalIds[$customer->id])) {
                $skipped++;
                continue;
            }

            $sourceData = (array) $row;
            $diff       = $this->detectDiff($sourceData, $customer, $source);

            SourceMatch::create([
                'entity_type'     => SourceMatch::ENTITY_CUSTOMER,
                'local_id'        => $customer->id,
                'source'          => $source,
                'source_id'       => $sourceId,
                'status'          => SourceMatch::STATUS_AUTO,
                'matched_by'      => null,
                'source_snapshot' => $sourceData,
                'diff_at_match'   => $diff,
                'confirmed_at'    => null,
            ]);

            $claimedLocalIds[$customer->id] = true;

            $fkCol = $this->fkColumn($source);
            Customer::where('id', $customer->id)->update([$fkCol => (int) $sourceId]);

            $autoMatched++;
        }

        return ['auto_matched' => $autoMatched, 'skipped' => $skipped, 'already_done' => $alreadyDone];
    }

    /**
     * Bestätigt alle Auto-Matches deren aktuelle Konfidenz >= $minConfidence ist.
     *
     * @param  int  $minConfidence  Schwellenwert 50–100, Standard 95
     * @return int  Anzahl bestätigter Matches
     */
    public function confirmAllAbove(string $source, int $userId, int $minConfidence = 95): int
    {
        $this->buildLookupMaps($source);

        $autoMatches = SourceMatch::where('entity_type', SourceMatch::ENTITY_CUSTOMER)
            ->where('source', $source)
            ->where('status', SourceMatch::STATUS_AUTO)
            ->get();

        $confirmed = 0;

        foreach ($autoMatches as $match) {
            $row = $this->fetchSourceRow($source, $match->source_id);

            if (! $row) {
                continue;
            }

            [, $confidence] = $this->findMatch($source, $row);

            if ($confidence >= $minConfidence) {
                $match->update([
                    'status'       => SourceMatch::STATUS_CONFIRMED,
                    'matched_by'   => $userId,
                    'confirmed_at' => now(),
                ]);

                if ($customer = Customer::find($match->local_id)) {
                    app(CustomerDataSyncService::class)->sync($customer);
                }

                $confirmed++;
            }
        }

        return $confirmed;
    }

    /** @deprecated Verwende confirmAllAbove(source, userId, 100) */
    public function confirmAll100(string $source, int $userId): int
    {
        return $this->confirmAllAbove($source, $userId, 100);
    }

    /**
     * Bestätigt einen Match. Ersetzt bestehende Auto-Matches auf denselben Kunden.
     */
    public function confirm(string $source, string $sourceId, int $customerId, int $userId): SourceMatch
    {
        $customer = Customer::findOrFail($customerId);

        $existing = SourceMatch::where('entity_type', SourceMatch::ENTITY_CUSTOMER)
            ->where('source', $source)
            ->where('local_id', $customerId)
            ->where('source_id', '!=', $sourceId)
            ->first();

        if ($existing) {
            if ($existing->status === SourceMatch::STATUS_CONFIRMED) {
                throw new \RuntimeException(
                    "Kunde #{$customerId} ist bereits mit {$source}-Datensatz #{$existing->source_id} verknüpft (bestätigt). Bitte zuerst die bestehende Verknüpfung aufheben."
                );
            }
            $fkColumn = $this->fkColumn($source);
            Customer::where('id', $customerId)->update([$fkColumn => null]);
            $existing->delete();
        }

        $row  = $this->fetchSourceRowAsArray($source, $sourceId);
        $diff = $this->detectDiff($row, $customer, $source);

        $match = SourceMatch::updateOrCreate(
            ['entity_type' => SourceMatch::ENTITY_CUSTOMER, 'source' => $source, 'source_id' => $sourceId],
            [
                'local_id'        => $customerId,
                'status'          => SourceMatch::STATUS_CONFIRMED,
                'matched_by'      => $userId,
                'source_snapshot' => $row,
                'diff_at_match'   => $diff,
                'confirmed_at'    => now(),
            ]
        );

        $fkColumn = $this->fkColumn($source);
        $customer->update([$fkColumn => (int) $sourceId]);

        // Kundendaten aus Quelltabellen synchronisieren
        app(CustomerDataSyncService::class)->sync($customer->fresh());

        $priorStatus = $match->wasRecentlyCreated ? null : $match->getOriginal('status');
        [, $confidence, $method] = $this->findMatchById($source, $sourceId);
        ReconcileFeedbackLog::create([
            'entity_type'    => SourceMatch::ENTITY_CUSTOMER,
            'source'         => $source,
            'source_id'      => $sourceId,
            'action'         => 'confirmed',
            'user_id'        => $userId,
            'target_id'      => (string) $customerId,
            'target_name'    => $customer->company_name ?: trim("{$customer->first_name} {$customer->last_name}"),
            'confidence'     => $confidence,
            'match_method'   => $method,
            'was_auto_match' => $priorStatus === SourceMatch::STATUS_AUTO,
        ]);

        return $match;
    }

    public function ignore(string $source, string $sourceId, int $userId = 0): SourceMatch
    {
        $prior = SourceMatch::where('entity_type', SourceMatch::ENTITY_CUSTOMER)
            ->where('source', $source)->where('source_id', $sourceId)->first();

        $match = SourceMatch::updateOrCreate(
            ['entity_type' => SourceMatch::ENTITY_CUSTOMER, 'source' => $source, 'source_id' => $sourceId],
            ['local_id' => null, 'status' => SourceMatch::STATUS_IGNORED]
        );

        [, $confidence, $method] = $this->findMatchById($source, $sourceId);
        ReconcileFeedbackLog::create([
            'entity_type'    => SourceMatch::ENTITY_CUSTOMER,
            'source'         => $source,
            'source_id'      => $sourceId,
            'action'         => 'ignored',
            'user_id'        => $userId ?: null,
            'confidence'     => $confidence,
            'match_method'   => $method,
            'was_auto_match' => $prior?->status === SourceMatch::STATUS_AUTO,
        ]);

        return $match;
    }

    /**
     * @return array<string, array{source:string, local:string}>
     */
    public function detectDiff(array $sourceData, Customer $customer, string $source): array
    {
        $diff = [];

        if ($source === 'ninox') {
            $this->cmp($diff, 'email',        $sourceData['e_mail']     ?? null, $customer->email);
            $this->cmp($diff, 'company_name', $sourceData['firmenname'] ?? null, $customer->company_name);
            $this->cmp($diff, 'first_name',   $sourceData['vorname']    ?? null, $customer->first_name);
            $this->cmp($diff, 'last_name',    $sourceData['nachname']   ?? null, $customer->last_name);
            $this->cmp($diff, 'phone',        $sourceData['telefon']    ?? null, $customer->phone);

            $ninoxKnr = trim((string) ($sourceData['kundennummer'] ?? ''));
            if ($ninoxKnr && $ninoxKnr !== '0') {
                $localKnr = $customer->customer_number ?? '';
                if (strtolower($ninoxKnr) !== strtolower(trim($localKnr))) {
                    $diff['kundennummer'] = ['source' => $ninoxKnr, 'local' => $localKnr];
                }
            }
        } elseif ($source === 'wawi') {
            $this->cmp($diff, 'email',        $sourceData['cMail']     ?? null, $customer->email);
            $this->cmp($diff, 'company_name', $sourceData['cFirma']    ?? null, $customer->company_name);
            $this->cmp($diff, 'first_name',   $sourceData['cVorname']  ?? null, $customer->first_name);
            $this->cmp($diff, 'last_name',    $sourceData['cNachname'] ?? null, $customer->last_name);
            $this->cmp($diff, 'phone',        $sourceData['cTel']      ?? null, $customer->phone);

            $wawiKnr = trim((string) ($sourceData['cKundenNr'] ?? ''));
            if ($wawiKnr && $wawiKnr !== '0') {
                $localKnr = $customer->customer_number ?? '';
                if (strtolower($wawiKnr) !== strtolower(trim($localKnr))) {
                    $diff['kundennummer'] = ['source' => $wawiKnr, 'local' => $localKnr];
                }
            }
        } elseif ($source === 'lexoffice') {
            $cleanName = $this->cleanLexofficeCompanyName($sourceData['company_name'] ?? '');
            $this->cmp($diff, 'email',        $sourceData['primary_email'] ?? null, $customer->email);
            $this->cmp($diff, 'company_name', $cleanName ?: null,                   $customer->company_name);
            $this->cmp($diff, 'first_name',   $sourceData['first_name']    ?? null, $customer->first_name);
            $this->cmp($diff, 'last_name',    $sourceData['last_name']     ?? null, $customer->last_name);
            $this->cmp($diff, 'phone',        $sourceData['primary_phone'] ?? null, $customer->phone);

            $lexKnr = $this->extractLexofficeCustomerNumber($sourceData['company_name'] ?? '');
            if ($lexKnr) {
                $localKnr = $customer->customer_number ?? '';
                if (strtoupper($lexKnr) !== strtoupper(trim($localKnr))) {
                    $diff['kundennummer'] = ['source' => strtoupper($lexKnr), 'local' => $localKnr];
                }
            }
        }

        return $diff;
    }

    /** @return array{total:int, auto:int, confirmed:int, ignored:int, unmatched:int} */
    public function stats(string $source): array
    {
        $total = match ($source) {
            'ninox'     => DB::table('ninox_kunden')->count(),
            'wawi'      => DB::table('wawi_kunden')->count(),
            'lexoffice' => DB::table('lexoffice_contacts')->where('is_customer', 1)->count(),
            default     => 0,
        };

        $matched = SourceMatch::where('entity_type', SourceMatch::ENTITY_CUSTOMER)
            ->where('source', $source)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $done = ($matched['auto'] ?? 0) + ($matched['confirmed'] ?? 0) + ($matched['ignored'] ?? 0);

        return [
            'total'     => $total,
            'auto'      => $matched['auto'] ?? 0,
            'confirmed' => $matched['confirmed'] ?? 0,
            'ignored'   => $matched['ignored'] ?? 0,
            'unmatched' => max(0, $total - $done),
        ];
    }

    // =========================================================================
    // Lookup-Map Builder
    // =========================================================================

    private function buildLookupMaps(string $source): void
    {
        if ($this->mapsBuilt) {
            return;
        }

        // Shared emails in der Quelltabelle ermitteln (nie für Matching verwenden)
        $this->sharedSourceEmails = $this->buildSharedSourceEmails($source);

        // Lokale Kunden in Maps laden
        Customer::select(['id', 'customer_number', 'email', 'phone', 'company_name', 'first_name', 'last_name'])
            ->get()
            ->each(function (Customer $c): void {
                $this->byId[$c->id] = $c;

                // Kundennummer (beliebiges Format) für direktes Matching
                if ($c->customer_number) {
                    $knrKey = strtolower(trim($c->customer_number));
                    if ($knrKey && $knrKey !== '0') {
                        $this->byCustomerNumber[$knrKey] = $c->id;
                    }
                }

                // Email (nur wenn nicht generisch und in lokaler DB eindeutig)
                if ($c->email) {
                    $emailKey = strtolower(trim($c->email));
                    if ($emailKey && ! $this->isGenericEmail($emailKey)) {
                        $this->byEmail[$emailKey] = $c->id;
                    }
                }

                // Telefon (normalisiert)
                if ($c->phone) {
                    $phoneKey = $this->normalizePhone($c->phone);
                    if (strlen($phoneKey) >= 6) {
                        $this->byPhone[$phoneKey] = $c->id;
                    }
                }

                // Name für Fuzzy (K-Suffix entfernt, Verbinder normalisiert)
                $rawName      = $c->company_name ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''));
                $strippedName = $this->normalizeName($this->stripKnr($rawName));

                // Nackte 5–6-stellige Zahl am Ende des lokalen Namens extrahieren
                $trailingNr = null;
                if (preg_match('/\s(\d{5,6})\s*$/', $rawName, $tm)) {
                    $trailingNr = $tm[1];
                    $this->byTrailingNumber[$trailingNr] = $c->id;
                }

                if ($strippedName) {
                    $this->allNames[] = [
                        'id'          => $c->id,
                        'name'        => $strippedName,
                        'trailing_nr' => $trailingNr,
                    ];
                }
            });

        $this->mapsBuilt = true;
    }

    private function buildSharedSourceEmails(string $source): array
    {
        if ($source === 'ninox') {
            return DB::table('ninox_kunden')
                ->selectRaw('LOWER(TRIM(e_mail)) as email')
                ->whereNotNull('e_mail')
                ->where('e_mail', '!=', '')
                ->groupByRaw('LOWER(TRIM(e_mail))')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('email')
                ->filter()
                ->flip()
                ->all();
        }

        if ($source === 'wawi') {
            return DB::table('wawi_dbo_tadresse')
                ->selectRaw('LOWER(TRIM(cMail)) as email')
                ->whereNotNull('cMail')
                ->where('cMail', '!=', '')
                ->where('nStandard', 1)
                ->groupByRaw('LOWER(TRIM(cMail))')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('email')
                ->filter()
                ->flip()
                ->all();
        }

        if ($source === 'lexoffice') {
            return DB::table('lexoffice_contacts')
                ->selectRaw('LOWER(TRIM(primary_email)) as email')
                ->whereNotNull('primary_email')
                ->where('primary_email', '!=', '')
                ->where('is_customer', 1)
                ->groupByRaw('LOWER(TRIM(primary_email))')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('email')
                ->filter()
                ->flip()
                ->all();
        }

        return [];
    }

    // =========================================================================
    // Matching
    // =========================================================================

    /** Lädt den Quelldatensatz und gibt [customer, confidence, method] zurück. */
    private function findMatchById(string $source, string $sourceId): array
    {
        $this->buildLookupMaps($source);
        $row = $this->fetchSourceRow($source, $sourceId);

        return $row ? $this->findMatch($source, $row) : [null, 0, 'none'];
    }

    private function findMatch(string $source, object $row): array
    {
        // ── Regel 0: Kundennummer (höchste Priorität) ─────────────────────────
        // Ninox: kundennummer (beliebiges Format — K####, 10291, leer)
        // WaWi:  cKundenNr (beliebiges Format)
        // Lexoffice: trailing K#### oder 5-6-stellige Zahl aus company_name
        $rawSourceNumber = $this->extractSourceCustomerNumber($source, $row);

        if ($rawSourceNumber) {
            $numKey = strtolower(trim($rawSourceNumber));
            if ($numKey && $numKey !== '0' && isset($this->byCustomerNumber[$numKey])) {
                return [$this->byId[$this->byCustomerNumber[$numKey]], 100, 'customer-number'];
            }
        }

        // ── Regel 0b: 5-6-stellige Zahl im lokalen Firmennamen ───────────────
        // Lokale Firmennamen enthalten oft die Ninox-Kundennummer als Suffix,
        // z.B. "Augen MVZ Mathildenhöhe GmbH 10185" → kundennummer=10185.
        // Namens-Gegenprüfung nötig: verhindert Zufallstreffer.
        if ($rawSourceNumber && preg_match('/^\d{5,6}$/', trim($rawSourceNumber))) {
            $knrNum = trim($rawSourceNumber);
            if (isset($this->byTrailingNumber[$knrNum])) {
                $candidate = $this->byId[$this->byTrailingNumber[$knrNum]];
                $srcName   = $this->extractSourceName($source, $row);
                $localName = $candidate->company_name
                    ?: trim(($candidate->first_name ?? '') . ' ' . ($candidate->last_name ?? ''));
                $nameSim   = $this->levenshteinPercent($this->stripKnr($srcName), $this->stripKnr($localName));

                if ($nameSim >= 60) {
                    return [$candidate, 100, 'trailing-kundennummer'];
                }
            }
        }

        // Trailing-Nummer merken für Penalty-Check in Fuzzy-Phase
        $sourceTrailingNr = ($rawSourceNumber && preg_match('/^\d{5,6}$/', trim($rawSourceNumber)))
            ? trim($rawSourceNumber)
            : null;

        // ── Regel 1: Email (qualifiziert) ─────────────────────────────────────
        $rawEmail = $this->extractSourceEmail($source, $row);
        $emailKey = $rawEmail ? strtolower(trim($rawEmail)) : null;

        $emailCustomer = null;
        if ($emailKey
            && ! $this->isGenericEmail($emailKey)
            && ! isset($this->sharedSourceEmails[$emailKey])
            && isset($this->byEmail[$emailKey])
        ) {
            $candidate  = $this->byId[$this->byEmail[$emailKey]];
            $sourceName = $this->extractSourceName($source, $row);
            $localName  = $candidate->company_name
                ?: trim(($candidate->first_name ?? '') . ' ' . ($candidate->last_name ?? ''));
            $nameSim    = $this->levenshteinPercent($this->stripKnr($sourceName), $this->stripKnr($localName));

            if ($nameSim >= 50) {
                return [$candidate, 90, 'email+name'];
            }

            // Email stimmt, Name aber nicht → merken für Tie-Breaker
            $emailCustomer = $candidate;
        }

        // ── Regel 3: Telefon (normalisiert) ──────────────────────────────────
        $phones = $this->extractSourcePhones($source, $row);

        foreach ($phones as $phoneKey) {
            if (strlen($phoneKey) < 6) {
                continue;
            }
            if (isset($this->byPhone[$phoneKey])) {
                $candidate = $this->byId[$this->byPhone[$phoneKey]];

                // Phone + Email auf gleichem Kandidaten → höhere Konfidenz
                if ($emailCustomer && $emailCustomer->id === $candidate->id) {
                    return [$candidate, 92, 'email+phone'];
                }

                return [$candidate, 85, 'phone'];
            }
        }

        // Email allein (ohne Namens-Bestätigung) → niedrige Konfidenz, nur Vorschlag
        if ($emailCustomer) {
            return [$emailCustomer, 70, 'email-only'];
        }

        // ── Regel 4+5: Fuzzy-Name ─────────────────────────────────────────────
        $sourceName = $this->normalizeName($this->stripKnr($this->extractSourceName($source, $row)));

        if ($sourceName) {
            $bestId   = null;
            $bestConf = 0;

            foreach ($this->allNames as ['id' => $id, 'name' => $localName, 'trailing_nr' => $localTrailingNr]) {
                $conf = $this->levenshteinPercent($sourceName, $localName);

                // Penalty: gleicher Firmenname, aber anhängende Kundennummer stimmt nicht überein
                // Beispiel: Ninox "DAW SE" knr=10814 vs lokal "DAW SE 11314" → Penalty
                if ($conf >= 80
                    && $localTrailingNr !== null
                    && $sourceTrailingNr !== null
                    && $localTrailingNr !== $sourceTrailingNr
                ) {
                    $conf = min($conf, 70);
                }

                if ($conf > $bestConf) {
                    $bestConf = $conf;
                    $bestId   = $id;
                }
            }

            if ($bestId !== null && $bestConf >= 80) {
                return [$this->byId[$bestId], $bestConf, 'fuzzy_name'];
            }
        }

        return [null, 0, 'none'];
    }

    // =========================================================================
    // Source helpers
    // =========================================================================

    private function sourceRows(string $source): array
    {
        return match ($source) {
            'ninox' => DB::table('ninox_kunden')->get()->all(),

            'wawi'  => DB::table('wawi_kunden as wk')
                ->leftJoin(
                    DB::raw(
                        '(SELECT kKunde,
                                 MIN(cFirma)       AS cFirma,
                                 MIN(cVorname)     AS cVorname,
                                 MIN(cName)        AS cNachname,
                                 MIN(cMail)        AS cMail,
                                 MIN(cTel)         AS cTel,
                                 MIN(cMobil)       AS cMobil
                          FROM wawi_dbo_tadresse
                          WHERE nStandard = 1
                          GROUP BY kKunde) AS a'
                    ),
                    'a.kKunde',
                    '=',
                    'wk.kKunde'
                )
                ->select(
                    'wk.kKunde',
                    'wk.cKundenNr',
                    'wk.updated_at',
                    'a.cFirma',
                    'a.cVorname',
                    'a.cNachname',
                    'a.cMail',
                    'a.cTel',
                    'a.cMobil'
                )
                ->get()
                ->all(),

            'lexoffice' => DB::table('lexoffice_contacts')
                ->where('is_customer', 1)
                ->where(function ($q): void {
                    $q->whereNull('archived')->orWhere('archived', 0);
                })
                ->get()
                ->all(),

            default => [],
        };
    }

    private function sourceId(string $source, object $row): string
    {
        return (string) match ($source) {
            'ninox'     => $row->ninox_id,
            'wawi'      => $row->kKunde,
            'lexoffice' => $row->lexoffice_uuid,
            default     => throw new \InvalidArgumentException("Unknown source: $source"),
        };
    }

    private function fetchSourceRow(string $source, string $sourceId): ?object
    {
        return match ($source) {
            'ninox'     => DB::table('ninox_kunden')->where('ninox_id', $sourceId)->first(),
            'wawi'      => DB::table('wawi_kunden as wk')
                ->leftJoin(
                    DB::raw(
                        '(SELECT kKunde,
                                 MIN(cFirma)       AS cFirma,
                                 MIN(cVorname)     AS cVorname,
                                 MIN(cName)        AS cNachname,
                                 MIN(cMail)        AS cMail,
                                 MIN(cTel)         AS cTel,
                                 MIN(cMobil)       AS cMobil
                          FROM wawi_dbo_tadresse
                          WHERE nStandard = 1
                          GROUP BY kKunde) AS a'
                    ),
                    'a.kKunde',
                    '=',
                    'wk.kKunde'
                )
                ->select('wk.kKunde', 'wk.cKundenNr', 'wk.updated_at', 'a.cFirma', 'a.cVorname', 'a.cNachname', 'a.cMail', 'a.cTel', 'a.cMobil')
                ->where('wk.kKunde', $sourceId)
                ->first(),
            'lexoffice' => DB::table('lexoffice_contacts')->where('lexoffice_uuid', $sourceId)->first(),
            default     => null,
        };
    }

    private function fetchSourceRowAsArray(string $source, string $sourceId): array
    {
        $row = $this->fetchSourceRow($source, $sourceId);
        return $row ? (array) $row : [];
    }

    private function fkColumn(string $source): string
    {
        return match ($source) {
            'ninox'     => 'ninox_kunden_id',
            'wawi'      => 'wawi_kunden_id',
            'lexoffice' => 'lexoffice_contact_id',
            default     => throw new \InvalidArgumentException("Unknown source: $source"),
        };
    }

    /**
     * Gibt die Kundennummer aus dem Quelldatensatz zurück (beliebiges Format).
     * Leere Strings und "0" werden als null behandelt.
     */
    private function extractSourceCustomerNumber(string $source, object $row): ?string
    {
        $raw = match ($source) {
            'ninox'     => $row->kundennummer ?? null,
            'wawi'      => $row->cKundenNr ?? null,
            'lexoffice' => $this->extractLexofficeCustomerNumber($row->company_name ?? ''),
            default     => null,
        };

        if ($raw === null) {
            return null;
        }
        $val = trim((string) $raw);
        return ($val === '' || $val === '0' || $val === '0.0') ? null : $val;
    }

    /**
     * Gibt den für Matching relevanten Firmennamen aus dem Quelldatensatz zurück.
     */
    private function extractSourceName(string $source, object $row): string
    {
        return match ($source) {
            'ninox'     => trim(($row->firmenname ?? '') ?: (($row->vorname ?? '') . ' ' . ($row->nachname ?? ''))),
            'wawi'      => trim(($row->cFirma ?? '') ?: (($row->cVorname ?? '') . ' ' . ($row->cNachname ?? ''))),
            'lexoffice' => $this->cleanLexofficeCompanyName($row->company_name ?? ''),
            default     => '',
        };
    }

    /**
     * Gibt die Email-Adresse aus dem Quelldatensatz zurück.
     */
    private function extractSourceEmail(string $source, object $row): ?string
    {
        $raw = match ($source) {
            'ninox'     => $row->e_mail ?? null,
            'wawi'      => $row->cMail ?? null,
            'lexoffice' => $row->primary_email ?? null,
            default     => null,
        };
        return ($raw && trim((string) $raw) !== '') ? trim((string) $raw) : null;
    }

    /**
     * Gibt normalisierte Telefonnummern aus dem Quelldatensatz zurück.
     *
     * @return list<string>
     */
    private function extractSourcePhones(string $source, object $row): array
    {
        $phones = [];

        if ($source === 'ninox') {
            foreach (['telefon', 'telefon_2'] as $field) {
                $v = $row->$field ?? null;
                if ($v && trim((string) $v) !== '0' && trim((string) $v) !== '') {
                    $phones[] = $this->normalizePhone((string) $v);
                }
            }
        } elseif ($source === 'wawi') {
            foreach (['cTel', 'cMobil'] as $field) {
                $v = $row->$field ?? null;
                if ($v && trim((string) $v) !== '') {
                    $phones[] = $this->normalizePhone((string) $v);
                }
            }
        } elseif ($source === 'lexoffice') {
            $v = $row->primary_phone ?? null;
            if ($v && trim((string) $v) !== '') {
                $phones[] = $this->normalizePhone((string) $v);
            }
        }

        return $phones;
    }

    // =========================================================================
    // Lexoffice helpers
    // =========================================================================

    /**
     * Extrahiert die trailing Kundennummer aus einem Lexoffice company_name.
     *
     *   "Alex Kaniak K4470"   → "K4470"
     *   "Afipro GmbH 11809"   → "11809"
     *   "Max Mustermann"      → null
     */
    public function extractLexofficeCustomerNumber(string $name): ?string
    {
        if (preg_match('/\s+(K\d{4,6})\s*$/i', $name, $m)) {
            return strtoupper(trim($m[1]));
        }
        if (preg_match('/\s+(\d{5,6})\s*$/', $name, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Bereinigt den Lexoffice company_name für Matching/Anzeige
     * (entfernt trailing Kundennummer).
     *
     *   "Alex Kaniak K4470"   → "Alex Kaniak"
     *   "Afipro GmbH 11809"   → "Afipro GmbH"
     */
    private function cleanLexofficeCompanyName(string $name): string
    {
        $name = preg_replace('/\s+K\d{4,6}\s*$/i', '', $name);
        $name = preg_replace('/\s+\d{5,6}\s*$/', '', $name);
        return trim($name);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Normalisiert eine Telefonnummer auf reine Ziffern mit deutschem Format.
     * "+49 6151/133-696" → "06151133696"
     * "0049 6151 133696" → "06151133696"
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0049')) {
            $digits = '0' . substr($digits, 4);
        } elseif (str_starts_with($digits, '49') && strlen($digits) >= 11) {
            $digits = '0' . substr($digits, 2);
        }

        return $digits;
    }

    /**
     * Entfernt Kundennummer-Suffixe aus Firmennamen vor dem Fuzzy-Vergleich.
     *
     *   "Müller GmbH K4470"              → "Müller GmbH"
     *   "Augen MVZ Mathildenhöhe GmbH 10185" → "Augen MVZ Mathildenhöhe GmbH"
     *
     * 4-stellige Zahlen werden NICHT entfernt (könnten Gründungsjahre sein).
     */
    private function stripKnr(string $name): string
    {
        $name = preg_replace('/\s+K\d{4,6}\s*$/i', '', $name);
        $name = preg_replace('/\s+\d{5,6}\s*$/', '', $name);
        return trim($name);
    }

    private function isGenericEmail(string $email): bool
    {
        $prefix = explode('@', $email)[0] ?? '';
        return in_array(strtolower($prefix), self::GENERIC_EMAIL_PREFIXES, true);
    }

    /**
     * Normalisiert einen Firmennamen für den Fuzzy-Vergleich.
     *
     * "K & K" = "K+K" = "K und K" = "KK"
     * "1und1" = "1&1" = "1+1"
     */
    private function normalizeName(string $name): string
    {
        $s = mb_strtolower(trim($name));
        $s = preg_replace('/\s*[&+]\s*/', ' ', $s);
        $s = preg_replace('/\s*-\s*/', ' ', $s);
        $s = preg_replace('/\bund\b/u', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private function levenshteinPercent(string $a, string $b): int
    {
        $aN     = $this->normalizeName($a);
        $bN     = $this->normalizeName($b);
        $score1 = $this->rawLev($aN, $bN);

        $aC     = str_replace([' ', 'und'], '', $aN);
        $bC     = str_replace([' ', 'und'], '', $bN);
        $score2 = $this->rawLev($aC, $bC);

        return max($score1, $score2);
    }

    private function rawLev(string $a, string $b): int
    {
        if ($a === $b) {
            return 100;
        }
        $max = max(mb_strlen($a), mb_strlen($b));
        if ($max === 0) {
            return 100;
        }
        return (int) round((1 - levenshtein($a, $b) / $max) * 100);
    }

    private function cmp(array &$diff, string $field, mixed $src, mixed $local): void
    {
        $s = $src   !== null ? trim((string) $src)   : null;
        $l = $local !== null ? trim((string) $local) : null;
        if ($s !== null && $s !== '' && $l !== null && $l !== '' && $s !== $l) {
            $diff[$field] = ['source' => $s, 'local' => $l];
        }
    }
}
