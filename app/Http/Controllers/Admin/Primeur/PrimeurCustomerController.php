<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Primeur;

use App\Http\Controllers\Controller;
use App\Models\Primeur\PrimeurCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PrimeurCustomerController extends Controller
{
    public function index(Request $request): View
    {
        $query = PrimeurCustomer::query();

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name1', 'like', "%{$search}%")
                  ->orWhere('name2', 'like', "%{$search}%")
                  ->orWhere('kundennummer', 'like', "%{$search}%")
                  ->orWhere('suchname', 'like', "%{$search}%")
                  ->orWhere('ort', 'like', "%{$search}%")
                  ->orWhere('plz', 'like', "%{$search}%");
            });
        }

        if ($group = $request->input('gruppe')) {
            $query->where('kundengruppe', $group);
        }

        $sort  = $request->input('sort', 'name1');
        $dir   = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $allowed = ['name1', 'kundennummer', 'ort', 'plz', 'kundengruppe'];
        if (in_array($sort, $allowed, true)) {
            $query->orderBy($sort, $dir);
        }

        $customers = $query->paginate(50)->withQueryString();

        $gruppen = PrimeurCustomer::whereNotNull('kundengruppe')
            ->distinct()
            ->orderBy('kundengruppe')
            ->pluck('kundengruppe');

        return view('admin.primeur.customers.index', compact('customers', 'gruppen'));
    }

    public function show(int $id): View
    {
        $customer = PrimeurCustomer::where('primeur_id', $id)->firstOrFail();

        // Auftragsstatistik des Kunden
        $orderStats = DB::table('primeur_orders')
            ->where('kunden_id', $customer->primeur_id)
            ->where('storno', false)
            ->whereIn('auftragsart', ['Rechnung', 'Lieferschein'])
            ->select(
                DB::raw('YEAR(belegdatum) as jahr'),
                DB::raw('COUNT(*) as anzahl'),
                DB::raw('ROUND(SUM(endbetrag), 2) as umsatz')
            )
            ->groupBy('jahr')
            ->orderByDesc('jahr')
            ->get();

        // Letzte 20 Aufträge
        $recentOrders = DB::table('primeur_orders')
            ->where('kunden_id', $customer->primeur_id)
            ->orderByDesc('belegdatum')
            ->limit(20)
            ->get();

        return view('admin.primeur.customers.show', compact('customer', 'orderStats', 'recentOrders'));
    }
}
