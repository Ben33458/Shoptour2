<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductLmivVersion;
use App\Services\Catalog\LmivVersioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * WP-15 – LMIV editor for base-item products.
 *
 * Routes:
 *   GET   /admin/products/{product}/lmiv                  edit()          – edit current active/draft version
 *   POST  /admin/products/{product}/lmiv                  update()        – save data_json fields
 *   POST  /admin/products/{product}/lmiv/ean              updateEan()     – change EAN → triggers version rollover
 *   POST  /admin/products/{product}/lmiv/new-version      newVersion()    – create manual draft version
 *   POST  /admin/products/{product}/lmiv/{version}/activate  activate()  – activate a draft version
 */
class AdminLmivController extends Controller
{
    public function __construct(
        private readonly LmivVersioningService $lmivService,
    ) {}

    /**
     * GET /admin/products/{product}/lmiv
     */
    public function edit(Product $product): View
    {
        $this->requireBaseItem($product);

        $activeVersion = $product->activeLmivVersion;
        $draftVersion  = ProductLmivVersion::where('product_id', $product->getKey())
            ->where('status', ProductLmivVersion::STATUS_DRAFT)
            ->orderByDesc('version_number')
            ->first();

        // Show draft if available, otherwise active
        $editVersion = $draftVersion ?? $activeVersion;

        $allVersions = $product->lmivVersions()->with('createdBy')->get();

        return view('admin.lmiv.edit', compact(
            'product',
            'editVersion',
            'activeVersion',
            'draftVersion',
            'allVersions',
        ));
    }

    /**
     * POST /admin/products/{product}/lmiv
     * Save LMIV data fields (no EAN change).
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->requireBaseItem($product);

        $data = $this->validateLmivData($request);

        $this->lmivService->updateData($product, $data, $request->user()?->id);

        return redirect()
            ->route('admin.lmiv.edit', $product)
            ->with('success', 'LMIV-Daten gespeichert.');
    }

    /**
     * POST /admin/products/{product}/lmiv/ean
     * Change the active EAN — triggers automatic version rollover.
     */
    public function updateEan(Request $request, Product $product): RedirectResponse
    {
        $this->requireBaseItem($product);

        $validated = $request->validate([
            'ean'           => ['required', 'string', 'max:30'],
            'change_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->lmivService->onEanChange(
            product:      $product,
            newEan:       $validated['ean'],
            changeReason: $validated['change_reason'] ?? null,
            actorUserId:  $request->user()?->id,
        );

        return redirect()
            ->route('admin.lmiv.edit', $product)
            ->with('success', "EAN aktualisiert – neue LMIV-Version erstellt.");
    }

    /**
     * POST /admin/products/{product}/lmiv/new-version
     * Create a manual draft version.
     */
    public function newVersion(Request $request, Product $product): RedirectResponse
    {
        $this->requireBaseItem($product);

        $validated = $request->validate([
            'change_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->lmivService->createManualVersion(
            product:      $product,
            changeReason: $validated['change_reason'] ?? 'Manueller Entwurf',
            actorUserId:  $request->user()?->id,
        );

        return redirect()
            ->route('admin.lmiv.edit', $product)
            ->with('success', 'Neuer Entwurf erstellt.');
    }

    /**
     * POST /admin/products/{product}/lmiv/{version}/activate
     * Activate a draft version.
     */
    public function activate(Request $request, Product $product, ProductLmivVersion $version): RedirectResponse
    {
        $this->requireBaseItem($product);

        if ($version->product_id !== $product->getKey()) {
            abort(404);
        }

        $this->lmivService->activateDraft($version, $request->user()?->id);

        return redirect()
            ->route('admin.lmiv.edit', $product)
            ->with('success', "Version {$version->version_number} aktiviert.");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requireBaseItem(Product $product): void
    {
        if (! $product->is_base_item) {
            abort(422, 'Dieses Produkt ist kein Basis-Artikel.');
        }
    }

    /**
     * Validate and return the LMIV data fields from the request.
     *
     * The data_json structure covers the main LMIV fields required by
     * EU Regulation 1169/2011 (Lebensmittelinformationsverordnung):
     *
     * @return array<string, mixed>
     */
    private function validateLmivData(Request $request): array
    {
        $request->validate([
            // ── Pflichtangaben ──────────────────────────────────────────────
            'lmiv.produktname'         => ['nullable', 'string', 'max:255'],
            'lmiv.hersteller'          => ['nullable', 'string', 'max:255'],
            'lmiv.herstelleranschrift' => ['nullable', 'string', 'max:500'],
            'lmiv.nettofuellmenge'     => ['nullable', 'string', 'max:50'],
            'lmiv.alkoholgehalt'       => ['nullable', 'numeric', 'min:0', 'max:100'],

            // ── Zutaten & Allergene ─────────────────────────────────────────
            'lmiv.zutaten'             => ['nullable', 'string', 'max:5000'],
            'lmiv.allergene'           => ['nullable', 'string', 'max:1000'],

            // ── Nährwerte je 100ml / 100g ───────────────────────────────────
            'lmiv.nw_energie_kj'       => ['nullable', 'numeric', 'min:0'],
            'lmiv.nw_energie_kcal'     => ['nullable', 'numeric', 'min:0'],
            'lmiv.nw_fett'             => ['nullable', 'numeric', 'min:0'],
            'lmiv.nw_fett_gesaettigt'  => ['nullable', 'numeric', 'min:0'],
            'lmiv.nw_kohlenhydrate'    => ['nullable', 'numeric', 'min:0'],
            'lmiv.nw_zucker'           => ['nullable', 'numeric', 'min:0'],
            'lmiv.nw_ballaststoffe'    => ['nullable', 'numeric', 'min:0'],
            'lmiv.nw_eiweiss'          => ['nullable', 'numeric', 'min:0'],
            'lmiv.nw_salz'             => ['nullable', 'numeric', 'min:0'],

            // ── Sonstiges ────────────────────────────────────────────────────
            'lmiv.lagerhinweis'        => ['nullable', 'string', 'max:500'],
            'lmiv.herkunftsland'       => ['nullable', 'string', 'max:100'],
            'lmiv.zusatzinfos'         => ['nullable', 'string', 'max:1000'],
        ]);

        return $request->input('lmiv', []);
    }
}
