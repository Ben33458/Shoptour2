<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Models\Events\EventImportLink;
use App\Models\Events\EventOfferItem;
use App\Models\Events\EventOfferVersion;
use App\Models\Events\EventOccurrence;
use App\Models\Pricing\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JtlOfferImportService
{
    /**
     * Returns all JTL offers (nType=0) that have not yet been imported as EventOfferVersion.
     */
    public function getCandidates(): Collection
    {
        $alreadyImported = EventOfferVersion::where('source_system', 'jtl')
            ->pluck('external_offer_number')
            ->toArray();

        // Subquery: one billing address per customer (min kAdresse to avoid duplicates)
        $addrSub = DB::table('wawi_dbo_tadresse')
            ->where('nTyp', 1)
            ->selectRaw('MIN(kAdresse) as kAdresse, kKunde')
            ->groupBy('kKunde');

        return DB::table('wawi_verkauf_tauftrag as a')
            ->leftJoinSub($addrSub, 'addr_min', 'addr_min.kKunde', '=', 'a.kKunde')
            ->leftJoin('wawi_dbo_tadresse as addr', 'addr.kAdresse', '=', 'addr_min.kAdresse')
            ->where('a.nType', 0)
            ->whereNotIn('a.cAuftragsNr', $alreadyImported)
            ->orderBy('a.dErstellt', 'desc')
            ->select(
                'a.kAuftrag',
                'a.cAuftragsNr',
                'a.dErstellt',
                'a.kKunde',
                'a.cKundenNr',
                DB::raw("TRIM(CONCAT(COALESCE(addr.cFirma,''), ' ', COALESCE(addr.cVorname,''), ' ', COALESCE(addr.cName,''))) as kundename"),
            )
            ->get();
    }

    /**
     * Enriches candidates with position_count, suggestion and auto_match flag.
     *
     * Auto-match logic (in order of confidence):
     *  1. Customer has exactly 1 open occurrence → auto_match = true
     *  2. Customer has multiple → find one whose event_start_at is in the same year-month
     *     as the JTL offer date → auto_match = true
     *  3. Otherwise → suggestion = best guess, auto_match = false
     */
    public function enrichCandidates(Collection $candidates): Collection
    {
        if ($candidates->isEmpty()) {
            return $candidates;
        }

        $kAuftraege   = $candidates->pluck('kAuftrag')->all();
        $kundenNrList = $candidates->pluck('cKundenNr')->unique()->all();

        // 1. Position counts — one query
        $positionCounts = DB::table('wawi_verkauf_tauftragposition')
            ->whereIn('kAuftrag', $kAuftraege)
            ->selectRaw('kAuftrag, COUNT(*) as cnt')
            ->groupBy('kAuftrag')
            ->pluck('cnt', 'kAuftrag');

        // 2. ninox_kunden rows indexed by kundennummer — one query
        $ninoxKundenByNr = DB::table('ninox_kunden')
            ->whereIn('kundennummer', $kundenNrList)
            ->get()
            ->keyBy('kundennummer');

        // 3. customers → id by ninox_kunden_id — one query
        $customerIdsByNinoxId = DB::table('customers')
            ->whereIn('ninox_kunden_id', $ninoxKundenByNr->pluck('ninox_id')->all())
            ->pluck('id', 'ninox_kunden_id');

        // 4. All open occurrences for matched customers — one query, grouped by customer_id
        $customerIds        = $customerIdsByNinoxId->values()->all();
        $occurrencesWithJtl = EventOfferVersion::where('source_system', 'jtl')
            ->pluck('event_occurrence_id')
            ->all();

        $occurrencesByCustomerId = EventOccurrence::with('customer')
            ->whereIn('customer_id', $customerIds)
            ->whereNotIn('id', $occurrencesWithJtl)
            ->whereIn('event_status', ['planned', 'confirmed'])
            ->orderBy('event_start_at', 'asc')
            ->get()
            ->groupBy('customer_id');

        return $candidates->map(function ($offer) use (
            $positionCounts, $ninoxKundenByNr, $customerIdsByNinoxId, $occurrencesByCustomerId
        ) {
            $offer->position_count  = (int) ($positionCounts[$offer->kAuftrag] ?? 0);
            $offer->suggestion      = null;
            $offer->auto_match      = false;
            $offer->ninox_kunde     = $ninoxKundenByNr[$offer->cKundenNr] ?? null;
            // can_auto_create: Ninox-Kunde gefunden, also kann EventOccurrence angelegt werden
            $offer->can_auto_create = $offer->ninox_kunde !== null;

            $ninoxId    = $offer->ninox_kunde?->ninox_id ?? null;
            $customerId = $ninoxId ? ($customerIdsByNinoxId[$ninoxId] ?? null) : null;

            if (! $customerId) {
                return $offer;
            }

            $occurrences = $occurrencesByCustomerId[$customerId] ?? collect();

            if ($occurrences->count() === 1) {
                $offer->suggestion = $occurrences->first();
                $offer->auto_match = true;
            } elseif ($occurrences->count() > 1) {
                $offerMonth = \Carbon\Carbon::parse($offer->dErstellt)->format('Y-m');
                $sameMonth  = $occurrences->filter(
                    fn ($o) => $o->event_start_at &&
                        \Carbon\Carbon::parse($o->event_start_at)->format('Y-m') === $offerMonth
                );

                if ($sameMonth->count() === 1) {
                    $offer->suggestion = $sameMonth->first();
                    $offer->auto_match = true;
                } else {
                    $offerTs = \Carbon\Carbon::parse($offer->dErstellt)->timestamp;
                    $offer->suggestion = $occurrences->sortBy(
                        fn ($o) => abs(\Carbon\Carbon::parse($o->event_start_at)->timestamp - $offerTs)
                    )->first();
                }
            }

            return $offer;
        });
    }

    /**
     * Imports a JTL offer as an EventOfferVersion attached to the given EventOccurrence.
     */
    public function import(int $kAuftrag, int $occurrenceId): EventOfferVersion
    {
        $offer = DB::table('wawi_verkauf_tauftrag')
            ->where('kAuftrag', $kAuftrag)
            ->where('nType', 0)
            ->firstOrFail();

        $positions = DB::table('wawi_verkauf_tauftragposition')
            ->where('kAuftrag', $kAuftrag)
            ->orderBy('nSort')
            ->get();

        $occurrence = EventOccurrence::findOrFail($occurrenceId);

        $versionNumber = ($occurrence->offerVersions()->max('version_number') ?? 0) + 1;

        $version = EventOfferVersion::create([
            'event_occurrence_id'   => $occurrenceId,
            'external_offer_number' => $offer->cAuftragsNr,
            'version_number'        => $versionNumber,
            'source_system'         => 'jtl',
            'status'                => 'draft',
            'net_total_milli'       => 0,
            'gross_total_milli'     => 0,
        ]);

        foreach ($positions as $pos) {
            $unitNet   = (int) round((float) $pos->fVkNetto * 1_000_000);
            $qty       = (float) $pos->fAnzahl;
            $taxRate   = (float) $pos->fMwSt;
            $lineNet   = (int) round($unitNet * $qty);
            $lineGross = (int) round($lineNet * (1 + $taxRate / 100));

            EventOfferItem::create([
                'event_offer_version_id'  => $version->id,
                'external_article_number' => $pos->cArtNr ?? null,
                'name'                    => $pos->cName ?? '',
                'quantity'                => $qty,
                'unit'                    => $pos->cEinheit ?? 'Stk.',
                'unit_price_net_milli'    => $unitNet,
                'tax_rate'                => $taxRate,
                'line_total_net_milli'    => $lineNet,
                'line_total_gross_milli'  => $lineGross,
                'item_type'               => 'beverage',
                'sort_order'              => (int) ($pos->nSort ?? 0),
            ]);
        }

        $version->refresh();
        $version->update([
            'net_total_milli'   => $version->items->sum('line_total_net_milli'),
            'gross_total_milli' => $version->items->sum('line_total_gross_milli'),
        ]);

        EventImportLink::create([
            'entity_type'      => EventOfferVersion::class,
            'entity_id'        => $version->id,
            'source_system'    => 'jtl',
            'source_table'     => 'wawi_verkauf_tauftrag',
            'source_record_id' => (string) $kAuftrag,
            'external_number'  => $offer->cAuftragsNr,
            'imported_at'      => now(),
        ]);

        return $version;
    }

    /**
     * Creates a new EventOccurrence from JTL offer data (via ninox_kunden),
     * then imports the offer into it.
     *
     * Used when no EventOccurrence exists yet for the customer.
     */
    public function importWithNewOccurrence(int $kAuftrag): EventOfferVersion
    {
        $offer = DB::table('wawi_verkauf_tauftrag')
            ->where('kAuftrag', $kAuftrag)
            ->where('nType', 0)
            ->firstOrFail();

        $ninoxKunde = DB::table('ninox_kunden')
            ->where('kundennummer', $offer->cKundenNr)
            ->firstOrFail();

        // Find or create Customer
        $customer = Customer::firstOrCreate(
            ['ninox_kunden_id' => $ninoxKunde->ninox_id],
            [
                'company_name'  => trim((string) ($ninoxKunde->firmenname ?? '')),
                'first_name'    => trim((string) ($ninoxKunde->vorname ?? '')),
                'last_name'     => trim((string) ($ninoxKunde->nachname ?? '')),
                'email'         => $ninoxKunde->e_mail ?? null,
                'phone'         => $ninoxKunde->telefon ?? null,
                'active'        => true,
                'customer_number' => $ninoxKunde->kundennummer,
            ]
        );

        // Determine event date: prefer dVoraussichtlichesLieferdatum, fall back to dErstellt
        $eventDate = ! empty($offer->dVoraussichtlichesLieferdatum)
            ? \Carbon\Carbon::parse($offer->dVoraussichtlichesLieferdatum)
            : \Carbon\Carbon::parse($offer->dErstellt);

        $customerLabel = trim(implode(' ', array_filter([
            $customer->company_name,
            $customer->first_name,
            $customer->last_name,
        ])));

        $occurrence = EventOccurrence::create([
            'customer_id'    => $customer->id,
            'title'          => $customerLabel ?: $offer->cKundenNr,
            'event_type'     => 'einmalig',
            'event_start_at' => $eventDate,
            'event_status'   => 'planned',
            'offer_status'   => 'draft',
            'source_system'  => 'jtl',
            'source_table'   => 'wawi_verkauf_tauftrag',
            'source_record_id' => (string) $kAuftrag,
            'needs_review'   => true,
        ]);

        return $this->import($kAuftrag, $occurrence->id);
    }
}
