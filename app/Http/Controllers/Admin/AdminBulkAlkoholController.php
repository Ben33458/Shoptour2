<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bulk editor for alkoholgehalt_vol_percent on products in alcoholic Warengruppen.
 * Allows admin to set or clear the alcohol percentage for many products at once.
 */
class AdminBulkAlkoholController extends Controller
{
    /** Warengruppen that typically contain alcoholic products */
    private const ALCOHOLIC_WG_IDS = [2, 3, 4, 5, 6, 7, 9, 10, 11, 12, 13, 14, 16, 17, 18, 19, 20, 21, 23, 27, 28, 29, 31, 32, 33];

    public function index(Request $request): View
    {
        $filter    = $request->input('filter', 'missing'); // missing | all | set
        $search    = trim((string) $request->input('search', ''));
        $wgFilter  = $request->input('wg', '');

        $query = Product::with('warengruppe')
            ->whereIn('warengruppe_id', self::ALCOHOLIC_WG_IDS)
            ->orderBy('warengruppe_id')
            ->orderBy('produktname');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('produktname', 'like', '%' . $search . '%')
                  ->orWhere('artikelnummer', 'like', '%' . $search . '%');
            });
        }

        if ($wgFilter !== '') {
            $query->where('warengruppe_id', (int) $wgFilter);
        }

        if ($filter === 'missing') {
            $query->whereNull('alkoholgehalt_vol_percent');
        } elseif ($filter === 'set') {
            $query->whereNotNull('alkoholgehalt_vol_percent');
        }

        $products = $query->paginate(100)->withQueryString();

        // Count for filter badges
        $baseQuery = Product::whereIn('warengruppe_id', self::ALCOHOLIC_WG_IDS);
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('produktname', 'like', '%' . $search . '%')
                  ->orWhere('artikelnummer', 'like', '%' . $search . '%');
            });
        }
        if ($wgFilter !== '') {
            $baseQuery->where('warengruppe_id', (int) $wgFilter);
        }

        $filterCounts = [
            'all'     => (clone $baseQuery)->count(),
            'missing' => (clone $baseQuery)->whereNull('alkoholgehalt_vol_percent')->count(),
            'set'     => (clone $baseQuery)->whereNotNull('alkoholgehalt_vol_percent')->count(),
        ];

        $warengruppen = \DB::table('warengruppen')
            ->whereIn('id', self::ALCOHOLIC_WG_IDS)
            ->orderBy('name')
            ->get();

        return view('admin.products.bulk-alkohol', compact(
            'products', 'filter', 'search', 'wgFilter', 'filterCounts', 'warengruppen'
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->input('alkohol', []);

        // $data is [product_id => value_string]
        foreach ($data as $productId => $value) {
            $productId = (int) $productId;
            if ($productId <= 0) {
                continue;
            }

            if ($value === '' || $value === null) {
                Product::where('id', $productId)
                    ->whereIn('warengruppe_id', self::ALCOHOLIC_WG_IDS)
                    ->update(['alkoholgehalt_vol_percent' => null]);
            } else {
                $vol = (float) str_replace(',', '.', (string) $value);
                if ($vol >= 0 && $vol <= 100) {
                    Product::where('id', $productId)
                        ->whereIn('warengruppe_id', self::ALCOHOLIC_WG_IDS)
                        ->update(['alkoholgehalt_vol_percent' => $vol]);
                }
            }
        }

        return redirect()->back()->with('success', count($data) . ' Produkte aktualisiert.');
    }
}
