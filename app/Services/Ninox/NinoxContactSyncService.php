<?php

declare(strict_types=1);

namespace App\Services\Ninox;

use App\Models\Contact;
use App\Models\Pricing\Customer;
use App\Models\SourceMatch;
use App\Models\Supplier\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Syncs ninox_kontakte → contacts table.
 *
 * Resolves the Ninox kunden/lieferanten FK through source_matches to get
 * the local Customer/Supplier ID.  Contacts whose FK cannot yet be resolved
 * are created without a contactable link (updated on next sync once matched).
 */
class NinoxContactSyncService
{
    /**
     * Sync all ninox_kontakte rows into the contacts table.
     *
     * @return array{created: int, updated: int, unlinked: int}
     */
    public function syncAll(): array
    {
        // Build lookup: ninox_kunden_id → local Customer id
        $customerMap = SourceMatch::where('source', 'ninox')
            ->where('status', SourceMatch::STATUS_CONFIRMED)
            ->where('entity_type', 'customer')
            ->pluck('local_id', 'source_id')  // ninox_id => customer_id
            ->all();

        // Build lookup: ninox_lieferanten_id → local Supplier id
        $supplierMap = SourceMatch::where('source', 'ninox')
            ->where('status', SourceMatch::STATUS_CONFIRMED)
            ->where('entity_type', 'supplier')
            ->pluck('local_id', 'source_id')
            ->all();

        $kontakte = DB::table('ninox_kontakte')->get();

        $created  = 0;
        $updated  = 0;
        $unlinked = 0;

        foreach ($kontakte as $k) {
            $name = trim(implode(' ', array_filter([
                $k->vorname ?? '',
                $k->nachname ?? '',
            ])));

            if (empty($name)) {
                $name = 'Unbekannt';
            }

            // Resolve contactable relationship
            [$contactableType, $contactableId] = $this->resolveContactable(
                $k,
                $customerMap,
                $supplierMap,
            );

            if ($contactableId === null) {
                $unlinked++;
            }

            $data = [
                'ninox_id'         => (int) $k->ninox_id,
                'contactable_type' => $contactableType,
                'contactable_id'   => $contactableId,
                'name'             => $name,
                'phone'            => $k->telefon ?: null,
                'email'            => $k->e_mail ?: null,
                'role'             => $k->rollen ?: null,
                'sort_order'       => 0,
            ];

            $existing = Contact::where('ninox_id', $k->ninox_id)->first();

            if ($existing) {
                $existing->update($data);
                $updated++;
            } else {
                Contact::create($data);
                $created++;
            }
        }

        return compact('created', 'updated', 'unlinked');
    }

    /**
     * Re-link contacts whose contactable is still null (run after more source_matches are confirmed).
     *
     * @return int  Number of contacts updated
     */
    public function relinkUnresolved(): int
    {
        $customerMap = SourceMatch::where('source', 'ninox')
            ->where('status', SourceMatch::STATUS_CONFIRMED)
            ->where('entity_type', 'customer')
            ->pluck('local_id', 'source_id')
            ->all();

        $supplierMap = SourceMatch::where('source', 'ninox')
            ->where('status', SourceMatch::STATUS_CONFIRMED)
            ->where('entity_type', 'supplier')
            ->pluck('local_id', 'source_id')
            ->all();

        $unlinked = Contact::whereNotNull('ninox_id')
            ->whereNull('contactable_id')
            ->get();

        $count = 0;
        foreach ($unlinked as $contact) {
            $k = DB::table('ninox_kontakte')->where('ninox_id', $contact->ninox_id)->first();
            if (! $k) continue;

            [$type, $id] = $this->resolveContactable($k, $customerMap, $supplierMap);
            if ($id === null) continue;

            $contact->update([
                'contactable_type' => $type,
                'contactable_id'   => $id,
            ]);
            $count++;
        }

        return $count;
    }

    // ── private ───────────────────────────────────────────────────────────────

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private function resolveContactable(
        object $ninoxKontakt,
        array  $customerMap,
        array  $supplierMap,
    ): array {
        // Priority: Kunden before Lieferanten
        if (! empty($ninoxKontakt->kunden)) {
            $localId = $customerMap[(string) $ninoxKontakt->kunden] ?? null;
            if ($localId) {
                return [Customer::class, (int) $localId];
            }
        }

        if (! empty($ninoxKontakt->lieferanten)) {
            $localId = $supplierMap[(string) $ninoxKontakt->lieferanten] ?? null;
            if ($localId) {
                return [Supplier::class, (int) $localId];
            }
        }

        return [null, null];
    }
}
