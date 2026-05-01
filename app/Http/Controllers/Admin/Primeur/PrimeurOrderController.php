<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Primeur;

use App\Http\Controllers\Controller;
use App\Models\Primeur\PrimeurOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PrimeurOrderController extends Controller
{
    public function index(Request $request): View
    {
        $query = DB::table('primeur_orders as o')
            ->leftJoin('primeur_customers as c', 'c.primeur_id', '=', 'o.kunden_id')
            ->select('o.*', 'c.name1', 'c.name2', 'c.kundennummer', 'c.suchname');

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search): void {
                $q->where('c.name1', 'like', "%{$search}%")
                  ->orWhere('c.kundennummer', 'like', "%{$search}%")
                  ->orWhere('o.beleg_nr', '=', (int) $search)
                  ->orWhere('c.suchname', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('art')) {
            $query->where('o.auftragsart', $type);
        }

        if ($from = $request->input('von')) {
            $query->where('o.belegdatum', '>=', $from);
        }
        if ($to = $request->input('bis')) {
            $query->where('o.belegdatum', '<=', $to);
        }

        if (! $request->boolean('mit_storno')) {
            $query->where('o.storno', false);
        }

        $sort    = $request->input('sort', 'belegdatum');
        $dir     = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $allowed = ['belegdatum', 'beleg_nr', 'endbetrag', 'auftragsart'];
        if (in_array($sort, $allowed, true)) {
            $query->orderBy("o.{$sort}", $dir);
        }

        $orders = $query->paginate(50)->withQueryString();

        $arten = DB::table('primeur_orders')
            ->distinct()
            ->whereNotNull('auftragsart')
            ->orderBy('auftragsart')
            ->pluck('auftragsart');

        return view('admin.primeur.orders.index', compact('orders', 'arten'));
    }

    public function show(int $id): View
    {
        $order = DB::table('primeur_orders as o')
            ->leftJoin('primeur_customers as c', 'c.primeur_id', '=', 'o.kunden_id')
            ->select('o.*', 'c.name1', 'c.name2', 'c.kundennummer', 'c.suchname', 'c.ort', 'c.plz', 'c.strasse', 'c.hausnr')
            ->where('o.id', $id)
            ->firstOrFail();

        $items = DB::table('primeur_order_items')
            ->where('order_id', $order->primeur_id)
            ->orderBy('id')
            ->get();

        return view('admin.primeur.orders.show', compact('order', 'items'));
    }
}
