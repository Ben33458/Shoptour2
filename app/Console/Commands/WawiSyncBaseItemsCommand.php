<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Syncs the base-item (Einzelflasche) product hierarchy from JTL WaWi.
 *
 * Three discovery methods, run in order (each only processes products not yet linked):
 *
 *   Method A — WaWi Stückliste (BOM):
 *     wawi_dbo_tstueckliste.kVaterArtikel → kArtikel
 *     Authoritative when the Stückliste has been maintained in WaWi.
 *
 *   Method B — EAN-strip heuristic:
 *     If a case product has a 14-digit Kisten-EAN starting with "1",
 *     strip the leading "1" → look up the resulting 13-digit EAN in
 *     wawi_dbo_tartikel.cBarcode to find the bottle article.
 *
 *   Method C — Product name parsing:
 *     Case names follow "{product name} {qty}x{volume}" (e.g. "Schmucker Pils 24x0,33 l").
 *     Strip the "{qty}x..." suffix → use the exact prefix to find the base article in
 *     wawi_artikel where cName starts with that prefix and has no NxV pattern.
 *     Disambiguated by volume when multiple matches exist.
 *
 * For each discovered bottle:
 *   - If a product with that wawi_artikel_id already exists → reuse it
 *   - Otherwise → create a stub product (active=false, show_in_shop=false)
 * Then set base_item_product_id on the case product and is_base_item=1
 * on the bottle product.
 *
 * Run this after every WaWi import to keep the hierarchy in sync.
 */
class WawiSyncBaseItemsCommand extends Command
{
    protected $signature = 'wawi:sync-base-items
                            {--dry-run : Show what would be done without writing}';

    protected $description = 'Sync base-item (Einzelflasche) products and case→bottle links from WaWi BOM + EAN heuristic';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY-RUN — no changes will be written.');
        }

        $now              = now()->toDateTimeString();
        $defaultTaxRateId = DB::table('tax_rates')->value('id') ?? 1;

        // bottle_wawi_id → products.id (to deduplicate across both methods)
        $bottleProductMap = [];

        $stats = [
            'base_created_bom'  => 0,
            'base_created_ean'  => 0,
            'base_created_name' => 0,
            'base_reused'       => 0,
            'cases_linked'      => 0,
            'already_ok'        => 0,
            'name_ambiguous'    => 0,
            'name_no_hit'       => 0,
        ];

        // ── Method A: WaWi Stückliste (BOM) ──────────────────────────────────

        $bom = DB::select("
            SELECT
                p.id                  AS case_product_id,
                p.company_id,
                s.kArtikel            AS bottle_wawi_id,
                ta.cArtNr             AS bottle_artnr,
                ta.cBarcode           AS bottle_barcode,
                s.fAnzahl             AS qty_per_case,
                tb.cName              AS bottle_name,
                p_existing.id         AS existing_base_product_id
            FROM products p
            JOIN wawi_dbo_tstueckliste s
                ON s.kVaterArtikel = p.wawi_artikel_id
            JOIN wawi_dbo_tartikel ta
                ON ta.kArtikel = s.kArtikel
            LEFT JOIN wawi_dbo_tartikelbeschreibung tb
                ON tb.kArtikel = s.kArtikel AND tb.kSprache = 1
            LEFT JOIN products p_existing
                ON p_existing.wawi_artikel_id = s.kArtikel
            WHERE p.active = 1
              AND p.wawi_artikel_id IS NOT NULL
        ");

        $this->info('Method A (BOM): ' . count($bom) . ' case products found');

        foreach ($bom as $row) {
            $baseId = $this->ensureBaseItem(
                (int)   $row->bottle_wawi_id,
                (string)$row->bottle_artnr,
                (string)($row->bottle_name ?? $row->bottle_artnr),
                $row->company_id,
                $row->existing_base_product_id ? (int) $row->existing_base_product_id : null,
                $defaultTaxRateId,
                $now,
                $dryRun,
                $bottleProductMap,
                $stats,
                'bom',
            );

            $this->linkCase((int) $row->case_product_id, $baseId, $now, $dryRun, $stats);
        }

        // ── Method B: EAN-strip heuristic ─────────────────────────────────────
        // Only for active products not yet linked by BOM.

        $eanCandidates = DB::select("
            SELECT
                p.id                  AS case_product_id,
                p.company_id,
                wa_case.cBarcode      AS case_barcode,
                SUBSTRING(wa_case.cBarcode, 2) AS bottle_ean,
                wa_bottle.kArtikel    AS bottle_wawi_id,
                wa_bottle.cArtNr     AS bottle_artnr,
                tb.cName              AS bottle_name,
                p_existing.id         AS existing_base_product_id
            FROM products p
            JOIN wawi_dbo_tartikel wa_case
                ON wa_case.kArtikel = p.wawi_artikel_id
            JOIN wawi_dbo_tartikel wa_bottle
                ON wa_bottle.cBarcode = SUBSTRING(wa_case.cBarcode, 2)
            LEFT JOIN wawi_dbo_tartikelbeschreibung tb
                ON tb.kArtikel = wa_bottle.kArtikel AND tb.kSprache = 1
            LEFT JOIN products p_existing
                ON p_existing.wawi_artikel_id = wa_bottle.kArtikel
            WHERE p.active = 1
              AND p.wawi_artikel_id IS NOT NULL
              AND p.base_item_product_id IS NULL
              AND LENGTH(wa_case.cBarcode) = 14
              AND LEFT(wa_case.cBarcode, 1) = '1'
              AND wa_case.cBarcode != wa_bottle.cBarcode
        ");

        $this->info('Method B (EAN-strip): ' . count($eanCandidates) . ' additional case products found');

        foreach ($eanCandidates as $row) {
            $baseId = $this->ensureBaseItem(
                (int)   $row->bottle_wawi_id,
                (string)$row->bottle_artnr,
                (string)($row->bottle_name ?? $row->bottle_artnr),
                $row->company_id,
                $row->existing_base_product_id ? (int) $row->existing_base_product_id : null,
                $defaultTaxRateId,
                $now,
                $dryRun,
                $bottleProductMap,
                $stats,
                'ean',
            );

            $this->linkCase((int) $row->case_product_id, $baseId, $now, $dryRun, $stats);
        }

        // ── Method C: product name parsing ───────────────────────────────────
        // Only for active products still not linked after A and B.

        $stillUnlinked = DB::table('products')
            ->where('active', true)
            ->whereNull('base_item_product_id')
            ->where('is_base_item', false)
            ->whereNotNull('wawi_artikel_id')
            ->select('id', 'company_id', 'produktname')
            ->get();

        $this->info('Method C (name-parse): ' . $stillUnlinked->count() . ' products to check');

        foreach ($stillUnlinked as $row) {
            // Parse "{prefix} {qty}x {volume}{unit}" — prefix is everything before the qty
            if (! preg_match('/^(.+?)\s+(\d+)x\s*([\d,]+)\s*(l|ml|cl)\b/i', $row->produktname, $m)) {
                continue; // no NxV pattern — not a case, skip
            }

            $prefix  = trim($m[1]);
            $volRaw  = $m[3];                            // e.g. "0,50"
            $volNorm = rtrim(rtrim($volRaw, '0'), ',') ?: '0'; // e.g. "0,5"
            $unit    = strtolower($m[4]);

            // Find WaWi articles whose name starts with the exact prefix
            // and do not themselves contain a NxV pattern (i.e. they are base items)
            $hits = DB::table('wawi_artikel')
                ->where('cAktiv', 'Y')
                ->where('cName', 'like', $prefix . ' %')
                ->whereRaw("cName NOT REGEXP '[0-9]+x'")
                ->select('kArtikel', 'cArtNr', 'cName', 'cBarcode')
                ->get();

            if ($hits->isEmpty()) {
                $stats['name_no_hit']++;
                continue;
            }

            // Disambiguate by volume when multiple hits
            $resolved = $hits;
            if ($hits->count() > 1) {
                $narrow = $hits->filter(fn ($h) =>
                    str_contains($h->cName, $volRaw . ' ' . $unit) ||
                    str_contains($h->cName, $volRaw . $unit) ||
                    str_contains($h->cName, $volNorm . ' ' . $unit) ||
                    str_contains($h->cName, $volNorm . $unit)
                );

                if ($narrow->count() !== 1) {
                    // Still ambiguous — log and skip
                    $names = $hits->pluck('cName')->implode(' | ');
                    $this->line("  <fg=yellow>AMBIGUOUS [{$row->id}]</> {$row->produktname} → [{$names}]");
                    $stats['name_ambiguous']++;
                    continue;
                }

                $resolved = $narrow;
            }

            $wawiBottle = $resolved->first();
            $bottleWawiId = (int) $wawiBottle->kArtikel;

            // Check if a product already exists for this WaWi article.
            // Guard: if the found product IS the case itself, the WaWi data is
            // inconsistent (case imported with bottle's wawi_artikel_id) — skip.
            $existingId = DB::table('products')
                ->where('wawi_artikel_id', $bottleWawiId)
                ->where('id', '!=', $row->id)   // never link a case to itself
                ->value('id');

            $baseId = $this->ensureBaseItem(
                $bottleWawiId,
                (string) $wawiBottle->cArtNr,
                (string) $wawiBottle->cName,
                $row->company_id,
                $existingId ? (int) $existingId : null,
                $defaultTaxRateId,
                $now,
                $dryRun,
                $bottleProductMap,
                $stats,
                'name',
            );

            $this->linkCase((int) $row->id, $baseId, $now, $dryRun, $stats);
        }

        // ── Summary ───────────────────────────────────────────────────────────

        $this->newLine();
        $this->table(
            ['Action', 'Count'],
            [
                ['Base items created via BOM',           $stats['base_created_bom']],
                ['Base items created via EAN-strip',     $stats['base_created_ean']],
                ['Base items created via name-parse',    $stats['base_created_name']],
                ['Base items reused (already existed)',  $stats['base_reused']],
                ['Cases linked to base item',            $stats['cases_linked']],
                ['Cases already correctly linked',       $stats['already_ok']],
                ['Name-parse: no WaWi hit',              $stats['name_no_hit']],
                ['Name-parse: ambiguous (skipped)',      $stats['name_ambiguous']],
            ]
        );

        return self::SUCCESS;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Find or create the base-item product for a given bottle WaWi article.
     * Returns the product ID (or -1 in dry-run when creating new).
     *
     * @param  array<int,int>    $bottleProductMap  reference — deduplication map
     * @param  array<string,int> $stats             reference — counters
     */
    private function ensureBaseItem(
        int     $bottleWawiId,
        string  $artnr,
        string  $name,
        mixed   $companyId,
        ?int    $existingProductId,
        int     $defaultTaxRateId,
        string  $now,
        bool    $dryRun,
        array   &$bottleProductMap,
        array   &$stats,
        string  $method,
    ): int {
        if (isset($bottleProductMap[$bottleWawiId])) {
            return $bottleProductMap[$bottleWawiId];
        }

        if ($existingProductId !== null) {
            $bottleProductMap[$bottleWawiId] = $existingProductId;
            $stats['base_reused']++;

            if (! $dryRun) {
                DB::table('products')
                    ->where('id', $existingProductId)
                    ->update(['is_base_item' => 1, 'updated_at' => $now]);
            }

            $this->line("  <fg=cyan>REUSE [{$method}]</> [{$existingProductId}] {$artnr} {$name}");
            return $existingProductId;
        }

        $slug = Str::slug($name . '-' . $bottleWawiId);

        if (! $dryRun) {
            // Guard against slug collisions
            $slugBase  = $slug;
            $collision = 1;
            while (DB::table('products')->where('slug', $slug)->exists()) {
                $slug = $slugBase . '-' . (++$collision);
            }

            // Guard against artikelnummer collisions (WaWi data quality: case
            // imported with bottle's cArtNr → append suffix to avoid constraint error)
            $artnrFinal = $artnr;
            if (DB::table('products')->where('artikelnummer', $artnrFinal)->exists()) {
                $artnrFinal = $artnr . '-b';
            }

            $newId = DB::table('products')->insertGetId([
                'company_id'             => $companyId,
                'tax_rate_id'            => $defaultTaxRateId,
                'wawi_artikel_id'        => $bottleWawiId,
                'artikelnummer'          => $artnrFinal,
                'slug'                   => $slug,
                'produktname'            => $name,
                'is_base_item'           => 1,
                'active'                 => 0,
                'show_in_shop'           => 0,
                'is_bundle'              => 0,
                'availability_mode'      => 'available',
                'base_price_net_milli'   => 0,
                'base_price_gross_milli' => 0,
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);

            $bottleProductMap[$bottleWawiId] = $newId;
            $this->line("  <fg=green>CREATE [{$method}]</> [#{$newId}] {$artnr} {$name}");
        } else {
            $bottleProductMap[$bottleWawiId] = -1;
            $this->line("  <fg=green>CREATE [{$method}]</> (dry) {$artnr} {$name}");
        }

        $stats['base_created_' . $method]++;
        return $bottleProductMap[$bottleWawiId];
    }

    /**
     * Set base_item_product_id on a case product if not already correct.
     *
     * @param  array<string,int> $stats  reference — counters
     */
    private function linkCase(
        int    $caseProductId,
        int    $baseId,
        string $now,
        bool   $dryRun,
        array  &$stats,
    ): void {
        if ($baseId <= 0) {
            return; // dry-run placeholder
        }

        $current = DB::table('products')
            ->where('id', $caseProductId)
            ->value('base_item_product_id');

        if ((int) $current === $baseId) {
            $stats['already_ok']++;
            return;
        }

        $this->line("  <fg=yellow>LINK</>  case [{$caseProductId}] → base [{$baseId}]");

        if (! $dryRun) {
            DB::table('products')
                ->where('id', $caseProductId)
                ->update([
                    'base_item_product_id' => $baseId,
                    'is_base_item'         => 0,
                    'updated_at'           => $now,
                ]);
        }

        $stats['cases_linked']++;
    }
}
