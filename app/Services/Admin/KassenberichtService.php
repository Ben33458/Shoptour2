<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

class KassenberichtService
{
    /**
     * Build the monthly VAT report from stats_pos_daily.
     *
     * MwSt rate comes directly from wawi_dbo_pos_bonposition.tArtikel_fMwSt
     * (stored in stats_pos_daily.mwst_satz after stats:refresh-pos).
     * No dependency on shoptour2's products/tax_rates tables.
     *
     * Returns array with keys:
     *   monat          string   'YYYY-MM'
     *   monat_label    string   'April 2026'
     *   zeilen         array    MwSt rows (regular sales, excluding Pfand/Leergut)
     *   pfand_brutto   float    Deposit income (is_pfand=1, is_leergut=0)
     *   leergut_brutto float    Empty-bottle returns (is_leergut=1)
     *   pfand_netto    float
     *   leergut_netto  float
     *   summe_brutto   float
     *   summe_netto    float
     *   summe_mwst     float
     *   hat_daten      bool
     */
    public function forMonth(string $monat): array
    {
        $from = $monat . '-01';
        $to   = date('Y-m-t', strtotime($from));

        $rows = DB::select("
            SELECT
                COALESCE(s.mwst_satz, 19.00) AS mwst_satz,
                s.is_pfand,
                s.is_leergut,
                SUM(s.umsatz) AS brutto,
                SUM(s.menge)  AS menge
            FROM stats_pos_daily s
            WHERE s.bon_date BETWEEN ? AND ?
              AND s.is_mhd_writeoff = 0
            GROUP BY mwst_satz, s.is_pfand, s.is_leergut
            ORDER BY s.is_pfand ASC, s.is_leergut ASC, mwst_satz DESC
        ", [$from, $to]);


        $zeilen         = [];
        $pfand_brutto   = 0.0;
        $leergut_brutto = 0.0;
        $pfand_netto    = 0.0;
        $leergut_netto  = 0.0;

        $mwstGroups = [];

        foreach ($rows as $row) {
            $brutto   = (float) $row->brutto;
            $mwstSatz = (float) $row->mwst_satz;
            $netto    = $this->nettoFromBrutto($brutto, $mwstSatz);

            if ($row->is_leergut) {
                $leergut_brutto += $brutto;
                $leergut_netto  += $netto;
            } elseif ($row->is_pfand) {
                $pfand_brutto += $brutto;
                $pfand_netto  += $netto;
            } else {
                $key = number_format($mwstSatz, 2);
                if (!isset($mwstGroups[$key])) {
                    $mwstGroups[$key] = [
                        'mwst_satz' => $mwstSatz,
                        'brutto'    => 0.0,
                        'menge'     => 0.0,
                    ];
                }
                $mwstGroups[$key]['brutto'] += $brutto;
                $mwstGroups[$key]['menge']  += (float) $row->menge;
            }
        }

        // Sort descending by tax rate (19% first)
        usort($mwstGroups, fn ($a, $b) => $b['mwst_satz'] <=> $a['mwst_satz']);

        foreach ($mwstGroups as $group) {
            $brutto = $group['brutto'];
            $netto  = $this->nettoFromBrutto($brutto, $group['mwst_satz']);
            $zeilen[] = [
                'steuersatz' => $this->formatSteuersatz($group['mwst_satz']),
                'mwst_satz'  => $group['mwst_satz'],
                'brutto'     => $brutto,
                'netto'      => $netto,
                'mwst'       => $brutto - $netto,
                'menge'      => $group['menge'],
            ];
        }

        $summeBrutto = array_sum(array_column($zeilen, 'brutto'));
        $summeNetto  = array_sum(array_column($zeilen, 'netto'));
        $summeMwst   = array_sum(array_column($zeilen, 'mwst'));

        $zahlungen = $this->zahlungsartenForMonth($from, $to);

        $bonCount = (int) DB::scalar("
            SELECT COUNT(*)
            FROM wawi_dbo_pos_bon b
            WHERE DATE(STR_TO_DATE(SUBSTRING(b.dDatum,1,19),'%Y-%m-%d %H:%i:%s')) BETWEEN ? AND ?
              AND b.kBonStorno = 0
              AND b.kKunde != 618
        ", [$from, $to]);

        $isCurrentOrFuture = $monat >= now()->format('Y-m');

        return [
            'monat'             => $monat,
            'monat_label'       => $this->monatLabel($monat),
            'zeilen'            => $zeilen,
            'pfand_brutto'      => $pfand_brutto,
            'pfand_netto'       => $pfand_netto,
            'leergut_brutto'    => $leergut_brutto,
            'leergut_netto'     => $leergut_netto,
            'summe_brutto'      => $summeBrutto,
            'summe_netto'       => $summeNetto,
            'summe_mwst'        => $summeMwst,
            'hat_daten'         => !empty($zeilen) || $pfand_brutto != 0.0 || $leergut_brutto != 0.0,
            'zahlungen'         => $zahlungen,
            'bon_count'         => $bonCount,
            'daten_vollstaendig' => $isCurrentOrFuture || $bonCount >= 1000,
        ];
    }

    private function zahlungsartenForMonth(string $from, string $to): array
    {
        $rows = DB::select("
            SELECT
                u.cTyp,
                u.cZahlart,
                u.fMwSt1, u.fSumme1,
                u.fMwSt2, u.fSumme2,
                u.fGesamtSumme,
                u.fAuftragsbezahlung
            FROM wawi_dbo_pos_umsaetze u
            JOIN wawi_dbo_pos_bon b ON b.kBon = u.kBon
            WHERE DATE(STR_TO_DATE(SUBSTRING(b.dDatum,1,19),'%Y-%m-%d %H:%i:%s')) BETWEEN ? AND ?
              AND b.kBonStorno = 0
              AND b.kKunde != 618
              AND u.cTyp IN ('Verkauf', 'Auftragszahlung', 'Rückgabe', 'Rückgabe/Verkauf')
        ", [$from, $to]);

        // kassenumsatz[zahlart_gruppe][mwst_key] = ['brutto', 'netto', 'mwst']
        $kassenumsatz = [];
        // auftragszahlungen[zahlart_gruppe] = brutto
        $auftragsZahlungen = ['bar' => 0.0, 'ec' => 0.0, 'sonstige' => 0.0];

        foreach ($rows as $row) {
            $gruppe = $this->zahlartGruppe($row->cZahlart);
            $isAuftrag = ($row->cTyp === 'Auftragszahlung');

            if ($isAuftrag) {
                $auftragsZahlungen[$gruppe] += (float) $row->fGesamtSumme;
                continue;
            }

            // Kassenumsatz (Verkauf, Rückgabe, Rückgabe/Verkauf)
            foreach ([
                [(float)$row->fMwSt1, (float)$row->fSumme1],
                [(float)$row->fMwSt2, (float)$row->fSumme2],
            ] as [$mwstSatz, $brutto]) {
                if ($mwstSatz <= 0 || $brutto == 0.0) {
                    continue;
                }
                $mwstKey = number_format($mwstSatz, 2);
                if (!isset($kassenumsatz[$gruppe][$mwstKey])) {
                    $kassenumsatz[$gruppe][$mwstKey] = ['mwst_satz' => $mwstSatz, 'brutto' => 0.0, 'netto' => 0.0, 'mwst' => 0.0];
                }
                $netto = $this->nettoFromBrutto($brutto, $mwstSatz);
                $kassenumsatz[$gruppe][$mwstKey]['brutto'] += $brutto;
                $kassenumsatz[$gruppe][$mwstKey]['netto']  += $netto;
                $kassenumsatz[$gruppe][$mwstKey]['mwst']   += $brutto - $netto;
            }
        }

        // Build gesamt (sum across all zahlart groups)
        $gesamt = [];
        foreach ($kassenumsatz as $gruppeData) {
            foreach ($gruppeData as $mwstKey => $data) {
                if (!isset($gesamt[$mwstKey])) {
                    $gesamt[$mwstKey] = ['mwst_satz' => $data['mwst_satz'], 'brutto' => 0.0, 'netto' => 0.0, 'mwst' => 0.0];
                }
                $gesamt[$mwstKey]['brutto'] += $data['brutto'];
                $gesamt[$mwstKey]['netto']  += $data['netto'];
                $gesamt[$mwstKey]['mwst']   += $data['mwst'];
            }
        }

        // Sort each group descending by mwst_satz
        $sortFn = fn($a, $b) => $b['mwst_satz'] <=> $a['mwst_satz'];
        foreach ($kassenumsatz as &$gruppeData) {
            uasort($gruppeData, $sortFn);
        }
        unset($gruppeData);
        uasort($gesamt, $sortFn);

        $auftragsGesamt = array_sum($auftragsZahlungen);

        return [
            'kassenumsatz'       => $kassenumsatz,
            'kassenumsatz_gesamt'=> $gesamt,
            'auftragszahlungen'  => $auftragsZahlungen + ['gesamt' => $auftragsGesamt],
            'hat_kassenumsatz'   => !empty($gesamt),
            'hat_auftragszahlung'=> $auftragsGesamt != 0.0,
        ];
    }

    private function zahlartGruppe(string $cZahlart): string
    {
        static $ec = [
            'EC-Karte', 'Girocard', 'VISA', 'Visa', 'EuroCard', 'EuroELV',
            'Maestro', 'Eurocard / Mastercard', 'EC / 2', 'Visa Pay',
            'Amex', '** Karte', 'Kartenerstattung',
        ];
        static $bar = ['Bar', 'Barauszahlung'];

        if (in_array($cZahlart, $bar, true)) {
            return 'bar';
        }
        if (in_array($cZahlart, $ec, true)) {
            return 'ec';
        }
        return 'sonstige';
    }

    private function nettoFromBrutto(float $brutto, float $mwstSatz): float
    {
        if ($mwstSatz <= 0) {
            return $brutto;
        }
        return $brutto * 100 / (100 + $mwstSatz);
    }

    private function formatSteuersatz(float $mwstSatz): string
    {
        return number_format($mwstSatz, 0) . '%';
    }

    private function monatLabel(string $monat): string
    {
        $months = [
            '01' => 'Januar',  '02' => 'Februar', '03' => 'März',
            '04' => 'April',   '05' => 'Mai',      '06' => 'Juni',
            '07' => 'Juli',    '08' => 'August',   '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Dezember',
        ];
        [$year, $month] = explode('-', $monat);
        return ($months[$month] ?? $month) . ' ' . $year;
    }
}
