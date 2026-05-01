<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Primeur;

use App\Http\Controllers\Controller;
use App\Models\Primeur\PrimeurCashDaily;
use App\Models\Primeur\PrimeurCashReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrimeurCashController extends Controller
{
    // ── Tagesübersicht ────────────────────────────────────────────────────

    public function daily(Request $request): View
    {
        $year  = (int) $request->input('jahr', 2024);
        $month = $request->input('monat');

        $query = PrimeurCashDaily::query()->whereYear('datum', $year);
        if ($month) {
            $query->whereMonth('datum', $month);
        }

        $days = $query->orderBy('datum')->get();

        // Monatssummen für das Jahr (umsatz = barbetrag + kartenbetrag, da Belegbetrag=0 in Quelle)
        $monthly = PrimeurCashDaily::select(
            DB::raw('DATE_FORMAT(datum, "%Y-%m") as monat'),
            DB::raw('DATE_FORMAT(datum, "%m") as m'),
            DB::raw('ROUND(SUM(barbetrag + kartenbetrag), 2) as umsatz_brutto'),
            DB::raw('ROUND(SUM(storno_belege + storno_karte + storno_scheck), 2) as storno'),
            DB::raw('ROUND(SUM(barbetrag + kartenbetrag) - SUM(storno_belege + storno_karte + storno_scheck), 2) as umsatz_netto'),
            DB::raw('ROUND(SUM(kartenbetrag), 2) as karte'),
            DB::raw('ROUND(SUM(barbetrag), 2) as bar'),
            DB::raw('COUNT(*) as anzahl_belege'),
            DB::raw('COUNT(*) as anzahl_tage')
        )
        ->whereYear('datum', $year)
        ->groupBy('monat', 'm')
        ->orderBy('monat')
        ->get();

        // MwSt-Aufschlüsselung aus Belegpositionen (Warenebene, ohne Pfand-Verzerrung).
        // Quelle: receipt_items – nur so stimmen Brutto-Anteile korrekt überein.
        // Karte/Bar werden NICHT per MwSt aufgeteilt: die Tages-Bar/Karte (aus cash_daily)
        // und die Beleg-Bar/Karte (aus receipts) divergieren durch Abschöpfungen/Bankeinreichungen
        // und lassen sich nicht sinnvoll überkreuz zuordnen.
        $mwstMonthly = DB::table('primeur_cash_receipt_items as i')
            ->join('primeur_cash_receipts as r', 'r.id', '=', 'i.cash_receipt_id')
            ->where('r.ist_storno', false)
            ->where('i.zugabe', false)
            ->whereYear('i.datum', $year)
            ->selectRaw("
                DATE_FORMAT(i.datum, '%Y-%m') as monat,
                MAX(CASE WHEN i.mwst_satz NOT IN (0.07, 0.00) THEN i.mwst_satz END) as satz_voll,
                ROUND(SUM(CASE WHEN i.mwst_satz NOT IN (0.07, 0.00)
                    THEN i.menge * i.vk_preis_tatsaechlich ELSE 0 END), 2) as brutto_voll,
                ROUND(SUM(CASE WHEN i.mwst_satz NOT IN (0.07, 0.00)
                    THEN i.menge * i.vk_preis_tatsaechlich * i.mwst_satz / (1 + i.mwst_satz) ELSE 0 END), 2) as mwst_voll,
                ROUND(SUM(CASE WHEN i.mwst_satz = 0.07
                    THEN i.menge * i.vk_preis_tatsaechlich ELSE 0 END), 2) as brutto_erm,
                ROUND(SUM(CASE WHEN i.mwst_satz = 0.07
                    THEN i.menge * i.vk_preis_tatsaechlich * 0.07 / 1.07 ELSE 0 END), 2) as mwst_erm
            ")
            ->groupByRaw("DATE_FORMAT(i.datum, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(i.datum, '%Y-%m')")
            ->get()
            ->keyBy('monat');

        $years = range(2015, 2024);

        return view('admin.primeur.cash.daily', compact('days', 'monthly', 'mwstMonthly', 'year', 'month', 'years'));
    }

    // ── Kassenbelege (Einzelbelege) ───────────────────────────────────────

    public function receipts(Request $request): View
    {
        $query = DB::table('primeur_cash_receipts');

        if ($from = $request->input('von')) {
            $query->where('datum', '>=', $from);
        }
        if ($to = $request->input('bis')) {
            $query->where('datum', '<=', $to);
        }
        if (! $request->boolean('mit_storno')) {
            $query->where('ist_storno', false);
        }
        if ($beleg = $request->input('beleg_nr')) {
            $query->where('belegnummer', (int) $beleg);
        }

        $sort    = $request->input('sort', 'datum');
        $dir     = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $allowed = ['datum', 'belegnummer', 'gesamtbetrag', 'kassen_nr'];
        if (in_array($sort, $allowed, true)) {
            $query->orderBy($sort, $dir);
        }

        $receipts = $query->paginate(100)->withQueryString();

        return view('admin.primeur.cash.receipts', compact('receipts'));
    }

    // ── Monatsstatistik ───────────────────────────────────────────────────

    public function monthly(Request $request): View
    {
        $monthly = PrimeurCashDaily::select(
            DB::raw('YEAR(datum) as jahr'),
            DB::raw('MONTH(datum) as monat_nr'),
            DB::raw('DATE_FORMAT(datum, "%Y-%m") as monat'),
            DB::raw('ROUND(SUM(barbetrag + kartenbetrag), 2) as umsatz_brutto'),
            DB::raw('ROUND(SUM(storno_belege + storno_karte + storno_scheck), 2) as storno'),
            DB::raw('ROUND(SUM(barbetrag + kartenbetrag) - SUM(storno_belege + storno_karte + storno_scheck), 2) as umsatz_netto'),
            DB::raw('ROUND(SUM(kartenbetrag), 2) as karte'),
            DB::raw('ROUND(SUM(barbetrag), 2) as bar'),
            DB::raw('COUNT(*) as anzahl_belege'),
            DB::raw('NULL as avg_bon')
        )
        ->groupBy('jahr', 'monat_nr', 'monat')
        ->orderByDesc('monat')
        ->get();

        return view('admin.primeur.cash.monthly', compact('monthly'));
    }

    // ── CSV-Export Monatsstatistik ─────────────────────────────────────────

    public function exportMonthly(Request $request): StreamedResponse
    {
        $year = $request->input('jahr');

        $query = PrimeurCashDaily::select(
            DB::raw('YEAR(datum) as Jahr'),
            DB::raw('MONTH(datum) as Monat'),
            DB::raw('DATE_FORMAT(datum, "%Y-%m") as Zeitraum'),
            DB::raw('ROUND(SUM(barbetrag + kartenbetrag), 2) as Umsatz_Brutto'),
            DB::raw('ROUND(SUM(storno_belege + storno_karte + storno_scheck), 2) as Storno'),
            DB::raw('ROUND(SUM(barbetrag + kartenbetrag) - SUM(storno_belege + storno_karte + storno_scheck), 2) as Umsatz_Netto'),
            DB::raw('ROUND(SUM(kartenbetrag), 2) as Kartenzahlung'),
            DB::raw('ROUND(SUM(barbetrag), 2) as Barzahlung'),
            DB::raw('COUNT(*) as Anzahl_Belege'),
            DB::raw('COUNT(*) as Anzahl_Tage')
        )
        ->groupBy('Jahr', 'Monat', 'Zeitraum')
        ->orderByDesc('Zeitraum');

        if ($year) {
            $query->whereYear('datum', (int) $year);
        }

        $rows = $query->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM for Excel
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_keys((array) $rows->first()), ';');
            foreach ($rows as $row) {
                fputcsv($out, array_values((array) $row), ';');
            }
            fclose($out);
        }, 'kassenumsaetze_monatlich.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ── CSV-Export Tagesumsätze ────────────────────────────────────────────

    public function exportDaily(Request $request): StreamedResponse
    {
        $year = (int) $request->input('jahr', 2024);

        $rows = PrimeurCashDaily::select([
            'datum',
            DB::raw('DAYNAME(datum) as wochentag'),
            DB::raw('NULL as anzahl_belege'),
            DB::raw('ROUND(barbetrag + kartenbetrag, 2) as umsatz_brutto'),
            DB::raw('ROUND(storno_belege + storno_karte + storno_scheck, 2) as storno'),
            DB::raw('ROUND(barbetrag + kartenbetrag - storno_belege - storno_karte - storno_scheck, 2) as umsatz_netto'),
            'kartenbetrag',
            'barbetrag',
            'bankeinreichung',
            'anz_rabatt',
            'rabattbetrag',
        ])
        ->whereYear('datum', $year)
        ->orderBy('datum')
        ->get();

        return response()->streamDownload(function () use ($rows, $year): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            if ($rows->isNotEmpty()) {
                fputcsv($out, array_keys((array) $rows->first()), ';');
            }
            foreach ($rows as $row) {
                fputcsv($out, array_values((array) $row), ';');
            }
            fclose($out);
        }, "kassenumsaetze_taeglich_{$year}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
