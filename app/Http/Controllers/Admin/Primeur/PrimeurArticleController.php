<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Primeur;

use App\Http\Controllers\Controller;
use App\Models\Primeur\PrimeurArticle;
use App\Services\Primeur\MdbReader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PrimeurArticleController extends Controller
{
    private const MDB_DATA = '/var/www/html/primeur_raw/IT_Drink/Tb_data.MDB';

    private const SUPPLIER_NAMES = [
        16 => 'Hassia', 17 => 'Medo Getränke', 18 => 'Odenwald Quelle',
        19 => 'Rapps Säfte', 21 => 'Dölp', 23 => 'Eder',
        26 => 'Schmucker', 28 => 'Pfungstädter Brauerei',
        29 => 'Darmstädter Brauerei', 30 => 'Breisacher Winzer',
        31 => 'Coca Cola', 32 => 'Grohe',
    ];

    // ── Preisdaten gecacht aus MDB laden ──────────────────────────────────

    private function vkPrices(): array
    {
        return Cache::remember('primeur_vk_prices_by_article', 86400, function (): array {
            $byArticle = [];
            foreach (MdbReader::rows(self::MDB_DATA, 'Tb_VKPreise') as $r) {
                $id = (int) $r['ArtikelID'];
                $pg = trim($r['Preisgruppe'] ?? '');
                $eu = strtoupper(trim($r['Artikeleinheit'] ?? ''));
                if (!$id || !$r['PreisAktiv']) {
                    continue;
                }
                $byArticle[$id][$pg][$eu] = (float) $r['VKPreis'];
            }
            return $byArticle;
        });
    }

    private function ekPrices(): array
    {
        return Cache::remember('primeur_ek_prices_by_article', 86400, function (): array {
            $byArticle = [];
            foreach (MdbReader::rows(self::MDB_DATA, 'Tb_EKPreise') as $r) {
                $id  = (int) $r['ArtikelID'];
                $lid = (int) $r['LieferantenID'];
                if (!$id || !$r['EKPreisAktiv']) {
                    continue;
                }
                $byArticle[$id][] = [
                    'lieferant_id'   => $lid,
                    'lieferant_name' => self::SUPPLIER_NAMES[$lid] ?? "Lieferant #{$lid}",
                    'einheit'        => strtoupper(trim($r['Artikeleinheit'] ?? '')),
                    'listen_ek'      => (float) $r['ListenEK'],
                    'effektiver_ek'  => (float) $r['EffektiverEK'],
                ];
            }
            return $byArticle;
        });
    }

    // ── Artikel-Detail ────────────────────────────────────────────────────

    public function show(int $id): View
    {
        $artikel = PrimeurArticle::where('primeur_id', $id)->firstOrFail();

        // VK- und EK-Preise aus gecachten MDB-Daten
        $vkByPG = $this->vkPrices()[$id] ?? [];
        $ekRows = $this->ekPrices()[$id] ?? [];

        // Verkaufsstatistik nach Jahr
        $statsByYear = DB::table('primeur_cash_receipt_items as i')
            ->join('primeur_cash_receipts as r', 'r.id', '=', 'i.cash_receipt_id')
            ->where('r.ist_storno', false)
            ->where('i.zugabe', false)
            ->where('i.artikel_id', $id)
            ->selectRaw('YEAR(i.datum) as jahr,
                ROUND(SUM(i.menge), 2) as menge,
                ROUND(SUM(i.menge * i.vk_preis_tatsaechlich), 2) as umsatz,
                COUNT(DISTINCT r.id) as belege,
                ROUND(AVG(i.vk_preis_tatsaechlich), 4) as avg_preis,
                MIN(i.vk_preis_tatsaechlich) as min_preis,
                MAX(i.vk_preis_tatsaechlich) as max_preis,
                MAX(i.mwst_satz) as mwst_satz')
            ->groupBy('jahr')
            ->orderByDesc('jahr')
            ->get();

        // Letzte 25 Einzelverkäufe
        $recentSales = DB::table('primeur_cash_receipt_items as i')
            ->join('primeur_cash_receipts as r', 'r.id', '=', 'i.cash_receipt_id')
            ->where('r.ist_storno', false)
            ->where('i.zugabe', false)
            ->where('i.artikel_id', $id)
            ->select('i.datum', 'i.menge', 'i.vk_preis_tatsaechlich', 'i.mwst_satz',
                     'r.belegnummer', 'r.kartenzahlung', 'r.barbetrag')
            ->orderByDesc('i.datum')
            ->limit(25)
            ->get();

        return view('admin.primeur.articles.show', compact(
            'artikel', 'vkByPG', 'ekRows', 'statsByYear', 'recentSales'
        ));
    }

    // ── Warengruppe-Detail ────────────────────────────────────────────────

    public function warengruppe(string $name): View
    {
        $decodedName = urldecode($name);

        $articles = DB::table('primeur_articles as a')
            ->leftJoin(DB::raw('(
                SELECT i.artikel_id,
                    ROUND(SUM(i.menge * i.vk_preis_tatsaechlich), 2) AS umsatz,
                    ROUND(SUM(i.menge), 2) AS menge,
                    COUNT(DISTINCT i.cash_receipt_id) AS belege
                FROM primeur_cash_receipt_items i
                JOIN primeur_cash_receipts r ON r.id = i.cash_receipt_id
                WHERE r.ist_storno = 0 AND i.zugabe = 0
                GROUP BY i.artikel_id
            ) s'), 's.artikel_id', '=', 'a.primeur_id')
            ->where('a.warengruppe', $decodedName)
            ->select('a.primeur_id', 'a.artikelnummer', 'a.bezeichnung', 'a.zusatz',
                     'a.warenuntergruppe', 'a.hersteller', 'a.inhalt', 'a.masseinheit', 'a.aktiv',
                     DB::raw('COALESCE(s.umsatz, 0) as umsatz'),
                     DB::raw('COALESCE(s.menge, 0) as menge'),
                     DB::raw('COALESCE(s.belege, 0) as belege'))
            ->orderByDesc('umsatz')
            ->get();

        $untergruppen = $articles->pluck('warenuntergruppe')->filter()->unique()->sort()->values();

        $summary = [
            'artikel'  => $articles->count(),
            'umsatz'   => $articles->sum('umsatz'),
            'menge'    => $articles->sum('menge'),
            'belege'   => $articles->sum('belege'),
            'mit_umsatz' => $articles->where('umsatz', '>', 0)->count(),
        ];

        return view('admin.primeur.articles.warengruppe', compact(
            'decodedName', 'articles', 'untergruppen', 'summary'
        ));
    }

    // ── Warenuntergruppe-Detail ───────────────────────────────────────────

    public function untergruppe(string $wg, string $ug): View
    {
        $wgName = urldecode($wg);
        $ugName = urldecode($ug);

        $articles = DB::table('primeur_articles as a')
            ->leftJoin(DB::raw('(
                SELECT i.artikel_id,
                    ROUND(SUM(i.menge * i.vk_preis_tatsaechlich), 2) AS umsatz,
                    ROUND(SUM(i.menge), 2) AS menge,
                    COUNT(DISTINCT i.cash_receipt_id) AS belege
                FROM primeur_cash_receipt_items i
                JOIN primeur_cash_receipts r ON r.id = i.cash_receipt_id
                WHERE r.ist_storno = 0 AND i.zugabe = 0
                GROUP BY i.artikel_id
            ) s'), 's.artikel_id', '=', 'a.primeur_id')
            ->where('a.warengruppe', $wgName)
            ->where('a.warenuntergruppe', $ugName)
            ->select('a.primeur_id', 'a.artikelnummer', 'a.bezeichnung', 'a.zusatz',
                     'a.hersteller', 'a.inhalt', 'a.masseinheit', 'a.aktiv',
                     DB::raw('COALESCE(s.umsatz, 0) as umsatz'),
                     DB::raw('COALESCE(s.menge, 0) as menge'),
                     DB::raw('COALESCE(s.belege, 0) as belege'))
            ->orderByDesc('umsatz')
            ->get();

        $summary = [
            'artikel'  => $articles->count(),
            'umsatz'   => $articles->sum('umsatz'),
            'menge'    => $articles->sum('menge'),
            'mit_umsatz' => $articles->where('umsatz', '>', 0)->count(),
        ];

        return view('admin.primeur.articles.untergruppe', compact(
            'wgName', 'ugName', 'articles', 'summary'
        ));
    }

    // ── Hersteller-Detail ─────────────────────────────────────────────────

    public function hersteller(string $name): View
    {
        $decodedName = urldecode($name);

        $articles = DB::table('primeur_articles as a')
            ->leftJoin(DB::raw('(
                SELECT i.artikel_id,
                    ROUND(SUM(i.menge * i.vk_preis_tatsaechlich), 2) AS umsatz,
                    ROUND(SUM(i.menge), 2) AS menge,
                    COUNT(DISTINCT i.cash_receipt_id) AS belege,
                    MAX(i.datum) AS letzter_verkauf
                FROM primeur_cash_receipt_items i
                JOIN primeur_cash_receipts r ON r.id = i.cash_receipt_id
                WHERE r.ist_storno = 0 AND i.zugabe = 0
                GROUP BY i.artikel_id
            ) s'), 's.artikel_id', '=', 'a.primeur_id')
            ->where('a.hersteller', $decodedName)
            ->select('a.primeur_id', 'a.artikelnummer', 'a.bezeichnung', 'a.zusatz',
                     'a.warengruppe', 'a.warenuntergruppe', 'a.inhalt', 'a.masseinheit', 'a.aktiv',
                     DB::raw('COALESCE(s.umsatz, 0) as umsatz'),
                     DB::raw('COALESCE(s.menge, 0) as menge'),
                     DB::raw('COALESCE(s.belege, 0) as belege'),
                     's.letzter_verkauf')
            ->orderByDesc('umsatz')
            ->get();

        $warengruppen = $articles->pluck('warengruppe')->filter()->unique()->sort()->values();

        $statsByYear = DB::table('primeur_cash_receipt_items as i')
            ->join('primeur_cash_receipts as r', 'r.id', '=', 'i.cash_receipt_id')
            ->join('primeur_articles as a', 'a.primeur_id', '=', 'i.artikel_id')
            ->where('r.ist_storno', false)
            ->where('i.zugabe', false)
            ->where('a.hersteller', $decodedName)
            ->selectRaw('YEAR(i.datum) as jahr,
                ROUND(SUM(i.menge * i.vk_preis_tatsaechlich), 2) as umsatz,
                ROUND(SUM(i.menge), 2) as menge,
                COUNT(DISTINCT r.id) as belege')
            ->groupBy('jahr')
            ->orderByDesc('jahr')
            ->get();

        $summary = [
            'artikel'    => $articles->count(),
            'umsatz'     => $articles->sum('umsatz'),
            'mit_umsatz' => $articles->where('umsatz', '>', 0)->count(),
        ];

        return view('admin.primeur.articles.hersteller', compact(
            'decodedName', 'articles', 'warengruppen', 'statsByYear', 'summary'
        ));
    }
}
