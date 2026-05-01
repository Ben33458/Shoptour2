<?php

declare(strict_types=1);

namespace App\Services\Reconcile;

use App\Models\Catalog\Category;
use App\Models\Catalog\Gebinde;
use App\Models\Catalog\PfandItem;
use App\Models\Catalog\PfandSet;
use App\Models\Catalog\PfandSetComponent;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductLmivVersion;
use App\Models\ReconcileFeedbackLog;
use App\Models\SourceMatch;
use App\Services\Integrations\GetraenkeDbClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GetraenkeDbMatchService
{
    private const SOURCE = 'getraenkedb';
    private const ENTITY = 'product';

    public function __construct(private readonly GetraenkeDbClient $client) {}

    /**
     * Propose matches between local products and getraenkeDB product families.
     *
     * @return list<array{product: Product, suggestion: array, confidence: int, method: string, existing_match: SourceMatch|null}>
     */
    public function proposeMatches(): array
    {
        $existingMatches = SourceMatch::where('source', self::SOURCE)
            ->where('entity_type', self::ENTITY)
            ->get()
            ->keyBy('local_id');

        $products = Product::with('barcodes')->get();

        $proposals = [];

        foreach ($products as $product) {
            $existing = $existingMatches->get($product->id);

            // Already confirmed or ignored: use stored snapshot, skip API calls
            if ($existing !== null && in_array($existing->status, ['confirmed', 'ignored'], true)) {
                $proposals[] = [
                    'product'        => $product,
                    'suggestion'     => $existing->source_snapshot ?? [],
                    'confidence'     => 0,
                    'method'         => 'stored',
                    'existing_match' => $existing,
                ];
                continue;
            }

            // EAN-Lookup (confidence 100)
            $apiResult  = null;
            $confidence = 0;
            $method     = '';

            foreach ($product->barcodes as $barcode) {
                $result = $this->client->getProductByGtin($barcode->barcode);
                if (! empty($result)) {
                    $apiResult  = $result;
                    $confidence = 100;
                    $method     = 'ean';
                    break;
                }
            }

            // Name-Fuzzy fallback
            if ($apiResult === null) {
                // Progressive fallback: if full query yields 0 results, try shorter queries
                // e.g. "Bernadett Brunnen Medium" → "Bernadett Brunnen" → "Bernadett"
                $baseQuery  = $this->stripPackagingInfo($product->produktname);
                $queryWords = explode(' ', $baseQuery);
                $results    = [];
                while (count($queryWords) >= 1 && empty($results)) {
                    $results = $this->client->searchProducts(implode(' ', $queryWords));
                    array_pop($queryWords);
                }

                $best      = null;
                $bestScore = 0;

                foreach ($results as $result) {
                    $remoteName = $result['name'] ?? '';
                    similar_text(
                        mb_strtolower($product->produktname),
                        mb_strtolower($remoteName),
                        $percent
                    );
                    if ($percent > $bestScore) {
                        $bestScore = (int) round($percent);
                        $best      = $result;
                    }
                }

                if ($best !== null && $bestScore >= 55) {
                    $apiResult  = $best;
                    $confidence = $bestScore;
                    $method     = 'name_fuzzy';
                }
            }

            if ($apiResult !== null) {
                $proposals[] = [
                    'product'        => $product,
                    'suggestion'     => $apiResult,
                    'confidence'     => $confidence,
                    'method'         => $method,
                    'existing_match' => $existing,
                ];
            } elseif ($existing !== null) {
                // auto-match stored but API returned nothing this time
                $proposals[] = [
                    'product'        => $product,
                    'suggestion'     => $existing->source_snapshot ?? [],
                    'confidence'     => 0,
                    'method'         => 'stored',
                    'existing_match' => $existing,
                ];
            }
        }

        return $proposals;
    }

    /**
     * Confirm a match between a local product and a getraenkeDB slug.
     */
    public function confirm(int $productId, string $slug, int $userId): void
    {
        $snapshot = $this->client->getProduct($slug);

        // Unique constraint: (entity_type, source, source_id) — one slug per source
        // Additionally ensure one match per local product for this source
        SourceMatch::where('entity_type', self::ENTITY)
            ->where('source', self::SOURCE)
            ->where('local_id', $productId)
            ->delete();

        SourceMatch::updateOrCreate(
            [
                'entity_type' => self::ENTITY,
                'source'      => self::SOURCE,
                'source_id'   => $slug,
            ],
            [
                'local_id'        => $productId,
                'status'          => SourceMatch::STATUS_CONFIRMED,
                'matched_by'      => $userId,
                'confirmed_at'    => now(),
                'source_snapshot' => $snapshot ?: null,
            ]
        );

        $product = Product::find($productId);
        ReconcileFeedbackLog::create([
            'entity_type'    => self::ENTITY,
            'source'         => self::SOURCE,
            'source_id'      => $slug,
            'action'         => 'confirmed',
            'user_id'        => $userId,
            'target_id'      => (string) $productId,
            'target_name'    => $product?->name,
            'source_name'    => $snapshot['name'] ?? $slug,
            'confidence'     => 100,
            'match_method'   => 'manual',
            'was_auto_match' => false,
        ]);
    }

    /**
     * GetraenkeDB-Match für ein Produkt ablehnen.
     */
    public function ignore(int $productId, int $userId): void
    {
        $prior = SourceMatch::where('entity_type', self::ENTITY)
            ->where('local_id', $productId)
            ->where('source', self::SOURCE)
            ->first();

        SourceMatch::updateOrCreate(
            [
                'entity_type' => self::ENTITY,
                'local_id'    => $productId,
                'source'      => self::SOURCE,
            ],
            [
                'source_id'  => null,
                'status'     => SourceMatch::STATUS_IGNORED,
                'matched_by' => $userId,
            ]
        );

        $product = Product::find($productId);
        ReconcileFeedbackLog::create([
            'entity_type'    => self::ENTITY,
            'source'         => self::SOURCE,
            'source_id'      => $prior?->source_id,
            'action'         => 'ignored',
            'user_id'        => $userId,
            'target_id'      => (string) $productId,
            'target_name'    => $product?->name,
            'confidence'     => 0,
            'match_method'   => 'manual',
            'was_auto_match' => $prior?->status === SourceMatch::STATUS_AUTO,
        ]);
    }

    /**
     * Sync all confirmed matches: download images, set Pfand data, import LMIV.
     *
     * @return array{synced: int, images_downloaded: int, pfand_created: int, lmiv_synced: int, errors: int}
     */
    public function syncConfirmed(int $userId): array
    {
        $stats = ['synced' => 0, 'images_downloaded' => 0, 'pfand_created' => 0, 'lmiv_synced' => 0, 'errors' => 0];

        $matches = SourceMatch::where('source', self::SOURCE)
            ->where('entity_type', self::ENTITY)
            ->where('status', SourceMatch::STATUS_CONFIRMED)
            ->whereNotNull('source_id')
            ->get();

        foreach ($matches as $match) {
            try {
                $product = Product::with(['gebinde.pfandSet', 'images', 'activeLmivVersion'])->find($match->local_id);
                if (! $product) {
                    continue;
                }

                $apiData = $this->client->getProduct($match->source_id);
                if (empty($apiData)) {
                    $stats['errors']++;
                    continue;
                }

                $match->update(['source_snapshot' => $apiData]);
                $tradeItems = $apiData['trade_items'] ?? [];

                // ── Bild-Sync ─────────────────────────────────────────────
                if ($product->images->isEmpty()) {
                    foreach ($tradeItems as $ti) {
                        $imageUrl = $ti['image_url'] ?? $ti['media_url'] ?? null;
                        if (! $imageUrl) {
                            continue;
                        }

                        $filename = Str::uuid() . '.jpg';
                        $destPath = "products/{$product->id}/{$filename}";

                        if ($this->client->downloadImage($imageUrl, $destPath)) {
                            ProductImage::create([
                                'product_id' => $product->id,
                                'path'       => $destPath,
                                'sort_order' => 0,
                                'alt_text'   => $product->produktname,
                            ]);
                            $stats['images_downloaded']++;
                            break;
                        }
                    }
                }

                // ── Pfand-Sync ────────────────────────────────────────────
                // Only if product has a Gebinde with no PfandSet yet
                $gebinde = $product->gebinde;
                if ($gebinde && ! $gebinde->pfand_set_id) {
                    foreach ($tradeItems as $ti) {
                        if (empty($ti['deposit_applicable'])) {
                            continue;
                        }
                        $depositCents = (int) ($ti['deposit_amount_cents'] ?? 0);
                        if ($depositCents <= 0) {
                            continue;
                        }

                        $pfandTyp       = (bool) ($ti['is_oneway'] ?? false) ? PfandItem::TYP_EINWEG : PfandItem::TYP_MEHRWEG;
                        $wertMilliCents = $depositCents * 10_000;

                        DB::transaction(function () use ($gebinde, $pfandTyp, $wertMilliCents, $depositCents, &$stats) {
                            $pfandItem = PfandItem::create([
                                'pfand_typ'                          => $pfandTyp,
                                'bezeichnung'                        => "{$pfandTyp}-Pfand {$depositCents} ct (getraenkeDB)",
                                'wert_netto_milli'                   => $wertMilliCents,
                                'wert_brutto_milli'                  => $wertMilliCents,
                                'wiederverkaeufer_wert_netto_milli'  => $wertMilliCents,
                                'wiederverkaeufer_wert_brutto_milli' => $wertMilliCents,
                                'active'                             => true,
                            ]);
                            $pfandSet = PfandSet::create([
                                'name'   => "{$pfandTyp}-Set {$depositCents} ct",
                                'active' => true,
                            ]);
                            PfandSetComponent::create([
                                'pfand_set_id'  => $pfandSet->id,
                                'pfand_item_id' => $pfandItem->id,
                                'qty'           => 1,
                            ]);
                            $gebinde->update(['pfand_set_id' => $pfandSet->id]);
                            $stats['pfand_created']++;
                        });

                        break;
                    }
                }

                // ── LMIV-Sync ─────────────────────────────────────────────
                // Only for base items, only if no active LMIV version exists yet
                $nutrition   = $apiData['nutrition'] ?? null;
                $ingredients = $apiData['ingredients'] ?? null;

                if (($nutrition || $ingredients) && ! $product->activeLmivVersion) {
                    $nextVersion = ($product->lmivVersions()->max('version_number') ?? 0) + 1;

                    $dataJson = [];
                    if ($nutrition) {
                        $dataJson['nutrition'] = array_filter([
                            'per_reference'        => $nutrition['per_reference'] ?? '100ml',
                            'energy_kcal'          => $nutrition['energy_kcal'] ?? null,
                            'energy_kj'            => $nutrition['energy_kj'] ?? null,
                            'fat_g'                => $nutrition['fat_g'] ?? null,
                            'saturated_fat_g'      => $nutrition['saturated_fat_g'] ?? null,
                            'carbohydrates_g'      => $nutrition['carbohydrates_g'] ?? null,
                            'sugars_g'             => $nutrition['sugars_g'] ?? null,
                            'fiber_g'              => $nutrition['fiber_g'] ?? null,
                            'protein_g'            => $nutrition['protein_g'] ?? null,
                            'salt_g'               => $nutrition['salt_g'] ?? null,
                            'alcohol_vol'          => $nutrition['alcohol_vol'] ?? null,
                            'caffeine_mg_per_100ml'=> $nutrition['caffeine_mg_per_100ml'] ?? null,
                        ], fn($v) => $v !== null);
                    }
                    if ($ingredients) {
                        $dataJson['ingredients'] = $ingredients;
                    }
                    $dataJson['source'] = 'getraenkedb';

                    ProductLmivVersion::create([
                        'product_id'         => $product->id,
                        'version_number'     => $nextVersion,
                        'status'             => ProductLmivVersion::STATUS_ACTIVE,
                        'data_json'          => $dataJson,
                        'change_reason'      => 'Import von getraenkeDB',
                        'effective_from'     => now(),
                        'created_by_user_id' => $userId,
                    ]);
                    $stats['lmiv_synced']++;
                }

                $stats['synced']++;
            } catch (\Throwable $e) {
                Log::error('GetraenkeDbMatchService::syncConfirmed error', [
                    'match_id' => $match->id,
                    'error'    => $e->getMessage(),
                ]);
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * Sync categories from getraenkeDB into shoptour2 and assign to matched products.
     *
     * @return array{categories_created: int, products_assigned: int, errors: int}
     */
    public function syncCategories(): array
    {
        $stats = ['categories_created' => 0, 'products_assigned' => 0, 'errors' => 0];

        $matches = SourceMatch::where('source', self::SOURCE)
            ->where('entity_type', self::ENTITY)
            ->where('status', SourceMatch::STATUS_CONFIRMED)
            ->whereNotNull('source_id')
            ->get();

        foreach ($matches as $match) {
            try {
                $product = Product::find($match->local_id);
                if (! $product || $product->category_id) {
                    continue; // skip already-categorised products
                }

                $apiData = $this->client->getProduct($match->source_id);
                if (empty($apiData)) {
                    $stats['errors']++;
                    continue;
                }

                $remoteCategories = $apiData['categories'] ?? [];
                if (empty($remoteCategories)) {
                    continue;
                }

                // Use the first (most specific) category from getraenkeDB
                $remoteCat = $remoteCategories[0];
                $catName   = $remoteCat['name'] ?? null;
                if (! $catName) {
                    continue;
                }

                // Find or create parent if present
                $parentId = null;
                if (! empty($remoteCat['parent'])) {
                    $parent   = Category::firstOrCreate(['name' => $remoteCat['parent']['name']], ['parent_id' => null]);
                    $parentId = $parent->id;
                    if ($parent->wasRecentlyCreated) {
                        $stats['categories_created']++;
                    }
                }

                $category = Category::firstOrCreate(
                    ['name' => $catName, 'parent_id' => $parentId],
                );
                if ($category->wasRecentlyCreated) {
                    $stats['categories_created']++;
                }

                $product->update(['category_id' => $category->id]);
                $stats['products_assigned']++;
            } catch (\Throwable $e) {
                Log::error('GetraenkeDbMatchService::syncCategories error', [
                    'match_id' => $match->id,
                    'error'    => $e->getMessage(),
                ]);
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * Strip packaging/quantity info from a product name before API search.
     * "Bitburger Premium Pils 24x0,33 l" → "Bitburger Premium Pils"
     * "Coca-Cola 12x1,0 l PET"           → "Coca-Cola"
     */
    private function stripPackagingInfo(string $name): string
    {
        // Remove patterns like: 24x0,33l | 12x1,0 l | 6x0.5l | 1x5l
        $clean = preg_replace('/\s*\d+\s*x\s*[\d,\.]+\s*l\b.*/i', '', $name);
        // Remove standalone volume: 0,33l | 0.5 l | 1,0l
        $clean = preg_replace('/\s*[\d,\.]+\s*l\b.*/i', $clean, $clean);
        // Remove "PET", "Glas", "Dose", "Kasten" suffixes
        $clean = preg_replace('/\s+(PET|Glas|Dose|Kasten|Flasche|Keg|Bügel)\b.*/i', '', $clean);

        return trim($clean) ?: $name;
    }
}
