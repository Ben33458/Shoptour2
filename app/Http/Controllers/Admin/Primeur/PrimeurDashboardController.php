<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Primeur;

use App\Http\Controllers\Controller;
use App\Models\Primeur\PrimeurCashDaily;
use App\Models\Primeur\PrimeurCashReceipt;
use App\Models\Primeur\PrimeurCustomer;
use App\Models\Primeur\PrimeurImportRun;
use App\Models\Primeur\PrimeurOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PrimeurDashboardController extends Controller
{
    public function __invoke(): View
    {
        $stats = [
            'customers' => PrimeurCustomer::count(),
            'orders'    => PrimeurOrder::count(),
            'receipts'  => PrimeurCashReceipt::count(),
            'cash_days' => PrimeurCashDaily::count(),
        ];

        // Jahresumsätze (Kassenumsatz = barbetrag + kartenbetrag, da Belegbetrag in Tb_Umsätze=0)
        $yearlyTurnover = PrimeurCashDaily::select(
            DB::raw('YEAR(datum) as jahr'),
            DB::raw('ROUND(SUM(barbetrag + kartenbetrag), 2) as umsatz_brutto'),
            DB::raw('ROUND(SUM(storno_belege + storno_karte + storno_scheck), 2) as storno_summe'),
            DB::raw('ROUND(SUM(barbetrag + kartenbetrag) - SUM(storno_belege + storno_karte + storno_scheck), 2) as umsatz_netto'),
            DB::raw('COUNT(*) as anzahl_belege')
        )
        ->groupBy('jahr')
        ->orderBy('jahr')
        ->get();

        // Letzter Importlauf
        $lastRun = PrimeurImportRun::latest()->first();

        // Monatsumsätze aktuelles Jahr (2024)
        $monthlyTurnover2024 = PrimeurCashDaily::select(
            DB::raw('DATE_FORMAT(datum, "%Y-%m") as monat'),
            DB::raw('ROUND(SUM(barbetrag + kartenbetrag), 2) as umsatz'),
            DB::raw('ROUND(SUM(storno_belege + storno_karte + storno_scheck), 2) as storno'),
            DB::raw('COUNT(*) as anzahl_belege'),
            DB::raw('ROUND(SUM(kartenbetrag), 2) as kartenzahlung'),
            DB::raw('ROUND(SUM(barbetrag), 2) as bar')
        )
        ->whereYear('datum', 2024)
        ->groupBy('monat')
        ->orderBy('monat')
        ->get();

        return view('admin.primeur.dashboard', compact(
            'stats', 'yearlyTurnover', 'lastRun', 'monthlyTurnover2024'
        ));
    }
}
