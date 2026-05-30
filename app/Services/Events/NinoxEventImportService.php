<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Models\Events\EventContact;
use App\Models\Events\EventImportLink;
use App\Models\Events\EventOccurrence;
use App\Models\Events\EventOfferVersion;
use App\Models\Events\EventSeries;
use App\Models\Pricing\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Imports Ninox event data into the local event tables.
 *
 * IMPORTANT: Only reads from Ninox tables — never mutates them.
 */
class NinoxEventImportService
{
    /**
     * Find ninox_bestellannahme records that are event candidates.
     *
     * Criteria:
     *  - status LIKE '%Angebot schreiben%'  (emoji-prefixed in Ninox)
     *  - OR status LIKE '%Angebot geschrieben%'
     *  - OR status LIKE '%Angebot abgelehnt%'
     *  - OR gehoert_zu_veranstaltung IS NOT NULL
     *
     * Note: datum_der_veranstaltung alone is NOT a reliable indicator —
     * regular deliveries also carry this field.
     */
    public function discoverEventCandidates(): Collection
    {
        return DB::table('ninox_bestellannahme')
            ->where(function ($q) {
                $q->where('status', 'LIKE', '%Angebot schreiben%')
                  ->orWhere('status', 'LIKE', '%Angebot geschrieben%')
                  ->orWhere('status', 'LIKE', '%Angebot abgelehnt%')
                  ->orWhereNotNull('gehoert_zu_veranstaltung');
            })
            ->orderByDesc('ninox_id')
            ->get();
    }

    /**
     * Import all ninox_veranstaltung records as EventSeries.
     *
     * Skips already-imported records (checks event_import_links).
     *
     * @return array{imported: int, skipped: int, errors: array}
     */
    public function importEventSeries(): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        $records = DB::table('ninox_veranstaltung')->get();

        foreach ($records as $record) {
            try {
                $alreadyImported = EventImportLink::where('entity_type', EventSeries::class)
                    ->where('source_system', 'ninox')
                    ->where('source_record_id', (string) $record->ninox_id)
                    ->exists();

                if ($alreadyImported) {
                    $result['skipped']++;
                    continue;
                }

                DB::transaction(function () use ($record) {
                    $customerId = !empty($record->kunden)
                        ? $this->resolveOrCreateCustomer((int) $record->kunden)
                        : null;

                    $series = EventSeries::create([
                        'name'              => $record->name ?? 'Veranstaltung #' . $record->ninox_id,
                        'event_type'        => 'unknown',
                        'customer_id'       => $customerId,
                        'source_system'     => 'ninox',
                        'source_table'      => 'ninox_veranstaltung',
                        'source_record_id'  => (string) $record->ninox_id,
                        'is_active'         => true,
                        'is_duplicate_suspect' => false,
                    ]);

                    EventImportLink::create([
                        'entity_type'      => EventSeries::class,
                        'entity_id'        => $series->id,
                        'source_system'    => 'ninox',
                        'source_table'     => 'ninox_veranstaltung',
                        'source_record_id' => (string) $record->ninox_id,
                        'imported_at'      => now(),
                        'last_seen_at'     => now(),
                    ]);
                });

                $result['imported']++;
            } catch (\Throwable $e) {
                $result['errors'][] = [
                    'ninox_id' => $record->ninox_id,
                    'error'    => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * Import a single ninox_bestellannahme record as EventOccurrence + EventOfferVersion.
     *
     * Returns null if the record was already imported.
     *
     * @throws \RuntimeException if the record does not exist
     */
    public function importEventOccurrence(int $ninoxId): ?EventOccurrence
    {
        $record = DB::table('ninox_bestellannahme')->where('ninox_id', $ninoxId)->first();

        if ($record === null) {
            throw new \RuntimeException("ninox_bestellannahme with ninox_id={$ninoxId} not found.");
        }

        // Check duplicate
        $alreadyImported = EventImportLink::where('entity_type', EventOccurrence::class)
            ->where('source_system', 'ninox')
            ->where('source_record_id', (string) $ninoxId)
            ->exists();

        if ($alreadyImported) {
            return null;
        }

        return DB::transaction(function () use ($record, $ninoxId) {
            // Resolve event_series_id if there is a veranstaltung link
            $seriesId = null;
            if (!empty($record->gehoert_zu_veranstaltung)) {
                $link = EventImportLink::where('entity_type', EventSeries::class)
                    ->where('source_system', 'ninox')
                    ->where('source_record_id', (string) $record->gehoert_zu_veranstaltung)
                    ->first();
                $seriesId = $link?->entity_id;
            }

            // Resolve customer
            $customerId = null;
            if (!empty($record->kunden)) {
                $customerId = $this->resolveOrCreateCustomer((int) $record->kunden);
            }

            // Map status
            $statusMap = $this->mapNinoxStatus((string) ($record->status ?? ''));

            // Parse event date — fallback chain:
            // 1. datum_der_veranstaltung  (explicit event date set in Ninox)
            // 2. lieferzeitpunkt          (delivery timestamp, includes time)
            // 3. lieferdatum              (delivery date)
            // 4. ninox_created_at         (record creation date as last resort)
            $eventStartAt = null;
            foreach ([
                $record->datum_der_veranstaltung ?? null,
                $record->lieferzeitpunkt ?? null,
                $record->lieferdatum ?? null,
                $record->ninox_created_at ?? null,
            ] as $rawDate) {
                if (!empty($rawDate)) {
                    try {
                        $eventStartAt = \Carbon\Carbon::parse($rawDate);
                        break;
                    } catch (\Throwable) {
                        // try next
                    }
                }
            }

            $eventYear = $eventStartAt?->year;

            $occurrence = EventOccurrence::create([
                'event_series_id'       => $seriesId,
                'customer_id'           => $customerId,
                'title'                 => $this->buildTitle($record),
                'event_type'            => 'unknown',
                'event_start_at'        => $eventStartAt,
                'event_year'            => $eventYear,
                'expected_guests'       => $record->anzahl_gaeste ?? null,
                'request_channel'       => 'ninox',
                'offer_status'          => $statusMap['offer_status'],
                'event_status'          => 'planned',
                'source_system'         => 'ninox',
                'source_table'          => 'ninox_bestellannahme',
                'source_record_id'      => (string) $ninoxId,
                'import_confidence'     => 0.80,
                'needs_review'          => $statusMap['needs_review'] || $customerId === null,
                'internal_notes'        => $record->text_mehrzeilig ?? null,
            ]);

            // Create offer version (no ST-A number for imports)
            EventOfferVersion::create([
                'event_occurrence_id'   => $occurrence->id,
                'external_offer_number' => (string) $ninoxId,
                'version_number'        => 1,
                'source_system'         => 'ninox',
                'status'                => $statusMap['offer_status'],
                'net_total_milli'       => $this->parsePriceMilli($record->preis ?? null),
            ]);

            // Create import link
            EventImportLink::create([
                'entity_type'      => EventOccurrence::class,
                'entity_id'        => $occurrence->id,
                'source_system'    => 'ninox',
                'source_table'     => 'ninox_bestellannahme',
                'source_record_id' => (string) $ninoxId,
                'imported_at'      => now(),
                'last_seen_at'     => now(),
            ]);

            // Import contacts if there is a linked veranstaltung
            if (!empty($record->gehoert_zu_veranstaltung)) {
                $this->importContacts($occurrence, (int) $record->gehoert_zu_veranstaltung);
            }

            return $occurrence;
        });
    }

    /**
     * Import contacts from ninox_kontakte for a given veranstaltung ID.
     */
    public function importContacts(EventOccurrence $occ, int $ninoxVeranstaltungId): void
    {
        $contacts = DB::table('ninox_kontakte')
            ->where('veranstaltung', $ninoxVeranstaltungId)
            ->get();

        foreach ($contacts as $contact) {
            $name = trim(($contact->vorname ?? '') . ' ' . ($contact->nachname ?? ''));
            if ($name === '') {
                continue;
            }

            $alreadyExists = EventContact::where('event_occurrence_id', $occ->id)
                ->where('source_contact_id', (string) $contact->ninox_id)
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            EventContact::create([
                'event_occurrence_id' => $occ->id,
                'source_contact_id'   => (string) $contact->ninox_id,
                'name'                => $name,
                'role'                => 'main',
                'phone'               => $contact->telefon ?? null,
                'email'               => $contact->e_mail ?? null,
                'is_primary'          => false,
            ]);
        }
    }

    /**
     * Map a Ninox status string to local offer_status and needs_review flag.
     *
     * Ninox status values carry emoji prefixes (e.g. "❣️ Angebot schreiben"),
     * so we use str_contains for matching instead of exact equality.
     *
     * @return array{offer_status: string, needs_review: bool}
     */
    public function mapNinoxStatus(string $ninoxStatus): array
    {
        if (str_contains($ninoxStatus, 'Angebot schreiben')) {
            return ['offer_status' => 'requested', 'needs_review' => false];
        }
        if (str_contains($ninoxStatus, 'Angebot geschrieben')) {
            return ['offer_status' => 'sent', 'needs_review' => false];
        }
        if (str_contains($ninoxStatus, 'Angebot abgelehnt')) {
            return ['offer_status' => 'rejected', 'needs_review' => false];
        }
        // bestellt / geliefert / abgerechnet → treat as converted
        if (str_contains($ninoxStatus, 'bestellt') || str_contains($ninoxStatus, 'geliefert') || str_contains($ninoxStatus, 'abgerechnet')) {
            return ['offer_status' => 'converted_to_order', 'needs_review' => false];
        }
        // Unknown → needs manual review
        return ['offer_status' => 'draft', 'needs_review' => true];
    }

    // ── Private helpers ────────────────────────────────────────

    /**
     * Resolve a Ninox Kunden-ID to a local Customer ID, creating one if needed.
     *
     * Step 1: direct match via ninox_kunden_id
     * Step 2: match via customer_number (backfills ninox_kunden_id)
     * Step 3: create new customer with Standardgruppe (ID 1)
     */
    private function resolveOrCreateCustomer(int $ninoxKundenId): ?int
    {
        // Step 1: direct match
        $existingId = Customer::where('ninox_kunden_id', $ninoxKundenId)->value('id');
        if ($existingId !== null) {
            return $existingId;
        }

        // Load ninox_kunden row
        $ninoxKunde = DB::table('ninox_kunden')->where('ninox_id', $ninoxKundenId)->first();
        if ($ninoxKunde === null) {
            return null;
        }

        // Step 2: match via Kundennummer
        if (!empty($ninoxKunde->kundennummer)) {
            $customer = Customer::where('customer_number', (string) $ninoxKunde->kundennummer)->first();
            if ($customer !== null) {
                $customer->update(['ninox_kunden_id' => $ninoxKundenId]);
                return $customer->id;
            }
        }

        // Step 3: create new customer
        $companyName = !empty($ninoxKunde->firmenname)
            ? $ninoxKunde->firmenname
            : trim(($ninoxKunde->vorname ?? '') . ' ' . ($ninoxKunde->nachname ?? ''));

        $customer = Customer::create([
            'company_name'      => $companyName ?: 'Ninox-Kunde #' . $ninoxKundenId,
            'customer_number'   => !empty($ninoxKunde->kundennummer) ? (string) $ninoxKunde->kundennummer : 'NINOX-' . $ninoxKundenId,
            'customer_group_id' => 1,
            'ninox_kunden_id'   => $ninoxKundenId,
        ]);

        return $customer->id;
    }

    private function buildTitle(object $record): string
    {
        if (!empty($record->datum_der_veranstaltung)) {
            return 'Veranstaltung ' . $record->datum_der_veranstaltung;
        }

        return 'Ninox-Bestellannahme #' . $record->ninox_id;
    }

    /**
     * Parse a price value (assumed EUR float/string) into milli-cents.
     */
    private function parsePriceMilli(mixed $price): ?int
    {
        if ($price === null || $price === '') {
            return null;
        }

        $value = (float) str_replace(',', '.', (string) $price);

        return (int) round($value * 1_000_000);
    }
}
