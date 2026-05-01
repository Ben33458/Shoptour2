<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductLmivVersion;
use App\Services\Catalog\LmivVersioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetch LMIV data from Open Food Facts (OFF) API for products with EANs.
 *
 * EAN resolution:
 *   1. product_barcodes.barcode — if 14-digit and starts with "1" → strip first digit
 *   2. product_barcodes.barcode — if 13-digit and != "0" → use as-is
 *   3. wawi_dbo_tartikel.cBarcode — fallback
 *
 * Usage:
 *   php artisan lmiv:fetch-from-off             # all products with EANs, no limit
 *   php artisan lmiv:fetch-from-off --limit=5   # first 5 (for testing)
 *   php artisan lmiv:fetch-from-off --dry-run   # resolve EANs + fetch, do not save
 */
class LmivFetchFromOffCommand extends Command
{
    protected $signature = 'lmiv:fetch-from-off
                            {--limit=0 : Max products to process (0 = all)}
                            {--offset=0 : Skip the first N products (resume support)}
                            {--ids= : Comma-separated product IDs to process}
                            {--dry-run : Fetch data but do not write to DB}';

    protected $description = 'Fetch LMIV nutrition/ingredient data from Open Food Facts API';

    private const OFF_API_URL = 'https://world.openfoodfacts.org/api/v2/product/{ean}.json';

    private const OFF_FIELDS = [
        'product_name',
        'brands',
        'quantity',
        // Standard nutriments (must be requested as sub-fields for _100g values)
        'nutriments.energy-kcal_100g',
        'nutriments.energy-kj_100g',
        'nutriments.fat_100g',
        'nutriments.saturated-fat_100g',
        'nutriments.carbohydrates_100g',
        'nutriments.sugars_100g',
        'nutriments.fiber_100g',
        'nutriments.proteins_100g',
        'nutriments.salt_100g',
        'nutriments.sodium_100g',
        'nutriments.alcohol_100g',
        // Mineral water specific (Mineralien)
        'nutriments.calcium_100g',
        'nutriments.magnesium_100g',
        'nutriments.bicarbonate_100g',
        'nutriments.potassium_100g',
        'nutriments.chloride_100g',
        'nutriments.sulphate_100g',
        'nutriments.fluoride_100g',
        'nutriments.silica_100g',
        // Other
        'ingredients_text_de',
        'ingredients_text',
        'allergens_tags',
        'traces_tags',
        'countries_tags',
        'alcohol_100g',
        'serving_size',
        'origins',
        'manufacturing_places',
    ];

    public function __construct(
        private readonly LmivVersioningService $versioning,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit  = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY-RUN mode — no data will be written.');
        }

        // ── Resolve EANs for base-item products ──────────────────────────────
        // Prefer is_base_item=true products (individual bottles created by wawi:sync-base-items).
        // Their EAN comes from wawi_dbo_tartikel.cBarcode (the real bottle barcode).
        // For products without a base item (no BOM in WaWi), fall back to product_barcodes
        // or wawi barcode (applying the "strip leading 1" heuristic for 14-digit Kisten-EANs).

        $query = DB::table('products as p')
            ->leftJoin('product_barcodes as pb', 'pb.product_id', '=', 'p.id')
            ->leftJoin('wawi_dbo_tartikel as wa', 'wa.kArtikel', '=', 'p.wawi_artikel_id')
            ->where('p.is_base_item', true)
            ->select('p.id', 'p.produktname', 'p.artikelnummer', 'pb.barcode', 'wa.cBarcode as wawi_barcode')
            ->orderBy('p.id');

        // Filter by explicit IDs if provided
        $idsOption = $this->option('ids');
        if ($idsOption) {
            $ids = array_filter(array_map('intval', explode(',', $idsOption)));
            $query->whereIn('p.id', $ids);
        }

        $offset = (int) $this->option('offset');
        if ($offset > 0) {
            $query->offset($offset);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->get();

        $this->info("Found {$rows->count()} base-item products to process.");

        $stats = ['fetched' => 0, 'saved' => 0, 'no_ean' => 0, 'no_data' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($rows as $row) {
            $ean = $this->resolveEan((string) ($row->barcode ?? ''), (string) ($row->wawi_barcode ?? ''));

            if ($ean === null) {
                $this->line("  <fg=gray>SKIP (no EAN)</> [{$row->id}] {$row->produktname}");
                $stats['no_ean']++;
                continue;
            }

            // Skip if already has a draft LMIV version with OFF data
            $existing = ProductLmivVersion::where('product_id', $row->id)
                ->whereIn('status', [ProductLmivVersion::STATUS_DRAFT, ProductLmivVersion::STATUS_ACTIVE])
                ->first();

            if ($existing && isset($existing->data_json['source']) && $existing->data_json['source'] === 'open_food_facts') {
                $this->line("  <fg=gray>SKIP (already has OFF data)</> [{$row->id}] {$row->produktname}");
                $stats['skipped']++;
                continue;
            }

            // ── Fetch from OFF API ────────────────────────────────────────────

            try {
                $data = $this->fetchFromOff($ean);
            } catch (\Throwable $e) {
                $this->error("  ERROR fetching [{$row->id}] {$row->produktname}: {$e->getMessage()}");
                Log::warning('lmiv:fetch-from-off error', ['product_id' => $row->id, 'ean' => $ean, 'error' => $e->getMessage()]);
                $stats['errors']++;
                continue;
            }

            $stats['fetched']++;

            if ($data === null) {
                $this->line("  <fg=yellow>NOT FOUND on OFF</> [{$row->id}] {$row->produktname} (EAN: {$ean})");
                $stats['no_data']++;
                continue;
            }

            // ── Map to data_json ──────────────────────────────────────────────

            $dataJson = $this->mapToDataJson($data, $ean);

            // Show what we found
            $nutrition = $dataJson['nutrition'] ?? [];
            $hasNutrition = isset($nutrition['energy_kcal']) || isset($nutrition['energy_kj']);
            $hasIngredients = !empty($dataJson['zutaten']);
            $hasAllergens = !empty($dataJson['allergene']);

            $flags = implode(' ', array_filter([
                $hasNutrition ? '<fg=green>NW</>' : '<fg=red>no-NW</>',
                $hasIngredients ? '<fg=green>Zutaten</>' : '<fg=yellow>no-Zutaten</>',
                $hasAllergens ? '<fg=green>Allergene</>' : '',
            ]));

            $this->line("  <fg=cyan>FETCH</> [{$row->id}] {$row->produktname} (EAN: {$ean}) {$flags}");

            if ($dryRun) {
                $this->line('    data_json: ' . json_encode($dataJson, JSON_UNESCAPED_UNICODE));
                continue;
            }

            // ── Write to DB ───────────────────────────────────────────────────

            try {
                if ($existing !== null && $existing->ean === $ean) {
                    // Same EAN → update data_json in place
                    $existing->data_json = $dataJson;
                    $existing->save();
                } else {
                    // New EAN or first version — create as DRAFT (OFF data unverified, needs manual review)
                    // (bypass LmivVersioningService is_base_item check)
                    ProductLmivVersion::updateOrCreate(
                        ['product_id' => $row->id, 'status' => ProductLmivVersion::STATUS_DRAFT],
                        [
                            'ean'            => $ean,
                            'data_json'      => $dataJson,
                            'change_reason'  => 'OFF-Import (Entwurf — bitte prüfen und aktivieren)',
                            'effective_from' => now(),
                            'effective_to'   => null,
                        ]
                    );
                }

                $stats['saved']++;
            } catch (\Throwable $e) {
                $this->error("  ERROR saving [{$row->id}]: {$e->getMessage()}");
                Log::error('lmiv:fetch-from-off save error', ['product_id' => $row->id, 'error' => $e->getMessage()]);
                $stats['errors']++;
            }

            // Be polite to OFF API — stay well under their ~100 req/min limit
            usleep(700_000); // 700ms between requests (~85 req/min)
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Fetched from OFF',  $stats['fetched']],
                ['Saved to DB',       $stats['saved']],
                ['No EAN',            $stats['no_ean']],
                ['Not on OFF',        $stats['no_data']],
                ['Already has data',  $stats['skipped']],
                ['Errors',            $stats['errors']],
            ]
        );

        return self::SUCCESS;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Resolve the bottle EAN from product_barcodes or wawi fallback.
     * Returns null when no usable EAN is found.
     */
    private function resolveEan(string $barcode, string $wawiBarcode): ?string
    {
        // product_barcodes takes precedence
        if ($barcode !== '' && $barcode !== '0') {
            if (strlen($barcode) === 14 && $barcode[0] === '1') {
                return substr($barcode, 1); // strip Kisten-EAN prefix
            }
            if (strlen($barcode) >= 8) {
                return $barcode;
            }
        }

        // WaWi fallback
        if ($wawiBarcode !== '' && $wawiBarcode !== '0') {
            if (strlen($wawiBarcode) === 14 && $wawiBarcode[0] === '1') {
                return substr($wawiBarcode, 1);
            }
            if (strlen($wawiBarcode) >= 8) {
                return $wawiBarcode;
            }
        }

        return null;
    }

    /**
     * Fetch product data from Open Food Facts API.
     * Returns null if product not found (status=0).
     * Retries up to 3 times on HTTP 429 with exponential backoff.
     *
     * @return array<string,mixed>|null
     */
    private function fetchFromOff(string $ean): ?array
    {
        $fields = implode(',', self::OFF_FIELDS);
        $url    = "https://world.openfoodfacts.org/api/v2/product/{$ean}.json?fields={$fields}";

        $maxRetries = 3;
        $attempt    = 0;

        while ($attempt <= $maxRetries) {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Kolabri-Getraenkeshop/1.0 (admin@kolabri.de)'])
                ->get($url);

            if ($response->status() === 429) {
                $waitSecs = (int) ($response->header('Retry-After') ?: (10 * (2 ** $attempt)));
                $this->line("    <fg=yellow>429 rate-limit — waiting {$waitSecs}s...</>");
                sleep($waitSecs);
                $attempt++;
                continue;
            }

            if ($response->status() === 404) {
                return null; // Product not in OFF database
            }

            if (! $response->successful()) {
                throw new \RuntimeException("OFF API returned HTTP {$response->status()} for EAN {$ean}");
            }

            $body = $response->json();

            if (($body['status'] ?? 0) === 0) {
                return null; // Not found in OFF
            }

            return $body['product'] ?? null;
        }

        throw new \RuntimeException("OFF API rate-limit exceeded after {$maxRetries} retries for EAN {$ean}");
    }

    /**
     * Map Open Food Facts product data to our data_json schema.
     *
     * @param  array<string,mixed> $off   Raw OFF product object
     * @return array<string,mixed>
     */
    private function mapToDataJson(array $off, string $ean): array
    {
        $n = $off['nutriments'] ?? [];

        // Allergen tags: ['en:gluten', 'en:milk'] → ['Gluten', 'Milch'] etc.
        $allergenTags  = $off['allergens_tags'] ?? [];
        $tracesTags    = $off['traces_tags'] ?? [];

        // Ingredients: prefer German, fall back to generic
        $ingredients = $off['ingredients_text_de']
            ?? $off['ingredients_text']
            ?? null;

        // Brand / manufacturer
        $brands = $off['brands'] ?? null;

        // Country of origin
        $origins = $off['origins'] ?? null;
        $countries = $off['countries_tags'] ?? [];
        $herkunft = $origins ?: $this->parseCountryTags($countries);

        // Build nutrition block (all per 100ml/100g)
        $nutrition = array_filter([
            'per_reference'      => '100ml',
            'energy_kj'          => $this->numOrNull($n['energy_100g'] ?? $n['energy-kj_100g'] ?? null),
            'energy_kcal'        => $this->numOrNull($n['energy-kcal_100g'] ?? null),
            'fat'                => $this->numOrNull($n['fat_100g'] ?? null),
            'fat_saturated'      => $this->numOrNull($n['saturated-fat_100g'] ?? null),
            'carbohydrates'      => $this->numOrNull($n['carbohydrates_100g'] ?? null),
            'sugar'              => $this->numOrNull($n['sugars_100g'] ?? null),
            'fiber'              => $this->numOrNull($n['fiber_100g'] ?? null),
            'protein'            => $this->numOrNull($n['proteins_100g'] ?? null),
            'salt'               => $this->numOrNull($n['salt_100g'] ?? null),
            'sodium'             => $this->numOrNull($n['sodium_100g'] ?? null),
            'alcohol'            => $this->numOrNull($n['alcohol_100g'] ?? $off['alcohol_100g'] ?? null),
        ], fn ($v) => $v !== null);

        return array_filter([
            'source'          => 'open_food_facts',
            'ean'             => $ean,
            'hersteller'      => $brands,
            'herkunftsland'   => $herkunft,
            'nettofuellmenge' => $off['quantity'] ?? null,
            'alkoholgehalt'   => $this->numOrNull($n['alcohol_100g'] ?? $off['alcohol_100g'] ?? null),
            'zutaten'         => $ingredients ?: null,
            'allergene'       => $allergenTags ? $this->formatTags($allergenTags) : null,
            'spuren'          => $tracesTags ? $this->formatTags($tracesTags) : null,
            'lagerhinweis'    => null,
            'nutrition'       => $nutrition ?: null,
            // CSV importer keys for cross-compatibility
            'nw_energie_kj'      => $this->numOrNull($n['energy_100g'] ?? $n['energy-kj_100g'] ?? null),
            'nw_energie_kcal'    => $this->numOrNull($n['energy-kcal_100g'] ?? null),
            'nw_fett'            => $this->numOrNull($n['fat_100g'] ?? null),
            'nw_fett_gesaettigt' => $this->numOrNull($n['saturated-fat_100g'] ?? null),
            'nw_kohlenhydrate'   => $this->numOrNull($n['carbohydrates_100g'] ?? null),
            'nw_zucker'          => $this->numOrNull($n['sugars_100g'] ?? null),
            'nw_ballaststoffe'   => $this->numOrNull($n['fiber_100g'] ?? null),
            'nw_eiweiss'         => $this->numOrNull($n['proteins_100g'] ?? null),
            'nw_salz'            => $this->numOrNull($n['salt_100g'] ?? null),
            // Mineral water fields
            'nw_natrium'         => $this->numOrNull($n['sodium_100g'] ?? null),
            'nw_calcium'         => $this->numOrNull($n['calcium_100g'] ?? null),
            'nw_magnesium'       => $this->numOrNull($n['magnesium_100g'] ?? null),
            'nw_hydrogencarbonat'=> $this->numOrNull($n['bicarbonate_100g'] ?? null),
            'nw_kalium'          => $this->numOrNull($n['potassium_100g'] ?? null),
            'nw_chlorid'         => $this->numOrNull($n['chloride_100g'] ?? null),
            'nw_sulfat'          => $this->numOrNull($n['sulphate_100g'] ?? null),
            'nw_fluorid'         => $this->numOrNull($n['fluoride_100g'] ?? null),
            'nw_kieselsaeure'    => $this->numOrNull($n['silica_100g'] ?? null),
        ], fn ($v) => $v !== null);
    }

    /**
     * @param  array<string> $tags  e.g. ['en:gluten','en:milk']
     */
    private function formatTags(array $tags): string
    {
        return implode(', ', array_map(
            fn (string $t): string => ucfirst(str_replace('-', ' ', preg_replace('/^[a-z]+:/', '', $t) ?? $t)),
            $tags,
        ));
    }

    /**
     * @param  array<string> $tags  e.g. ['en:germany','en:france']
     */
    private function parseCountryTags(array $tags): ?string
    {
        if (empty($tags)) {
            return null;
        }

        return implode(', ', array_map(
            fn (string $t): string => ucfirst(str_replace('-', ' ', preg_replace('/^[a-z]+:/', '', $t) ?? $t)),
            $tags,
        ));
    }

    private function numOrNull(mixed $val): float|int|null
    {
        if ($val === null || $val === '' || $val === false) {
            return null;
        }
        $f = (float) $val;
        return $f == (int) $f ? (int) $f : $f;
    }
}
