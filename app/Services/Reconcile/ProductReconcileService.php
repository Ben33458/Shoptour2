<?php

declare(strict_types=1);

namespace App\Services\Reconcile;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductBarcode;
use App\Models\Catalog\Warengruppe;
use App\Models\ReconcileFeedbackLog;
use App\Models\ReconcileProductRule;
use App\Models\SourceMatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Matches Ninox products (ninox_marktbestand) against JTL-WaWi products (wawi_artikel).
 *
 * The local `products` table starts empty. This service creates a cross-reference
 * between the two external systems so that matched pairs can later be imported as
 * a single canonical local product.
 *
 * Matching priority:
 *   1. EAN barcode (ninox.ean == wawi.cBarcode)  → 100 %
 *   2. Fuzzy article name (Levenshtein ≥ 75 %)   → variable
 *
 * Article-number comparison is intentionally skipped: Ninox uses its own numbering
 * system (2xxxx range) while WaWi uses a different one (5xxxx range).
 */
class ProductReconcileService
{
    // ── Lazy-loaded WaWi lookup maps ─────────────────────────────────────────

    /** lower(ean) → wawi row (object) */
    private array $wawiByEan = [];

    /** cArtNr → wawi row (object) */
    private array $wawiByArtNr = [];

    /** [{kArtikel, name_lower}] for fuzzy pass */
    private array $wawiAllNames = [];

    /** kArtikel → wawi row (object) */
    private array $wawiById = [];

    /** source_token → target_token for DB-stored synonym + noise rules */
    private array $dbSynonymMap = [];

    private bool $mapsBuilt = false;

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * @param  array{unmatched_only?: bool, status?: string}  $filters
     * @return list<array{
     *   source_id: string,
     *   source_data: array,
     *   match: object|null,
     *   confidence: int,
     *   match_method: string,
     *   existing_match: SourceMatch|null
     * }>
     */
    public function proposeMatches(array $filters = []): array
    {
        $this->buildLookupMaps();

        $query = DB::table('ninox_marktbestand')
            ->where(function ($q): void {
                $q->whereNull('zum_loeschen_markiert')
                  ->orWhere('zum_loeschen_markiert', 0);
            });

        if (isset($filters['search']) && $filters['search'] !== '') {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('artikelname', 'like', $term)
                  ->orWhere('artnummer', 'like', $term);
            });
        }

        $rows = $query->get()->all();

        $existingMatches = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
            ->where('source', SourceMatch::SOURCE_NINOX)
            ->get()
            ->keyBy('source_id');

        $results = [];

        foreach ($rows as $row) {
            $sourceId = (string) $row->ninox_id;
            $existing = $existingMatches->get($sourceId);

            if (($filters['unmatched_only'] ?? false) && $existing) {
                continue;
            }
            if (isset($filters['status']) && $existing?->status !== $filters['status']) {
                continue;
            }

            [$wawiMatch, $confidence, $method] = $this->findWawiMatch($row);

            // If confirmed, use stored wawi match from snapshot
            if ($existing?->status === SourceMatch::STATUS_CONFIRMED) {
                $storedWawiId = $existing->source_snapshot['_wawi_id'] ?? null;
                if ($storedWawiId && isset($this->wawiById[(int) $storedWawiId])) {
                    $wawiMatch  = $this->wawiById[(int) $storedWawiId];
                    $confidence = max($confidence, 100);
                    $method     = 'confirmed';
                }
            }

            $results[] = [
                'source_id'      => $sourceId,
                'source_data'    => (array) $row,
                'match'          => $wawiMatch,
                'confidence'     => $confidence,
                'match_method'   => $method,
                'existing_match' => $existing,
            ];
        }

        return $results;
    }

    /**
     * Import all confirmed matches as local products, or update already-imported ones.
     *
     * For each confirmed Ninox↔WaWi match:
     *   - local_id IS NULL  → create Product, ProductBarcode, update source_match
     *   - local_id IS NOT NULL → update show_in_shop and warengruppe_id if missing
     *
     * Warengruppen are auto-created from ninox_warengruppe.bezeichnung on first use.
     *
     * @return array{imported: int, updated: int, skipped: int, skipped_details: list<array{artnr:string,name:string,reason:string}>, errors: list<string>}
     */
    public function importConfirmed(int $userId): array
    {
        $imported        = 0;
        $updated         = 0;
        $skipped         = 0;
        $skippedDetails  = [];
        $errors          = [];

        // Pre-load Ninox warengruppen → local warengruppe_id map
        $warengruppMap = $this->buildWarengruppMap();

        $confirmedMatches = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
            ->where('source', SourceMatch::SOURCE_NINOX)
            ->where('status', SourceMatch::STATUS_CONFIRMED)
            ->get();

        // Build a map artikelnummer → product_id for collision detection
        $existingArtnr = Product::pluck('id', 'artikelnummer')->all();

        foreach ($confirmedMatches as $sourceMatch) {
            try {
                $snapshot = $sourceMatch->source_snapshot ?? [];
                $ninoxId  = $sourceMatch->source_id;
                $wawiId   = $snapshot['_wawi_id'] ?? null;

                // Resolve warengruppe_id from ninox field
                $ninoxWgId      = (int) ($snapshot['warengruppen'] ?? 0);
                $warengruppId   = $ninoxWgId > 0 ? ($warengruppMap[$ninoxWgId] ?? null) : null;

                // ── Already imported: update missing fields ───────────────────
                if ($sourceMatch->local_id !== null) {
                    $product = Product::find($sourceMatch->local_id);
                    if ($product) {
                        $changes = [];
                        if (! $product->show_in_shop) {
                            $changes['show_in_shop'] = true;
                        }
                        if ($product->warengruppe_id === null && $warengruppId !== null) {
                            $changes['warengruppe_id'] = $warengruppId;
                        }
                        if (! empty($changes)) {
                            $product->update($changes);
                            $updated++;
                        }
                    }
                    continue;
                }

                // ── New product ───────────────────────────────────────────────

                // Load live WaWi data
                $wawi = $wawiId
                    ? DB::table('wawi_artikel')->where('kArtikel', $wawiId)->first()
                    : null;

                // Determine artikelnummer: Ninox → WaWi → fallback
                $artnr = trim((string) ($snapshot['artnummer'] ?? ''));
                if ($artnr === '' && $wawi) {
                    $artnr = trim((string) ($wawi->cArtNr ?? ''));
                }
                if ($artnr === '') {
                    $artnr = 'N' . $ninoxId;
                }

                // Artikelnummer collision check
                if (isset($existingArtnr[$artnr])) {
                    $existingProduct = Product::find($existingArtnr[$artnr]);

                    // Same product already in DB (e.g. imported via WaWi) → just link it
                    if ($existingProduct && (
                        ($wawiId && (int) $existingProduct->wawi_artikel_id === (int) $wawiId) ||
                        (int) $existingProduct->ninox_artikel_id === (int) $ninoxId
                    )) {
                        $sourceMatch->update(['local_id' => $existingProduct->id]);
                        $updated++;
                        continue;
                    }

                    // Different product holds this artnr → fall back to N{ninoxId}
                    $fallbackArtnr = 'N' . $ninoxId;
                    if (isset($existingArtnr[$fallbackArtnr])) {
                        // Even the fallback is taken — skip to avoid data corruption
                        $skipped++;
                        $skippedDetails[] = [
                            'artnr'  => $artnr,
                            'name'   => $snapshot['artikelname'] ?? '—',
                            'reason' => sprintf(
                                'Artikelnummer „%s" bereits vergeben an „%s" (ID %d), Fallback „%s" ebenfalls belegt',
                                $artnr,
                                $existingProduct?->produktname ?? '?',
                                $existingArtnr[$artnr],
                                $fallbackArtnr
                            ),
                        ];
                        continue;
                    }

                    // Use fallback artnr and proceed with import
                    $artnr = $fallbackArtnr;
                }

                // Determine product name
                $produktname = trim((string) ($snapshot['artikelname'] ?? ''));
                if ($produktname === '' && $wawi) {
                    $produktname = trim((string) ($wawi->cName ?? ''));
                }
                if ($produktname === '') {
                    $produktname = 'Unbekannt';
                }

                // Calculate prices (tax_rate_id=1 = 19 %)
                $nettoFloat = null;
                if ($wawi && isset($wawi->fVKNetto) && (float) $wawi->fVKNetto > 0) {
                    $nettoFloat = (float) $wawi->fVKNetto;
                } elseif (isset($snapshot['vk_brutto_markt']) && (float) $snapshot['vk_brutto_markt'] > 0) {
                    $nettoFloat = (float) $snapshot['vk_brutto_markt'] / 1.19;
                }

                $nettoFloat  = $nettoFloat ?? 0.0;
                $bruttoFloat = $nettoFloat * 1.19;

                // milli-cents: EUR × 1_000_000 (1 EUR = 1_000_000 milli-cents)
                $netMilli   = (int) round($nettoFloat * 1_000_000);
                $grossMilli = (int) round($bruttoFloat * 1_000_000);

                // Generate unique slug from produktname
                $slug = $this->generateUniqueSlug($produktname);

                $product = Product::create([
                    'artikelnummer'          => $artnr,
                    'slug'                   => $slug,
                    'produktname'            => $produktname,
                    'tax_rate_id'            => 1,
                    'base_price_net_milli'   => $netMilli,
                    'base_price_gross_milli' => $grossMilli,
                    'availability_mode'      => Product::AVAILABILITY_AVAILABLE,
                    'active'                 => true,
                    'show_in_shop'           => true,
                    'is_bundle'              => false,
                    'warengruppe_id'         => $warengruppId,
                    'ninox_artikel_id'       => (int) $ninoxId,
                    'wawi_artikel_id'        => $wawiId ? (int) $wawiId : null,
                ]);

                // Create barcode if available in WaWi
                $barcode = trim((string) ($wawi->cBarcode ?? ''));
                if ($barcode !== '' && $barcode !== '0') {
                    ProductBarcode::create([
                        'product_id'   => $product->id,
                        'barcode'      => $barcode,
                        'barcode_type' => 'EAN-13',
                        'is_primary'   => true,
                    ]);
                }

                // Update Ninox-side match with local product id
                $sourceMatch->update(['local_id' => $product->id]);

                // Create/update WaWi-side match
                if ($wawiId) {
                    SourceMatch::updateOrCreate(
                        [
                            'entity_type' => SourceMatch::ENTITY_PRODUCT,
                            'source'      => SourceMatch::SOURCE_WAWI,
                            'source_id'   => (string) $wawiId,
                        ],
                        [
                            'local_id'     => $product->id,
                            'status'       => SourceMatch::STATUS_CONFIRMED,
                            'matched_by'   => $userId,
                            'confirmed_at' => now(),
                        ]
                    );
                }

                $existingArtnr[$artnr] = $product->id;
                $imported++;

            } catch (\Throwable $e) {
                Log::error('ProductReconcileService::importConfirmed error', [
                    'source_match_id' => $sourceMatch->id,
                    'message'         => $e->getMessage(),
                ]);
                $errors[] = "Match #{$sourceMatch->id}: {$e->getMessage()}";
            }
        }

        if ($imported > 0 || $updated > 0) {
            Cache::flush();
        }

        return [
            'imported'        => $imported,
            'updated'         => $updated,
            'skipped'         => $skipped,
            'skipped_details' => $skippedDetails,
            'errors'          => $errors,
        ];
    }

    /**
     * Builds a map of ninox_warengruppe.ninox_id → local warengruppen.id,
     * creating missing Warengruppe records on the fly.
     *
     * @return array<int, int>  ninox_id → local id
     */
    private function buildWarengruppMap(): array
    {
        $ninoxWgs = DB::table('ninox_warengruppe')
            ->select('ninox_id', 'bezeichnung')
            ->get();

        $map = [];

        foreach ($ninoxWgs as $wg) {
            $name  = trim((string) $wg->bezeichnung);
            if ($name === '') {
                continue;
            }

            $local = Warengruppe::firstOrCreate(
                ['name' => $name],
                ['active' => true]
            );

            $map[(int) $wg->ninox_id] = $local->id;
        }

        return $map;
    }

    /**
     * Auto-match all unmatched ninox products where confidence >= $minConfidence.
     *
     * @return array{auto_matched:int, skipped:int, already_done:int}
     */
    public function autoMatchAll(int $minConfidence = 90): array
    {
        $this->buildLookupMaps();

        $existingBySourceId = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
            ->where('source', SourceMatch::SOURCE_NINOX)
            ->pluck('source_id')->filter(fn ($v) => $v !== null)->flip()->all();

        $autoMatched = 0;
        $skipped     = 0;
        $alreadyDone = 0;

        $rows = DB::table('ninox_marktbestand')->get()->all();

        foreach ($rows as $row) {
            $sourceId = (string) $row->ninox_id;

            if (isset($existingBySourceId[$sourceId])) {
                $alreadyDone++;
                continue;
            }

            [$wawiMatch, $confidence, $method] = $this->findWawiMatch($row);

            if (! $wawiMatch || $confidence < $minConfidence) {
                $skipped++;
                continue;
            }

            $snapshot = array_merge((array) $row, ['_wawi_id' => $wawiMatch->kArtikel]);

            SourceMatch::create([
                'entity_type'     => SourceMatch::ENTITY_PRODUCT,
                'local_id'        => null,
                'source'          => SourceMatch::SOURCE_NINOX,
                'source_id'       => $sourceId,
                'status'          => SourceMatch::STATUS_AUTO,
                'matched_by'      => null,
                'source_snapshot' => $snapshot,
                'diff_at_match'   => $this->detectDiff((array) $row, (array) $wawiMatch),
                'confirmed_at'    => null,
            ]);

            $autoMatched++;
        }

        return ['auto_matched' => $autoMatched, 'skipped' => $skipped, 'already_done' => $alreadyDone];
    }

    /**
     * Confirm a ninox↔wawi product match (without creating a local product).
     */
    public function confirm(string $ninoxId, ?string $wawiId, int $userId, string $action = 'confirmed'): SourceMatch
    {
        $this->buildLookupMaps();

        $row  = DB::table('ninox_marktbestand')->where('ninox_id', $ninoxId)->first();
        $wawi = $wawiId ? DB::table('wawi_artikel')->where('kArtikel', $wawiId)->first() : null;

        $snapshot = array_merge(
            $row ? (array) $row : [],
            $wawi ? ['_wawi_id' => $wawi->kArtikel] : []
        );

        $priorStatus = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
            ->where('source', SourceMatch::SOURCE_NINOX)
            ->where('source_id', $ninoxId)
            ->value('status');

        $match = SourceMatch::updateOrCreate(
            [
                'entity_type' => SourceMatch::ENTITY_PRODUCT,
                'source'      => SourceMatch::SOURCE_NINOX,
                'source_id'   => $ninoxId,
            ],
            [
                'local_id'        => null,
                'status'          => SourceMatch::STATUS_CONFIRMED,
                'matched_by'      => $userId,
                'source_snapshot' => $snapshot,
                'diff_at_match'   => $row && $wawi ? $this->detectDiff((array) $row, (array) $wawi) : [],
                'confirmed_at'    => now(),
            ]
        );

        // Feedback log
        [, $confidence, $method] = $row ? $this->findWawiMatch($row) : [null, 0, 'none'];
        ReconcileFeedbackLog::create([
            'entity_type'    => SourceMatch::ENTITY_PRODUCT,
            'source'         => SourceMatch::SOURCE_NINOX,
            'source_id'      => $ninoxId,
            'action'         => $action,
            'user_id'        => $userId,
            'source_name'    => $row?->artikelname,
            'source_artnr'   => $row?->artnummer,
            'source_ean'     => $row?->ean,
            'target_id'      => $wawiId,
            'target_name'    => $wawi?->cName,
            'confidence'     => $confidence,
            'match_method'   => $method,
            'was_auto_match' => $priorStatus === SourceMatch::STATUS_AUTO,
        ]);

        return $match;
    }

    /**
     * Bestätigt alle Auto-Matches (der Threshold wurde bereits beim Auto-Matching angewendet).
     */
    public function confirmAllAbove(int $userId, int $minConfidence = 96): int
    {
        $confirmed = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
            ->where('source', SourceMatch::SOURCE_NINOX)
            ->where('status', SourceMatch::STATUS_AUTO)
            ->update([
                'status'       => SourceMatch::STATUS_CONFIRMED,
                'matched_by'   => $userId,
                'confirmed_at' => now(),
            ]);

        return $confirmed;
    }

    public function ignore(string $ninoxId, ?int $userId = null, string $action = 'ignored'): SourceMatch
    {
        $this->buildLookupMaps();

        $row = DB::table('ninox_marktbestand')->where('ninox_id', $ninoxId)->first();

        $priorStatus = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
            ->where('source', SourceMatch::SOURCE_NINOX)
            ->where('source_id', $ninoxId)
            ->value('status');

        $priorSnapshot = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
            ->where('source', SourceMatch::SOURCE_NINOX)
            ->where('source_id', $ninoxId)
            ->value('source_snapshot');

        $match = SourceMatch::updateOrCreate(
            [
                'entity_type' => SourceMatch::ENTITY_PRODUCT,
                'source'      => SourceMatch::SOURCE_NINOX,
                'source_id'   => $ninoxId,
            ],
            [
                'local_id'        => null,
                'status'          => SourceMatch::STATUS_IGNORED,
                'source_snapshot' => $row ? (array) $row : [],
            ]
        );

        // Feedback log — include prior wawi match if one existed
        [, $confidence, $method] = $row ? $this->findWawiMatch($row) : [null, 0, 'none'];
        $priorWawiId   = $priorSnapshot['_wawi_id'] ?? null;
        $priorWawiName = $priorWawiId ? ($this->wawiById[(int) $priorWawiId]?->cName ?? null) : null;

        ReconcileFeedbackLog::create([
            'entity_type'    => SourceMatch::ENTITY_PRODUCT,
            'source'         => SourceMatch::SOURCE_NINOX,
            'source_id'      => $ninoxId,
            'action'         => $action,
            'user_id'        => $userId,
            'source_name'    => $row?->artikelname,
            'source_artnr'   => $row?->artnummer,
            'source_ean'     => $row?->ean,
            'target_id'      => $priorWawiId ? (string) $priorWawiId : null,
            'target_name'    => $priorWawiName,
            'confidence'     => $confidence,
            'match_method'   => $method,
            'was_auto_match' => $priorStatus === SourceMatch::STATUS_AUTO,
        ]);

        return $match;
    }

    /** @return array{total:int, wawi_total:int, auto:int, confirmed:int, confirmed_pending:int, ignored:int, unmatched:int} */
    public function stats(): array
    {
        $total      = DB::table('ninox_marktbestand')->count();
        $wawiTotal  = DB::table('wawi_artikel')->count();

        $matched = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
            ->where('source', SourceMatch::SOURCE_NINOX)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $confirmedPending = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
            ->where('source', SourceMatch::SOURCE_NINOX)
            ->where('status', SourceMatch::STATUS_CONFIRMED)
            ->whereNull('local_id')
            ->count();

        $done = ($matched['auto'] ?? 0) + ($matched['confirmed'] ?? 0) + ($matched['ignored'] ?? 0);

        return [
            'total'             => $total,
            'wawi_total'        => $wawiTotal,
            'auto'              => $matched['auto'] ?? 0,
            'confirmed'         => $matched['confirmed'] ?? 0,
            'confirmed_pending' => $confirmedPending,
            'ignored'           => $matched['ignored'] ?? 0,
            'unmatched'         => max(0, $total - $done),
        ];
    }

    /**
     * Detects field differences between a ninox and wawi product record.
     *
     * @return array<string, array{ninox: string, wawi: string}>
     */
    public function detectDiff(array $ninox, array $wawi): array
    {
        $diff = [];

        $this->cmpProduct($diff, 'Artikelname',
            $ninox['artikelname'] ?? null,
            $wawi['cName'] ?? null
        );
        $this->cmpProduct($diff, 'VK-Preis (brutto)',
            isset($ninox['vk_brutto_markt']) ? number_format((float) $ninox['vk_brutto_markt'], 2) : null,
            isset($wawi['fVKNetto']) ? number_format((float) $wawi['fVKNetto'], 2) . ' (netto)' : null
        );

        return $diff;
    }

    // =========================================================================
    // Lookup map
    // =========================================================================

    /** Forces a reload of lookup maps (e.g. after saving new DB rules). */
    public function resetMaps(): void
    {
        $this->mapsBuilt    = false;
        $this->wawiByEan    = [];
        $this->wawiByArtNr  = [];
        $this->wawiAllNames = [];
        $this->wawiById     = [];
        $this->dbSynonymMap = [];
    }

    private function buildLookupMaps(): void
    {
        if ($this->mapsBuilt) {
            return;
        }

        DB::table('wawi_artikel')->get()->each(function (object $w): void {
            $this->wawiById[(int) $w->kArtikel] = $w;

            if ($w->cArtNr && trim($w->cArtNr) !== '') {
                $this->wawiByArtNr[trim($w->cArtNr)] = $w;
            }

            if ($w->cBarcode && trim($w->cBarcode) !== '') {
                $this->wawiByEan[strtolower(trim($w->cBarcode))] = $w;
            }

            if ($w->cName && trim($w->cName) !== '') {
                $this->wawiAllNames[] = [
                    'kArtikel' => (int) $w->kArtikel,
                    'name'     => strtolower(trim($w->cName)),
                ];
            }
        });

        // Load active DB rules into synonym map
        ReconcileProductRule::where('active', true)->get()->each(function (ReconcileProductRule $rule): void {
            $this->dbSynonymMap[$rule->source_token] = $rule->target_token;
        });

        $this->mapsBuilt = true;
    }

    // =========================================================================
    // Matching
    // =========================================================================

    /** @return array{0:object|null, 1:int, 2:string} */
    private function findWawiMatch(object $ninoxRow): array
    {
        // ── Rule 0: artnrkolabrikasten == WaWi cArtNr (exact article-number) ─
        $kolabriArtNr = trim((string) ($ninoxRow->artnrkolabrikasten ?? ''));
        if ($kolabriArtNr !== '' && isset($this->wawiByArtNr[$kolabriArtNr])) {
            return [$this->wawiByArtNr[$kolabriArtNr], 100, 'artnr'];
        }

        // ── Rule 1: EAN exact ─────────────────────────────────────────────────
        $ean = $ninoxRow->ean ?? null;
        if ($ean && trim((string) $ean) !== '') {
            $eanKey = strtolower(trim((string) $ean));
            if (isset($this->wawiByEan[$eanKey])) {
                return [$this->wawiByEan[$eanKey], 100, 'ean'];
            }
        }

        // ── Rule 2: Gebinde-aware fuzzy name ─────────────────────────────────
        $ninoxName = strtolower(trim((string) ($ninoxRow->artikelname ?? '')));
        if (! $ninoxName) {
            return [null, 0, 'none'];
        }

        $ninoxGebinde = $this->parseGebinde($ninoxName);
        $ninoxBase    = $this->stripGebindeart(
            $ninoxGebinde !== null ? $this->stripGebinde($ninoxName) : $ninoxName
        );

        $bestId   = null;
        $bestConf = 0;

        foreach ($this->wawiAllNames as ['kArtikel' => $kArtikel, 'name' => $wawiName]) {
            $wawiGebinde = $this->parseGebinde($wawiName);

            // Hard exclusion: both have Gebinde but they differ
            // Tolerance of 0.04 l handles data-entry shorthand in Ninox, e.g. "0,3 l" for standard 0,33 l (33 cl)
            if ($ninoxGebinde !== null && $wawiGebinde !== null) {
                if ($ninoxGebinde['count'] !== $wawiGebinde['count']
                    || abs($ninoxGebinde['volume'] - $wawiGebinde['volume']) > 0.04) {
                    continue;
                }
            }

            // Compare base names (Gebinde + Gebindeart stripped)
            $wawiBase = $this->stripGebindeart(
                $wawiGebinde !== null ? $this->stripGebinde($wawiName) : $wawiName
            );

            $conf = $this->levenshteinPercent($ninoxBase, $wawiBase);
            if ($conf > $bestConf) {
                $bestConf = $conf;
                $bestId   = $kArtikel;
            }
        }

        if ($bestId !== null && $bestConf >= 75) {
            $method = $ninoxGebinde !== null ? 'fuzzy_gebinde' : 'fuzzy_name';
            return [$this->wawiById[$bestId], $bestConf, $method];
        }

        return [null, 0, 'none'];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function levenshteinPercent(string $a, string $b): int
    {
        $a   = $this->normalizeForMatching($a);
        $b   = $this->normalizeForMatching($b);
        $max = max(strlen($a), strlen($b));
        if ($max === 0) {
            return 100;
        }
        return (int) round((1 - levenshtein($a, $b) / $max) * 100);
    }

    /**
     * Normalizes a product name for fuzzy comparison.
     *
     * Pipeline (applied to both sides identically):
     *  1. Lowercase
     *  2. German umlauts → ASCII (ä→ae, ß→ss, …)
     *  3. Remaining accents stripped (é→e, ç→c, …)
     *  4. Punctuation (hyphens, dots) → space, so "johannisb.-nektar" splits
     *     into tokens and abbreviations get a fair Levenshtein score
     *  5. Synonym normalisation per token (weissbier→weizen, steini→stubbi)
     *  6. All whitespace removed → "odenwald quelle" ≡ "odenwaldquelle"
     *
     * After step 6 the string is pure ASCII with no spaces, safe for PHP's
     * byte-oriented levenshtein().
     */
    private function normalizeForMatching(string $s): string
    {
        $s = mb_strtolower(trim($s));

        // German umlauts (before accent stripping to avoid ä→a instead of ae)
        $s = strtr($s, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'æ' => 'ae', 'ø' => 'oe', 'å' => 'aa',
        ]);

        // Remaining accents
        $s = strtr($s, [
            'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ā'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','ē'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ī'=>'i',
            'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ō'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ū'=>'u',
            'ý'=>'y','ÿ'=>'y',
            'ç'=>'c','ć'=>'c','č'=>'c',
            'ñ'=>'n','ń'=>'n',
            'ş'=>'s','š'=>'s','ś'=>'s',
            'ž'=>'z','ź'=>'z','ż'=>'z',
            'ř'=>'r','ľ'=>'l','ł'=>'l','đ'=>'d',
        ]);

        // Verbinder &, + und standalone "und" → Leerzeichen
        // → "K & K" = "K+K" = "K und K"; "1und1" = "1+1" = "1&1"
        $s = preg_replace('/\s*[&+]\s*/', ' ', $s);
        $s = preg_replace('/\bund\b/u', ' ', $s);

        // Punctuation → space (splits abbreviations into separate tokens)
        $s = preg_replace('/[\.\-_\/]+/', ' ', $s) ?? $s;

        // Phrase-level normalisations (applied before tokenisation)
        // "Ice Tea" (English) is a filler phrase for some brands (e.g. Elephant Bay):
        //   "Elephant Bay Ice Tea Peach" ≡ "Elephant Bay Peach"
        // Note: German "Eistee" is a single token and intentionally left untouched.
        $s = preg_replace('/\bice\s+tea\b/', '', $s) ?? $s;

        // Synonym normalisation per token
        $tokens = preg_split('/\s+/', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_map([$this, 'normalizeSynonymToken'], $tokens);
        $s      = implode(' ', $tokens);

        // Remove all whitespace → space-insensitive matching
        return preg_replace('/\s+/', '', $s) ?? $s;
    }

    /**
     * Maps known synonym tokens to a single canonical form.
     * Input is already lowercase + umlaut-expanded ASCII.
     * Extend this list as new synonym pairs are discovered.
     */
    private function normalizeSynonymToken(string $token): string
    {
        // DB rules take precedence over hardcoded ones
        if (array_key_exists($token, $this->dbSynonymMap)) {
            return $this->dbSynonymMap[$token];
        }

        return match ($token) {
            // Beer-style synonyms
            'weissbier', 'weisbier', 'weizbier' => 'weizen',
            // Bottle-size synonyms
            'steini'                             => 'stubbi',
            // Fruit abbreviations (common in Ninox data entry)
            'joh'                                => 'johannisbeere',
            'schw'                               => 'schwarz',
            'klass'                              => 'klassisch',
            'alkfr', 'af'                        => 'alkoholfrei',
            // Brand abbreviations (Ninox uses short form, WaWi uses full brand name)
            // "Eli" and "Elisabethen" are both short for "Elisabethenquelle"
            // Note: "Elisabethen Quelle" (2 words) normalises identically via whitespace removal
            'eli', 'elisabethen'                 => 'elisabethenquelle',
            // DE/EN fruit name equivalents
            'pfirsich'                           => 'peach',
            // Packaging/product-line qualifiers (Ninox: "Exclusiv", WaWi: "Gastro")
            'exclusiv', 'exclusive'              => 'gastro',
            // Quality/style qualifiers that appear inconsistently between Ninox and WaWi
            // (word order varies, e.g. "Naturtrübe Blutorange" vs "Blutorange Naturtrüb")
            'naturtruebe', 'naturtrueb'          => '',
            default                              => $token,
        };
    }

    private function cmpProduct(array &$diff, string $field, mixed $ninox, mixed $wawi): void
    {
        $n = $ninox !== null ? trim((string) $ninox) : null;
        $w = $wawi  !== null ? trim((string) $wawi)  : null;
        if ($n !== null && $n !== '' && $w !== null && $w !== '' && $n !== $w) {
            $diff[$field] = ['ninox' => $n, 'wawi' => $w];
        }
    }

    /**
     * Parses the Gebinde (packaging unit) from a product name.
     *
     * Handles multi-packs  (e.g. "6x1,5 l", "6 X 1.5L", "6x500ml", "12x0,33 cl")
     * and single bottles   (e.g. "1,5 l", "500ml", "0,5l", "33cl").
     *
     * Volume is always stored in litres for unit-agnostic comparison:
     *   500 ml → 0.5 l,  33 cl → 0.33 l,  1,00 l → 1.0 l
     *
     * @return array{count:int, volume:float}|null
     */
    private function parseGebinde(string $name): ?array
    {
        // Unit is optional — "6x1,5" matches the same as "6x1,5 l".
        // Multi-pack: 6x1,5 l / 6x500ml / 6x1,5 / 12x0,5
        if (preg_match('/(\d+)\s*[xX]\s*(\d+(?:[,.]\d+)?)\s*(ml|cl|l)?(?:\b|$)/iu', $name, $m)) {
            $rawUnit = strtolower($m[3] ?? 'l');
            return [
                'count'  => (int) $m[1],
                'volume' => $this->toLitres((float) str_replace(',', '.', $m[2]), $rawUnit),
            ];
        }

        // Single bottle with decimal (unit optional): 1,5 l / 500ml / 1,5 / 0,33
        // Require a decimal to avoid matching lone integers like "6"
        if (preg_match('/(?<![xX\d])\b(\d+[,.]\d+)\s*(ml|cl|l)?(?:\b|$)/iu', $name, $m)) {
            $rawUnit = strtolower($m[2] ?? 'l');
            return [
                'count'  => 1,
                'volume' => $this->toLitres((float) str_replace(',', '.', $m[1]), $rawUnit),
            ];
        }

        // Single bottle with explicit unit, no decimal: "1 l", "2 l", "500 ml"
        if (preg_match('/(?<![xX\d])\b(\d+)\s*(ml|cl|l)(?:\b|$)/iu', $name, $m)) {
            return [
                'count'  => 1,
                'volume' => $this->toLitres((float) $m[1], strtolower($m[2])),
            ];
        }

        return null;
    }

    /**
     * Strips container-type and deposit-scheme tokens from a product name.
     *
     * Tokens are matched as whole words anywhere in the string (not just end),
     * so "6x1,5 l PET Ka" and "6x1,5 l" both reduce to the same base.
     *
     * Known tokens:
     *   ka / kasten  – crate
     *   pet          – PET plastic
     *   glas         – glass bottle
     *   pec          – variant spelling of PET
     *   ew           – Einweg (single-use)
     *   mw           – Mehrweg (returnable) — kept separate, rarely causes false positives
     *   dpg          – Deutsches Pfandsystem (deposit label)
     *   fass         – keg/barrel
     *   dose/dosen   – can
     *   can          – can (English)
     *   keg          – keg (English)
     */
    private function stripGebindeart(string $name): string
    {
        $tokens = 'kasten|ka|pet|pec|pe|glas|ge|ew|mw|dpg|fass|dosen|dose|can|keg|bottle|fl';
        $name   = preg_replace('/(?:^|\s+)\b(?:' . $tokens . ')\b(?:\s+|$)/iu', ' ', $name) ?? $name;

        return trim($name);
    }

    /** Converts a volume value to litres based on its unit. */
    private function toLitres(float $value, string $unit): float
    {
        return match ($unit) {
            'ml'    => $value / 1000,
            'cl'    => $value / 100,
            default => $value,          // 'l'
        };
    }

    /**
     * Removes the Gebinde portion from a product name, returning only brand + product type.
     *
     * Examples:
     *   "rosbacher naturell 6x1,5 l"  → "rosbacher naturell"
     *   "rosbacher naturell 500ml"     → "rosbacher naturell"
     */
    private function stripGebinde(string $name): string
    {
        // Multi-pack (unit optional): 6x1,5 l / 6x500ml / 6x1,5
        $name = preg_replace('/\s*\d+\s*[xX]\s*\d+(?:[,.]\d+)?\s*(?:ml|cl|l)?(?:\b|$)/iu', '', $name);
        // Single bottle with decimal (unit optional): 1,5 l / 500ml / 1,5
        $name = preg_replace('/\s*(?<![xX\d])\b\d+[,.]\d+\s*(?:ml|cl|l)?(?:\b|$)/iu', '', $name);
        // Single bottle with explicit unit, no decimal: 1 l / 500 ml
        $name = preg_replace('/\s*(?<![xX\d])\b\d+\s*(?:ml|cl|l)(?:\b|$)/iu', '', $name);

        return trim((string) $name);
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'produkt';
        }

        $slug    = $base;
        $counter = 2;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    // =========================================================================
    // Rule suggestion (learns from confirmed manual matches)
    // =========================================================================

    /**
     * Analyses confirmed Ninox↔WaWi matches that the auto-matcher could not find
     * (confidence < 90 % or matched to a different WaWi article) and returns
     * token-level suggestions for new synonym rules and noise tokens.
     *
     * @param  int  $minFrequency  Minimum number of pairs a pattern must appear in
     * @return array{
     *   total_analyzed: int,
     *   skipped_auto: int,
     *   synonyms: list<array{ninox_token:string, wawi_token:string, count:int, examples:list}>,
     *   noise_ninox: list<array{token:string, count:int, examples:list<string>}>,
     *   noise_wawi:  list<array{token:string, count:int, examples:list<string>}>,
     * }
     */
    public function suggestRules(int $minFrequency = 2): array
    {
        $this->buildLookupMaps();

        $confirmed = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
            ->where('source', SourceMatch::SOURCE_NINOX)
            ->where('status', SourceMatch::STATUS_CONFIRMED)
            ->get()
            ->filter(fn ($m): bool => ! empty($m->source_snapshot['_wawi_id']));

        $pairCounts        = [];   // 'ninoxTok→wawiTok' => int
        $pairExamples      = [];   // key => [{ninox,wawi}]
        $ninoxNoiseCounts  = [];   // token => int
        $ninoxNoiseExamples = [];
        $wawiNoiseCounts   = [];
        $wawiNoiseExamples = [];
        $skippedAuto       = 0;

        foreach ($confirmed as $match) {
            $ninoxId = $match->source_id;
            $wawiId  = $match->source_snapshot['_wawi_id'] ?? null;
            if (! $wawiId) {
                continue;
            }

            $ninoxRow = DB::table('ninox_marktbestand')->where('ninox_id', $ninoxId)->first();
            $wawi     = $this->wawiById[(int) $wawiId] ?? null;
            if (! $ninoxRow || ! $wawi) {
                continue;
            }

            // Skip pairs the auto-matcher already handles correctly
            [$autoMatch, $confidence] = $this->findWawiMatch($ninoxRow);
            if ($confidence >= 90 && $autoMatch && (int) $autoMatch->kArtikel === (int) $wawiId) {
                $skippedAuto++;
                continue;
            }

            $ninoxName   = (string) ($ninoxRow->artikelname ?? '');
            $wawiName    = (string) ($wawi->cName ?? '');
            $ninoxTokens = $this->getRawTokensForAnalysis($ninoxName);
            $wawiTokens  = $this->getRawTokensForAnalysis($wawiName);

            $onlyNinox = array_values(array_diff($ninoxTokens, $wawiTokens));
            $onlyWawi  = array_values(array_diff($wawiTokens, $ninoxTokens));

            // Synonym candidate: exactly one unmatched token on each side
            if (count($onlyNinox) === 1 && count($onlyWawi) === 1) {
                $nTok = $onlyNinox[0];
                $wTok = $onlyWawi[0];
                if (mb_strlen($nTok) >= 2 && mb_strlen($wTok) >= 2 && $nTok !== $wTok) {
                    $key               = $nTok . '→' . $wTok;
                    $pairCounts[$key]   = ($pairCounts[$key] ?? 0) + 1;
                    $pairExamples[$key][] = ['ninox' => $ninoxName, 'wawi' => $wawiName];
                }
            }

            // Ninox-only noise: one or more extra tokens in Ninox, none in WaWi
            if (count($onlyNinox) >= 1 && count($onlyWawi) === 0) {
                foreach ($onlyNinox as $tok) {
                    if (mb_strlen($tok) >= 2) {
                        $ninoxNoiseCounts[$tok]    = ($ninoxNoiseCounts[$tok] ?? 0) + 1;
                        $ninoxNoiseExamples[$tok][] = $ninoxName;
                    }
                }
            }

            // WaWi-only noise: extra tokens in WaWi, none in Ninox
            if (count($onlyWawi) >= 1 && count($onlyNinox) === 0) {
                foreach ($onlyWawi as $tok) {
                    if (mb_strlen($tok) >= 2) {
                        $wawiNoiseCounts[$tok]    = ($wawiNoiseCounts[$tok] ?? 0) + 1;
                        $wawiNoiseExamples[$tok][] = $wawiName;
                    }
                }
            }
        }

        arsort($pairCounts);
        arsort($ninoxNoiseCounts);
        arsort($wawiNoiseCounts);

        $synonyms = [];
        foreach ($pairCounts as $key => $count) {
            if ($count < $minFrequency) {
                continue;
            }
            [$nTok, $wTok] = explode('→', $key, 2);
            // Skip pairs already covered by the synonym map
            if ($this->normalizeSynonymToken($nTok) !== $nTok) {
                continue;
            }
            $synonyms[] = [
                'ninox_token' => $nTok,
                'wawi_token'  => $wTok,
                'count'       => $count,
                'examples'    => array_slice($pairExamples[$key] ?? [], 0, 3),
            ];
        }

        $noiseNinox = [];
        foreach ($ninoxNoiseCounts as $tok => $count) {
            if ($count < $minFrequency) {
                continue;
            }
            if ($this->normalizeSynonymToken($tok) !== $tok) {
                continue;
            }
            $noiseNinox[] = [
                'token'    => $tok,
                'count'    => $count,
                'examples' => array_unique(array_slice($ninoxNoiseExamples[$tok] ?? [], 0, 3)),
            ];
        }

        $noiseWawi = [];
        foreach ($wawiNoiseCounts as $tok => $count) {
            if ($count < $minFrequency) {
                continue;
            }
            if ($this->normalizeSynonymToken($tok) !== $tok) {
                continue;
            }
            $noiseWawi[] = [
                'token'    => $tok,
                'count'    => $count,
                'examples' => array_unique(array_slice($wawiNoiseExamples[$tok] ?? [], 0, 3)),
            ];
        }

        return [
            'total_analyzed' => $confirmed->count(),
            'skipped_auto'   => $skippedAuto,
            'synonyms'       => $synonyms,
            'noise_ninox'    => $noiseNinox,
            'noise_wawi'     => $noiseWawi,
        ];
    }

    /**
     * Returns the normalised token set for a product name, suitable for
     * comparing which tokens differ between a Ninox and a WaWi name.
     *
     * Applies the same normalisation pipeline as normalizeForMatching() but
     * WITHOUT synonym substitution (so we can detect NEW synonym candidates)
     * and WITHOUT whitespace collapsing (so we keep individual tokens).
     * Gebinde patterns and Gebindeart tokens are stripped so they don't
     * pollute the diff analysis.
     */
    private function getRawTokensForAnalysis(string $name): array
    {
        $s = mb_strtolower(trim($name));

        // Umlauts
        $s = strtr($s, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'æ' => 'ae', 'ø' => 'oe', 'å' => 'aa',
        ]);

        // Remaining accents
        $s = strtr($s, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ş' => 's', 'š' => 's',
        ]);

        // Connectors
        $s = preg_replace('/\s*[&+]\s*/', ' ', $s) ?? $s;
        $s = preg_replace('/\bund\b/u', ' ', $s) ?? $s;

        // Punctuation → space
        $s = preg_replace('/[\.\-_\/]+/', ' ', $s) ?? $s;

        // Strip "ice tea"
        $s = preg_replace('/\bice\s+tea\b/iu', '', $s) ?? $s;

        // Strip Gebinde patterns (6x1,5l, 500ml, 1,5 l, etc.)
        $s = preg_replace('/\d+\s*[xX]\s*[\d,.]+\s*(?:ml|cl|l)?/iu', '', $s) ?? $s;
        $s = preg_replace('/[\d,.]+\s*(?:ml|cl|l)\b/iu', '', $s) ?? $s;
        $s = preg_replace('/\b\d+\b/', '', $s) ?? $s;

        // Strip Gebindeart tokens (same list as stripGebindeart)
        $tokens = 'kasten|ka|pet|pec|pe|glas|ge|ew|mw|dpg|fass|dosen|dose|can|keg|bottle|fl';
        $s      = preg_replace('/\b(?:' . $tokens . ')\b/iu', '', $s) ?? $s;

        return array_values(array_filter(
            preg_split('/\s+/', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            static fn ($t): bool => mb_strlen($t) >= 2,
        ));
    }
}
