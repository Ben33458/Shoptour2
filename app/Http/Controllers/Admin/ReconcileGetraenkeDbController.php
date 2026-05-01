<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Integrations\GetraenkeDbClient;
use App\Services\Reconcile\GetraenkeDbMatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReconcileGetraenkeDbController extends Controller
{
    private GetraenkeDbMatchService $service;

    public function __construct()
    {
        $this->service = new GetraenkeDbMatchService(GetraenkeDbClient::make());
    }

    public function index(Request $request): View
    {
        $sort      = $request->input('sort', 'confidence');
        $dir       = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $proposals = $this->service->proposeMatches();

        usort($proposals, function (array $a, array $b) use ($sort, $dir): int {
            $valA = match ($sort) {
                'name'  => mb_strtolower((string) ($a['product']->produktname ?? '')),
                default => $a['confidence'],
            };
            $valB = match ($sort) {
                'name'  => mb_strtolower((string) ($b['product']->produktname ?? '')),
                default => $b['confidence'],
            };
            if ($valA === $valB) return 0;
            $cmp = $valA <=> $valB;
            return $dir === 'asc' ? $cmp : -$cmp;
        });

        return view('admin.reconcile.getraenkedb', compact('proposals', 'sort', 'dir'));
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'product_id' => 'required|integer',
            'slug'       => 'required|string|max:255',
        ]);

        $productId = (int) $request->input('product_id');
        $slug      = $request->input('slug');

        \Illuminate\Support\Facades\Log::info('ReconcileGetraenkeDb::confirm called', [
            'product_id' => $productId,
            'slug'       => $slug,
            'user'       => $request->user()->id,
        ]);

        $this->service->confirm($productId, $slug, $request->user()->id);

        $count = \App\Models\SourceMatch::where('source', 'getraenkedb')
            ->where('entity_type', 'product')
            ->where('status', 'confirmed')
            ->count();

        \Illuminate\Support\Facades\Log::info('ReconcileGetraenkeDb::confirm done', [
            'product_id'     => $productId,
            'slug'           => $slug,
            'confirmed_total'=> $count,
        ]);

        return back()->with('success', "Match bestätigt. (Gesamt bestätigt: {$count})");
    }

    public function ignore(Request $request): RedirectResponse
    {
        $request->validate(['product_id' => 'required|integer']);

        $this->service->ignore(
            (int) $request->input('product_id'),
            $request->user()->id,
        );

        return back()->with('success', 'Produkt abgelehnt.');
    }

    public function bulkConfirm(Request $request): RedirectResponse
    {
        // sel[product_id] = slug
        $sel = $request->input('sel', []);
        if (empty($sel)) {
            return back()->with('error', 'Keine Produkte ausgewählt.');
        }

        $ok = 0;
        foreach ($sel as $productId => $slug) {
            if (empty($slug)) continue;
            try {
                $this->service->confirm((int) $productId, (string) $slug, $request->user()->id);
                $ok++;
            } catch (\Throwable) {}
        }

        return back()->with('success', "{$ok} Match(es) bestätigt.");
    }

    public function bulkIgnore(Request $request): RedirectResponse
    {
        $sel = $request->input('sel', []);
        if (empty($sel)) {
            return back()->with('error', 'Keine Produkte ausgewählt.');
        }

        $ok = 0;
        foreach ($sel as $productId => $_) {
            try {
                $this->service->ignore((int) $productId, $request->user()->id);
                $ok++;
            } catch (\Throwable) {}
        }

        return back()->with('success', "{$ok} Produkt(e) abgelehnt.");
    }

    public function sync(Request $request): RedirectResponse
    {
        $stats = $this->service->syncConfirmed($request->user()->id);

        return back()->with('success', sprintf(
            'Sync abgeschlossen: %d synchronisiert, %d Bilder heruntergeladen, %d Pfand angelegt, %d Fehler.',
            $stats['synced'],
            $stats['images_downloaded'],
            $stats['pfand_created'],
            $stats['errors'],
        ));
    }

    public function clearCache(): RedirectResponse
    {
        \Illuminate\Support\Facades\Artisan::call('cache:clear');

        return back()->with('success', 'GetraenkeDB-Cache geleert. Nächster Seitenaufruf lädt frische API-Daten.');
    }

    public function syncCategories(): RedirectResponse
    {
        $stats = $this->service->syncCategories();

        return back()->with('success', sprintf(
            'Kategorien: %d neu erstellt, %d Produkte zugeordnet, %d Fehler.',
            $stats['categories_created'],
            $stats['products_assigned'],
            $stats['errors'],
        ));
    }
}
