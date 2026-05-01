<?php

declare(strict_types=1);

namespace App\Services\Wawi;

use Illuminate\Support\Facades\DB;

class WawiSyncService
{
    /**
     * Entity configuration:
     *   table      → target table name
     *   primaryKey → JTL k* field used as upsert key
     *   columns    → whitelisted columns (unknown fields in records are dropped)
     */
    private const ENTITIES = [
        'artikel' => [
            'table'      => 'wawi_artikel',
            'primaryKey' => 'kArtikel',
            'columns'    => ['kArtikel', 'cArtNr', 'cBarcode', 'fVKNetto', 'fEKNetto',
                             'cAktiv', 'dMod', 'cName', 'cBeschreibung'],
        ],
        'kunden' => [
            'table'      => 'wawi_kunden',
            'primaryKey' => 'kKunde',
            'columns'    => ['kKunde', 'cKundenNr', 'dErstellt', 'cSperre', 'cKundenGruppe'],
        ],
        'auftraege' => [
            'table'      => 'wawi_auftraege',
            'primaryKey' => 'kAuftrag',
            'columns'    => ['kAuftrag', 'kKunde', 'cAuftragsNr', 'dErstellt', 'nAuftragStatus',
                             'cWaehrung', 'cRgName', 'cRgMail', 'cRgStrasse', 'cRgPLZ', 'cRgOrt'],
        ],
        'auftragspositionen' => [
            'table'      => 'wawi_auftragspositionen',
            'primaryKey' => 'kAuftragPosition',
            'columns'    => ['kAuftragPosition', 'kAuftrag', 'kArtikel', 'cArtNr', 'cName',
                             'fAnzahl', 'fVkNetto'],
        ],
        'rechnungen' => [
            'table'      => 'wawi_rechnungen',
            'primaryKey' => 'kRechnung',
            'columns'    => ['kRechnung', 'kBestellung', 'kKunde', 'cRechnungsNr',
                             'dErstellt', 'cBezahlt', 'nRechnungStatus'],
        ],
        'lagerbestand' => [
            'table'      => 'wawi_lagerbestand',
            'primaryKey' => 'kArtikel',
            'columns'    => ['kArtikel', 'fLagerbestand', 'fVerfuegbar', 'fZulauf', 'nLagerAktiv'],
        ],
        'warenlager' => [
            'table'      => 'wawi_warenlager',
            'primaryKey' => 'kWarenLager',
            'columns'    => ['kWarenLager', 'cName', 'cKuerzel', 'nAktiv'],
        ],

        // ── Neue Entities ────────────────────────────────────────────────────
        'zahlungen' => [
            'table'      => 'wawi_zahlungen',
            'primaryKey' => 'kZahlung',
            'columns'    => ['kZahlung', 'kRechnung', 'kBestellung', 'fBetrag', 'dDatum',
                             'kZahlungsart', 'nAnzahlung', 'cHinweis', 'nZuweisungstyp',
                             'nZahlungstyp', 'cExternalTransactionId', 'kZahlungsabgleichUmsatz',
                             'nZuweisungswertung', 'kGutschrift'],
        ],
        'rechnung_zahlungen' => [
            'table'      => 'wawi_rechnung_zahlungen',
            'primaryKey' => 'kRechnung',
            'columns'    => ['kRechnung', 'cRechnungsnummer', 'fRechnungswert', 'dBelegdatum',
                             'cKundennummer', 'fBetrag', 'fMahngebuehr', 'kZahlungsart',
                             'fSkontowertInProzent', 'cZahlungsartbezeichnung', 'fOffenerWert',
                             'nZahlungStatus', 'cBestellnummer', 'fAuftragswert', 'dBestelldatum',
                             'cExterneBestellNr', 'cVerwendungszweck'],
        ],
        'artikel_attribute' => [
            'table'      => 'wawi_artikel_attribute',
            'primaryKey' => 'kArtikelAttribut',
            'columns'    => ['kArtikelAttribut', 'kArtikel', 'cAttributName',
                             'cWertVarchar', 'nWertInt', 'fWertDecimal'],
        ],
        'hersteller' => [
            'table'      => 'wawi_hersteller',
            'primaryKey' => 'kHersteller',
            'columns'    => ['kHersteller', 'cName', 'cHomepage', 'cBeschreibung'],
        ],
        'kategorien' => [
            'table'      => 'wawi_kategorien',
            'primaryKey' => 'kKategorie',
            'columns'    => ['kKategorie', 'kOberKategorie', 'cAktiv', 'nSort',
                             'cName', 'cBeschreibung', 'cUrlPfad'],
        ],
        'kategorien_artikel' => [
            'table'      => 'wawi_kategorien_artikel',
            'primaryKey' => ['kArtikel', 'kKategorie'],   // composite key
            'columns'    => ['kArtikel', 'kKategorie'],
        ],
        'zahlungsarten' => [
            'table'      => 'wawi_zahlungsarten',
            'primaryKey' => 'kZahlungsart',
            'columns'    => ['kZahlungsart', 'cName', 'nLastschrift', 'nAusliefernVorZahlung',
                             'nMahnwesenAktiv', 'fSkontoWert', 'nSkontoZeitraum', 'nAktiv'],
        ],
        'versandarten' => [
            'table'      => 'wawi_versandarten',
            'primaryKey' => 'kVersandArt',
            'columns'    => ['kVersandArt', 'cName', 'fPrice', 'cAktiv', 'fVKFreiAB',
                             'fMwSt', 'cTrackingUrlTemplate', 'nExpress'],
        ],
        'preise' => [
            'table'      => 'wawi_preise',
            'primaryKey' => ['kArtikel', 'kKundenGruppe', 'kKunde', 'nAnzahlAb'],   // composite key
            'columns'    => ['kArtikel', 'kKundenGruppe', 'kKunde', 'nAnzahlAb',
                             'fNettoPreis', 'fProzent'],
        ],
    ];

    public function supports(string $entity): bool
    {
        return isset(self::ENTITIES[$entity]);
    }

    /**
     * Upsert a batch of records for the given entity.
     * Returns the number of records processed.
     */
    public function sync(string $entity, array $records): int
    {
        $config     = self::ENTITIES[$entity];
        $now        = now()->toDateTimeString();
        $columns    = $config['columns'];
        $pk         = $config['primaryKey'];
        $pkColumns  = (array) $pk;   // normalise: string or string[]

        $rows = [];
        foreach ($records as $record) {
            $record = (array) $record;

            // Skip records missing any part of the primary key
            foreach ($pkColumns as $pkCol) {
                if (! isset($record[$pkCol]) || $record[$pkCol] === '') {
                    continue 2;
                }
            }

            // Whitelist known columns, fill missing ones with null
            $row = [];
            foreach ($columns as $col) {
                $row[$col] = $record[$col] ?? null;
            }
            $row['created_at'] = $now;
            $row['updated_at'] = $now;

            $rows[] = $row;
        }

        if (empty($rows)) {
            return 0;
        }

        // Columns to update on duplicate key (all except PK columns and created_at)
        $updateColumns = array_values(array_filter(
            array_merge($columns, ['updated_at']),
            fn (string $col) => ! in_array($col, $pkColumns, true) && $col !== 'created_at'
        ));

        // Process in chunks of 500 to avoid max_allowed_packet limits
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($config['table'])->upsert(
                $chunk,
                $pkColumns,
                $updateColumns
            );
        }

        return count($rows);
    }
}
