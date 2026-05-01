<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Primeur;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrimeurStatsController extends Controller
{
    // ── Kundenumsatzstatistik ─────────────────────────────────────────────

    public function customers(Request $request): View
    {
        $year  = $request->input('jahr');
        $limit = (int) $request->input('limit', 50);

        $query = DB::table('primeur_orders as o')
            ->join('primeur_customers as c', 'c.primeur_id', '=', 'o.kunden_id')
            ->where('o.storno', false)
            ->whereIn('o.auftragsart', ['Rechnung', 'Lieferschein'])
            ->select(
                'c.primeur_id',
                'c.name1',
                'c.name2',
                'c.kundennummer',
                'c.ort',
                DB::raw('COUNT(*) as anzahl_belege'),
                DB::raw('ROUND(SUM(o.endbetrag), 2) as umsatz_gesamt'),
                DB::raw('ROUND(AVG(o.endbetrag), 2) as avg_bon'),
                DB::raw('MIN(o.belegdatum) as erster_kauf'),
                DB::raw('MAX(o.belegdatum) as letzter_kauf')
            )
            ->groupBy('c.primeur_id', 'c.name1', 'c.name2', 'c.kundennummer', 'c.ort')
            ->orderByDesc('umsatz_gesamt')
            ->limit($limit);

        if ($year) {
            $query->whereYear('o.belegdatum', (int) $year);
        }

        $topCustomers = $query->get();

        $years = DB::table('primeur_orders')
            ->whereNotNull('belegdatum')
            ->selectRaw('DISTINCT YEAR(belegdatum) as jahr')
            ->orderByDesc('jahr')
            ->pluck('jahr');

        return view('admin.primeur.stats.customers', compact('topCustomers', 'years', 'year', 'limit'));
    }

    // ── Umsatzstatistik ───────────────────────────────────────────────────

    public function revenue(Request $request): View
    {
        // Jahresumsätze (Kassenseite)
        $cashYearly = DB::table('primeur_cash_daily')
            ->select(
                DB::raw('YEAR(datum) as jahr'),
                DB::raw('ROUND(SUM(barbetrag + kartenbetrag), 2) as umsatz_brutto'),
                DB::raw('ROUND(SUM(storno_belege + storno_karte + storno_scheck), 2) as storno'),
                DB::raw('ROUND(SUM(barbetrag + kartenbetrag) - SUM(storno_belege + storno_karte + storno_scheck), 2) as umsatz_netto'),
                DB::raw('ROUND(SUM(kartenbetrag), 2) as kartenzahlung'),
                DB::raw('ROUND(SUM(barbetrag), 2) as barzahlung'),
                DB::raw('SUM(anz_belege) as anzahl_belege'),
                DB::raw('COUNT(*) as anzahl_tage')
            )
            ->groupBy('jahr')
            ->orderByDesc('jahr')
            ->get();

        // Auftrags-Jahresumsätze (Lieferschein + Rechnung)
        $orderYearly = DB::table('primeur_orders')
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

        // Monatsumsätze (Kasse) für alle Jahre
        $cashMonthly = DB::table('primeur_cash_daily')
            ->select(
                DB::raw('YEAR(datum) as jahr'),
                DB::raw('MONTH(datum) as monat_nr'),
                DB::raw('DATE_FORMAT(datum, "%b") as monat_name'),
                DB::raw('ROUND(SUM(barbetrag + kartenbetrag) - SUM(storno_belege + storno_karte + storno_scheck), 2) as umsatz_netto')
            )
            ->groupBy('jahr', 'monat_nr', 'monat_name')
            ->orderBy('jahr')
            ->orderBy('monat_nr')
            ->get()
            ->groupBy('jahr');

        return view('admin.primeur.stats.revenue', compact('cashYearly', 'orderYearly', 'cashMonthly'));
    }

    // ── Warengruppen-Statistik ─────────────────────────────────────────────

    public function articles(Request $request): View
    {
        $year  = $request->input('jahr');
        $limit = (int) $request->input('limit', 50);

        $query = DB::table('primeur_cash_receipt_items as i')
            ->join('primeur_cash_receipts as r', 'r.id', '=', 'i.cash_receipt_id')
            ->where('r.ist_storno', false)
            ->where('i.zugabe', false)
            ->select(
                'i.artikel_id',
                'i.artikel_bezeichnung',
                DB::raw('ROUND(SUM(i.menge), 2) as menge_gesamt'),
                DB::raw('ROUND(SUM(i.menge * i.vk_preis_tatsaechlich), 2) as umsatz'),
                DB::raw('COUNT(DISTINCT r.id) as anzahl_belege')
            )
            ->groupBy('i.artikel_id', 'i.artikel_bezeichnung')
            ->orderByDesc('umsatz')
            ->limit($limit);

        if ($year) {
            $query->whereYear('i.datum', (int) $year);
        }

        $topArticles = $query->get();

        $years = DB::table('primeur_cash_receipt_items')
            ->whereNotNull('datum')
            ->selectRaw('DISTINCT YEAR(datum) as jahr')
            ->orderByDesc('jahr')
            ->pluck('jahr');

        return view('admin.primeur.stats.articles', compact('topArticles', 'years', 'year', 'limit'));
    }

    // ── Artikel-Übersicht (durchsuchbar / filterbar) ──────────────────────

    public function articlesList(Request $request): View
    {
        $search     = trim((string) $request->input('suche', ''));
        $wg         = $request->input('warengruppe');
        $wug        = $request->input('warenuntergruppe');
        $hersteller = $request->input('hersteller');
        $mwst       = $request->input('mwst');
        $sort       = $request->input('sort', 'bezeichnung');
        $dir        = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $flip       = $dir === 'asc' ? 'desc' : 'asc';

        $allowed = ['artikelnummer', 'bezeichnung', 'warengruppe', 'warenuntergruppe',
                    'hersteller', 'umsatz', 'menge_gesamt', 'mwst_dom'];

        // Aggregierte Verkaufsdaten + dominanter MwSt-Satz pro Artikel
        // (korrelierte Subquery für mwst_dom ist OK: Artikel-Tabelle hat ~1700 Einträge, artikel_id ist indiziert)
        $salesSub = DB::raw('(
            SELECT
                i.artikel_id,
                ROUND(SUM(i.menge), 2)                             AS menge_gesamt,
                ROUND(SUM(i.menge * i.vk_preis_tatsaechlich), 2)   AS umsatz,
                COUNT(DISTINCT i.cash_receipt_id)                   AS anzahl_belege,
                MAX(i.datum)                                        AS letzter_verkauf,
                (SELECT mi.mwst_satz
                 FROM primeur_cash_receipt_items mi
                 WHERE mi.artikel_id = i.artikel_id AND mi.zugabe = 0
                 GROUP BY mi.mwst_satz ORDER BY COUNT(*) DESC LIMIT 1
                )                                                   AS mwst_dom
            FROM primeur_cash_receipt_items i
            JOIN primeur_cash_receipts r ON r.id = i.cash_receipt_id
            WHERE i.zugabe = 0 AND i.artikel_id IS NOT NULL AND r.ist_storno = 0
            GROUP BY i.artikel_id
        ) s');

        $query = DB::table('primeur_articles as a')
            ->leftJoin($salesSub, 's.artikel_id', '=', 'a.primeur_id')
            ->select([
                'a.primeur_id', 'a.artikelnummer', 'a.kurzbezeichnung', 'a.bezeichnung',
                'a.zusatz', 'a.warengruppe', 'a.warenuntergruppe',
                'a.inhalt', 'a.masseinheit', 'a.hersteller', 'a.aktiv',
                DB::raw('COALESCE(s.menge_gesamt, 0) as menge_gesamt'),
                DB::raw('COALESCE(s.umsatz, 0) as umsatz'),
                DB::raw('COALESCE(s.anzahl_belege, 0) as anzahl_belege'),
                's.letzter_verkauf',
                DB::raw('CAST(s.mwst_dom AS DECIMAL(5,2)) as mwst_dom'),
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('a.bezeichnung', 'like', "%{$search}%")
                  ->orWhere('a.kurzbezeichnung', 'like', "%{$search}%")
                  ->orWhere('a.artikelnummer', 'like', "%{$search}%")
                  ->orWhere('a.zusatz', 'like', "%{$search}%");
            });
        }
        if ($wg)         { $query->where('a.warengruppe', $wg); }
        if ($wug)        { $query->where('a.warenuntergruppe', $wug); }
        if ($hersteller) { $query->where('a.hersteller', $hersteller); }
        if ($mwst !== null && $mwst !== '') {
            // Alle Artikel die IRGENDWANN mit diesem Satz verkauft wurden
            $query->whereExists(function ($sub) use ($mwst): void {
                $sub->select(DB::raw(1))
                    ->from('primeur_cash_receipt_items as fi')
                    ->join('primeur_cash_receipts as fr', 'fr.id', '=', 'fi.cash_receipt_id')
                    ->whereColumn('fi.artikel_id', 'a.primeur_id')
                    ->where('fi.mwst_satz', (float) $mwst)
                    ->where('fi.zugabe', false)
                    ->where('fr.ist_storno', false);
            });
        }

        $sortCol = match ($sort) {
            'umsatz', 'menge_gesamt', 'anzahl_belege' => "s.{$sort}",
            'mwst_dom'                                 => 'mwst_dom',
            default                                    => "a.{$sort}",
        };
        if (in_array($sort, $allowed, true)) {
            $query->orderByRaw("{$sortCol} IS NULL ASC")->orderBy($sortCol, $dir);
        } else {
            $query->orderBy('a.bezeichnung', 'asc');
        }

        $articles = $query->paginate(100)->withQueryString();

        // Filter-Optionslisten
        $warengruppen     = DB::table('primeur_articles')->whereNotNull('warengruppe')
            ->distinct()->orderBy('warengruppe')->pluck('warengruppe');
        $warenuntergruppen = DB::table('primeur_articles')->whereNotNull('warenuntergruppe')
            ->when($wg, fn ($q) => $q->where('warengruppe', $wg))
            ->distinct()->orderBy('warenuntergruppe')->pluck('warenuntergruppe');
        $herstellers      = DB::table('primeur_articles')->whereNotNull('hersteller')
            ->distinct()->orderBy('hersteller')->pluck('hersteller');
        $mwstSaetze       = DB::table('primeur_cash_receipt_items')
            ->whereNotNull('mwst_satz')->where('zugabe', false)
            ->distinct()->orderBy('mwst_satz')->pluck('mwst_satz');

        return view('admin.primeur.articles.index', compact(
            'articles', 'search', 'wg', 'wug', 'hersteller', 'mwst',
            'sort', 'dir', 'flip',
            'warengruppen', 'warenuntergruppen', 'herstellers', 'mwstSaetze'
        ));
    }

    // ── CSV-Export Kundenumsatz ───────────────────────────────────────────

    public function exportCustomers(Request $request): StreamedResponse
    {
        $year = $request->input('jahr');

        $query = DB::table('primeur_orders as o')
            ->join('primeur_customers as c', 'c.primeur_id', '=', 'o.kunden_id')
            ->where('o.storno', false)
            ->whereIn('o.auftragsart', ['Rechnung', 'Lieferschein'])
            ->select(
                'c.kundennummer as Kundennummer',
                'c.name1 as Name1',
                'c.name2 as Name2',
                'c.ort as Ort',
                DB::raw('COUNT(*) as Anzahl_Belege'),
                DB::raw('ROUND(SUM(o.endbetrag), 2) as Umsatz_Gesamt'),
                DB::raw('ROUND(AVG(o.endbetrag), 2) as Avg_Bon'),
                DB::raw('MIN(o.belegdatum) as Erster_Kauf'),
                DB::raw('MAX(o.belegdatum) as Letzter_Kauf')
            )
            ->groupBy('c.primeur_id', 'c.kundennummer', 'c.name1', 'c.name2', 'c.ort')
            ->orderByDesc('Umsatz_Gesamt');

        if ($year) {
            $query->whereYear('o.belegdatum', (int) $year);
        }

        $rows = $query->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            if ($rows->isNotEmpty()) {
                fputcsv($out, array_keys((array) $rows->first()), ';');
            }
            foreach ($rows as $row) {
                fputcsv($out, array_values((array) $row), ';');
            }
            fclose($out);
        }, 'primeur_kundenumsatz.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
