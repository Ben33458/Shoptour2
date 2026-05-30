<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminRecentChangesController extends Controller
{
    public function index(): View
    {
        $since = now()->subDays(30);

        // ── 1. Image additions ────────────────────────────────────────────
        $imageEvents = DB::table('product_images as pi')
            ->join('products as p', 'p.id', '=', 'pi.product_id')
            ->select(
                'p.id as product_id',
                'p.produktname',
                'p.artikelnummer',
                'pi.created_at as changed_at',
                DB::raw("'bild' as type"),
                DB::raw("'Bild hinzugefügt' as type_label")
            )
            ->where('pi.created_at', '>=', $since)
            ->orderBy('pi.created_at', 'desc')
            ->limit(200)
            ->get();

        // ── 2. LMIV imports ───────────────────────────────────────────────
        $lmivEvents = DB::table('product_lmiv_versions as lv')
            ->join('products as p', 'p.id', '=', 'lv.product_id')
            ->select(
                'p.id as product_id',
                'p.produktname',
                'p.artikelnummer',
                'lv.created_at as changed_at',
                DB::raw("'lmiv' as type"),
                DB::raw("'LMIV importiert' as type_label")
            )
            ->where('lv.created_at', '>=', $since)
            ->orderBy('lv.created_at', 'desc')
            ->limit(200)
            ->get();

        // ── 3. General data changes (products not covered by above sources)
        $coveredIds = $imageEvents->pluck('product_id')
            ->merge($lmivEvents->pluck('product_id'))
            ->unique()
            ->values();

        $dataEvents = DB::table('products as p')
            ->select(
                'p.id as product_id',
                'p.produktname',
                'p.artikelnummer',
                'p.updated_at as changed_at',
                DB::raw("'stammdaten' as type"),
                DB::raw("'Stammdaten' as type_label")
            )
            ->whereNotIn('p.id', $coveredIds->isEmpty() ? [0] : $coveredIds)
            ->where('p.updated_at', '>=', $since)
            ->orderBy('p.updated_at', 'desc')
            ->limit(200)
            ->get();

        // ── Merge and sort ────────────────────────────────────────────────
        $allEvents = $imageEvents
            ->concat($lmivEvents)
            ->concat($dataEvents)
            ->sortByDesc('changed_at')
            ->take(75)
            ->values();

        // ── Thumbnail images for each product ─────────────────────────────
        $productIds = $allEvents->pluck('product_id')->unique()->values()->toArray();
        $thumbs = ProductImage::whereIn('product_id', $productIds)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($imgs) => $imgs->first());

        // ── Header stats ──────────────────────────────────────────────────
        $stats = [
            'total'      => $allEvents->count(),
            'bild'       => $allEvents->where('type', 'bild')->count(),
            'lmiv'       => $allEvents->where('type', 'lmiv')->count(),
            'stammdaten' => $allEvents->where('type', 'stammdaten')->count(),
        ];

        return view('admin.recent-changes.index', compact('allEvents', 'thumbs', 'stats'));
    }
}
