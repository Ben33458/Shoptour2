<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductBarcode;
use App\Models\Catalog\Warengruppe;
use App\Models\ReconcileProductRule;
use App\Models\SourceMatch;
use App\Services\Reconcile\ProductReconcileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReconcileProductController extends Controller
{
    public function __construct(
        private readonly ProductReconcileService $service,
    ) {}

    public function index(Request $request): View
    {
        $filter = $request->input('filter', 'unmatched');
        $search = trim((string) $request->input('search', ''));
        $sort   = $request->input('sort', 'confidence');
        $dir    = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $stats = $this->service->stats();

        $filters = match ($filter) {
            'unmatched' => ['unmatched_only' => true],
            'auto'      => ['status' => 'auto'],
            'confirmed' => ['status' => 'confirmed'],
            'ignored'   => ['status' => 'ignored'],
            default     => [],
        };

        if ($search !== '') {
            $filters['search'] = $search;
        }

        if ($filter === 'all') {
            $proposals = array_slice($this->service->proposeMatches($filters), 0, 200);
            $truncated = true;
        } else {
            $proposals = $this->service->proposeMatches($filters);
            $truncated = false;
        }

        usort($proposals, function (array $a, array $b) use ($sort, $dir): int {
            $valA = match ($sort) {
                'name'      => mb_strtolower((string) ($a['source_data']['artikelname'] ?? '')),
                'artnummer' => mb_strtolower((string) ($a['source_data']['artnummer'] ?? '')),
                default     => $a['confidence'],
            };
            $valB = match ($sort) {
                'name'      => mb_strtolower((string) ($b['source_data']['artikelname'] ?? '')),
                'artnummer' => mb_strtolower((string) ($b['source_data']['artnummer'] ?? '')),
                default     => $b['confidence'],
            };
            if ($valA === $valB) {
                return 0;
            }
            $cmp = $valA <=> $valB;
            return $dir === 'asc' ? $cmp : -$cmp;
        });

        return view('admin.reconcile.products', compact('proposals', 'stats', 'filter', 'truncated', 'search', 'sort', 'dir'));
    }

    public function confirmAll100(Request $request): RedirectResponse
    {
        $request->validate(['min_confidence' => 'nullable|integer|min:50|max:100']);

        $minConfidence = (int) $request->input('min_confidence', 96);
        $count = $this->service->confirmAllAbove($request->user()->id, $minConfidence);

        return back()->with('success', "{$count} Produkt-Matches mit ≥ {$minConfidence} % Konfidenz bestätigt.");
    }

    public function autoMatch(Request $request): RedirectResponse
    {
        $request->validate([
            'min_confidence' => 'nullable|integer|min:50|max:100',
        ]);

        $result = $this->service->autoMatchAll(
            (int) $request->input('min_confidence', 90),
        );

        return back()->with('success', sprintf(
            'Auto-Abgleich: %d verknüpft, %d zu unsicher (< %d %%), %d bereits erledigt.',
            $result['auto_matched'],
            $result['skipped'],
            $request->input('min_confidence', 90),
            $result['already_done'],
        ));
    }

    public function confirm(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'ninox_id' => 'required|string',
            'wawi_id'  => 'nullable|string',
        ]);

        $this->service->confirm(
            $request->ninox_id,
            $request->wawi_id ?: null,
            $request->user()->id,
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Produkt-Match bestätigt.');
    }

    public function ignore(Request $request): RedirectResponse
    {
        $request->validate([
            'ninox_id' => 'required|string',
        ]);

        $this->service->ignore($request->ninox_id, $request->user()->id);

        return back()->with('success', 'Produkt abgelehnt.');
    }

    public function bulkConfirm(Request $request): RedirectResponse
    {
        $request->validate([
            'ninox_ids'   => 'required|array',
            'ninox_ids.*' => 'string',
        ]);

        $confirmed = 0;
        foreach ($request->ninox_ids as $ninoxId) {
            $existing = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
                ->where('source', SourceMatch::SOURCE_NINOX)
                ->where('source_id', $ninoxId)
                ->first();

            $wawiId = isset($existing?->source_snapshot['_wawi_id'])
                ? (string) $existing->source_snapshot['_wawi_id']
                : null;

            $this->service->confirm($ninoxId, $wawiId, $request->user()->id, 'bulk_confirmed');
            $confirmed++;
        }

        return back()->with('success', "{$confirmed} Produkte bestätigt.");
    }

    public function bulkIgnore(Request $request): RedirectResponse
    {
        $request->validate([
            'ninox_ids'   => 'required|array',
            'ninox_ids.*' => 'string',
        ]);

        $ignored = 0;
        foreach ($request->ninox_ids as $ninoxId) {
            $this->service->ignore($ninoxId, $request->user()->id, 'bulk_ignored');
            $ignored++;
        }

        return back()->with('success', "{$ignored} Produkte abgelehnt.");
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate(['ninox_id' => 'required|string']);

        SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
            ->where('source', SourceMatch::SOURCE_NINOX)
            ->where('source_id', $request->ninox_id)
            ->delete();

        return back()->with('success', 'Verknüpfung zurückgesetzt.');
    }

    public function suggestRules(Request $request): JsonResponse
    {
        $minFrequency = max(2, (int) $request->input('min_frequency', 2));
        $result = $this->service->suggestRules($minFrequency);

        // Mark which suggestions are already stored as active rules
        $existing = ReconcileProductRule::where('active', true)
            ->pluck('source_token')
            ->flip()
            ->all();

        foreach ($result['synonyms'] as &$s) {
            $s['already_saved'] = isset($existing[$s['ninox_token']]);
        }
        foreach ($result['noise_ninox'] as &$n) {
            $n['already_saved'] = isset($existing[$n['token']]);
        }
        foreach ($result['noise_wawi'] as &$n) {
            $n['already_saved'] = isset($existing[$n['token']]);
        }
        unset($s, $n);

        return response()->json($result);
    }

    public function listRules(): JsonResponse
    {
        $rules = ReconcileProductRule::orderBy('type')->orderBy('source_token')->get();
        return response()->json($rules);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'         => 'required|in:synonym,noise',
            'source_token' => 'required|string|max:100',
            'target_token' => 'nullable|string|max:100',
        ]);

        $rule = ReconcileProductRule::updateOrCreate(
            ['type' => $data['type'], 'source_token' => $data['source_token']],
            [
                'target_token' => $data['target_token'] ?? '',
                'active'       => true,
                'created_by'   => $request->user()->id,
            ]
        );

        return response()->json(['success' => true, 'rule' => $rule]);
    }

    public function deleteRule(int $id): JsonResponse
    {
        ReconcileProductRule::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Returns tax rates and warengruppen for the "new product" form in the modal.
     */
    public function newProductFormData(): JsonResponse
    {
        $taxRates    = DB::table('tax_rates')->where('active', 1)->get(['id', 'name', 'rate_basis_points']);
        $warengruppen = Warengruppe::where('active', true)->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'tax_rates'    => $taxRates,
            'warengruppen' => $warengruppen,
        ]);
    }

    /**
     * Creates a new product directly from Ninox data (without a WaWi link),
     * then confirms the source_match so it is immediately linked.
     */
    public function createProduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ninox_id'       => 'required|string',
            'produktname'    => 'required|string|max:255',
            'artikelnummer'  => 'required|string|max:100',
            'brutto_preis'   => 'required|numeric|min:0',
            'tax_rate_id'    => 'required|integer|exists:tax_rates,id',
            'warengruppe_id' => 'nullable|integer|exists:warengruppen,id',
            'ean'            => 'nullable|string|max:50',
        ]);

        // Artikelnummer collision check
        if (Product::where('artikelnummer', $data['artikelnummer'])->exists()) {
            return response()->json([
                'success' => false,
                'error'   => 'Artikelnummer „' . $data['artikelnummer'] . '" ist bereits vergeben.',
            ], 422);
        }

        // Already confirmed?
        $alreadyConfirmed = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
            ->where('source', SourceMatch::SOURCE_NINOX)
            ->where('source_id', $data['ninox_id'])
            ->where('status', SourceMatch::STATUS_CONFIRMED)
            ->whereNotNull('local_id')
            ->exists();

        if ($alreadyConfirmed) {
            return response()->json([
                'success' => false,
                'error'   => 'Dieser Ninox-Artikel ist bereits mit einem lokalen Produkt verknüpft.',
            ], 422);
        }

        // Tax rate → derive netto from brutto
        $taxRate   = DB::table('tax_rates')->find($data['tax_rate_id']);
        $taxFactor = 1 + ($taxRate->rate_basis_points / 10000);
        $bruttoFloat = (float) $data['brutto_preis'];
        $nettoFloat  = $taxFactor > 0 ? $bruttoFloat / $taxFactor : $bruttoFloat;

        $netMilli   = (int) round($nettoFloat  * 1_000_000);
        $grossMilli = (int) round($bruttoFloat * 1_000_000);

        // Unique slug
        $base    = Str::slug($data['produktname']) ?: 'produkt';
        $slug    = $base;
        $counter = 2;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }

        $product = Product::create([
            'artikelnummer'          => $data['artikelnummer'],
            'slug'                   => $slug,
            'produktname'            => $data['produktname'],
            'tax_rate_id'            => $data['tax_rate_id'],
            'base_price_net_milli'   => $netMilli,
            'base_price_gross_milli' => $grossMilli,
            'availability_mode'      => Product::AVAILABILITY_AVAILABLE,
            'active'                 => true,
            'show_in_shop'           => true,
            'is_bundle'              => false,
            'warengruppe_id'         => $data['warengruppe_id'] ?? null,
            'ninox_artikel_id'       => (int) $data['ninox_id'],
            'wawi_artikel_id'        => null,
        ]);

        // EAN barcode
        $ean = trim((string) ($data['ean'] ?? ''));
        if ($ean !== '' && $ean !== '0' && ! ProductBarcode::where('barcode', $ean)->exists()) {
            ProductBarcode::create([
                'product_id'   => $product->id,
                'barcode'      => $ean,
                'barcode_type' => 'EAN-13',
                'is_primary'   => true,
            ]);
        }

        // Ninox row for snapshot
        $ninoxRow = DB::table('ninox_marktbestand')->where('ninox_id', $data['ninox_id'])->first();

        // Confirm source_match → local_id set immediately
        SourceMatch::updateOrCreate(
            [
                'entity_type' => SourceMatch::ENTITY_PRODUCT,
                'source'      => SourceMatch::SOURCE_NINOX,
                'source_id'   => $data['ninox_id'],
            ],
            [
                'local_id'        => $product->id,
                'status'          => SourceMatch::STATUS_CONFIRMED,
                'matched_by'      => $request->user()->id,
                'source_snapshot' => $ninoxRow ? (array) $ninoxRow : [],
                'diff_at_match'   => [],
                'confirmed_at'    => now(),
            ]
        );

        return response()->json([
            'success'      => true,
            'product_id'   => $product->id,
            'produktname'  => $product->produktname,
            'artikelnummer'=> $product->artikelnummer,
        ]);
    }

    public function wawiSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $term         = '%' . $q . '%';
        $gebindeCount = max(0, (int) $request->input('gebinde_count', 0));

        $rows = DB::table('wawi_artikel')
            ->where(function ($query) use ($term): void {
                $query->where('cName',    'like', $term)
                      ->orWhere('cArtNr',  'like', $term)
                      ->orWhere('cBarcode', 'like', $term);
            })
            ->when($gebindeCount > 0, function ($query) use ($gebindeCount): void {
                $query->where(function ($q) use ($gebindeCount): void {
                    $q->where('cName', 'like', '%' . $gebindeCount . 'x%')
                      ->orWhere('cName', 'like', '%' . $gebindeCount . 'X%');
                });
            })
            ->select('kArtikel', 'cArtNr', 'cName', 'cBarcode', 'fVKNetto')
            ->limit(30)
            ->get();

        return response()->json($rows);
    }

    public function importConfirmed(Request $request): RedirectResponse
    {
        $result = $this->service->importConfirmed($request->user()->id);

        $message = sprintf(
            '%d Produkte importiert, %d aktualisiert, %d übersprungen.',
            $result['imported'],
            $result['updated'],
            $result['skipped'],
        );

        if (! empty($result['errors'])) {
            $message .= sprintf(' %d Fehler aufgetreten.', count($result['errors']));
            return back()->with('error', $message)
                ->with('skipped_details', $result['skipped_details']);
        }

        if (! empty($result['skipped_details'])) {
            return back()->with('warning', $message)
                ->with('skipped_details', $result['skipped_details']);
        }

        return back()->with('success', $message);
    }
}
