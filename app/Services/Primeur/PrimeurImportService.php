<?php

declare(strict_types=1);

namespace App\Services\Primeur;

use App\Models\Primeur\PrimeurArticle;
use App\Models\Primeur\PrimeurCashDaily;
use App\Models\Primeur\PrimeurCashReceipt;
use App\Models\Primeur\PrimeurCashReceiptItem;
use App\Models\Primeur\PrimeurCashSession;
use App\Models\Primeur\PrimeurCustomer;
use App\Models\Primeur\PrimeurImportRun;
use App\Models\Primeur\PrimeurOrder;
use App\Models\Primeur\PrimeurOrderItem;
use App\Models\Primeur\PrimeurSourceFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\OutputInterface;

class PrimeurImportService
{
    private const RAW_BASE     = '/var/www/html/primeur_raw/IT_Drink';
    private const EXPORTS_BASE = '/var/www/html/primeur_raw/exports';
    private const BATCH    = 500;

    private OutputInterface $output;
    private bool $dryRun = false;
    private bool $force  = false;

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    public function setForce(bool $force): void
    {
        $this->force = $force;
    }

    // ── Kunden ────────────────────────────────────────────────────────────

    public function importCustomers(PrimeurImportRun $run): array
    {
        $mdb = self::RAW_BASE . '/Tb_data.MDB';
        $this->line("  Lese Kunden aus {$mdb}");

        $imported = 0;
        $skipped  = 0;
        $batch    = [];

        $existingIds = $this->force ? [] : PrimeurCustomer::pluck('primeur_id')->flip()->all();

        foreach (MdbReader::rows($mdb, 'Tb_Adressen') as $row) {
            $id = MdbReader::parseInt($row['RecordID'] ?? '');
            if (! $id) {
                $skipped++;
                continue;
            }
            if (! $this->force && isset($existingIds[$id])) {
                $skipped++;
                continue;
            }

            $batch[] = [
                'primeur_id'    => $id,
                'suchname'      => MdbReader::parseStr($row['Suchname'] ?? '', 25),
                'name1'         => MdbReader::parseStr($row['Name1'] ?? '', 40),
                'name2'         => MdbReader::parseStr($row['Name2'] ?? '', 25),
                'name3'         => MdbReader::parseStr($row['Name3'] ?? '', 40),
                'strasse'       => MdbReader::parseStr($row['Strasse'] ?? '', 40),
                'hausnr'        => MdbReader::parseStr(($row['Hausnr'] ?? '') . ($row['HausnrZusatz'] ?? ''), 10),
                'plz'           => MdbReader::parseStr($row['PLZ'] ?? '', 8),
                'ort'           => MdbReader::parseStr($row['Ort'] ?? '', 40),
                'vorwahl'       => MdbReader::parseStr($row['Vorwahl'] ?? '', 10),
                'telefon'       => MdbReader::parseStr($row['Telefon'] ?? '', 30),
                'telefon2'      => MdbReader::parseStr($row['Telefon2'] ?? '', 30),
                'fax'           => MdbReader::parseStr($row['Fax'] ?? '', 30),
                'email'         => MdbReader::parseStr($row['EMail'] ?? '', 80),
                'kundennummer'  => MdbReader::parseStr($row['Kundennummer'] ?? '', 10),
                'kundennummer2' => MdbReader::parseStr($row['Kundennummer2'] ?? '', 10),
                'kundengruppe'  => MdbReader::parseStr($row['Kundengruppe'] ?? '', 30),
                'preisgruppe'   => MdbReader::parseStr($row['Preisgruppe'] ?? '', 30),
                'zahlungsart'   => MdbReader::parseStr($row['Zahlungsart'] ?? '', 30),
                'aktiv'         => MdbReader::parseBool($row['Aktiv'] ?? '1'),
                'anleg_time'    => MdbReader::parseDateTime($row['AnlegTime'] ?? ''),
                'update_time'   => MdbReader::parseDateTime($row['UpdateTime'] ?? ''),
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
            $imported++;

            if (count($batch) >= self::BATCH) {
                $this->flush('primeur_customers', $batch);
                $batch = [];
            }
        }

        if ($batch) {
            $this->flush('primeur_customers', $batch);
        }

        $this->recordSourceFile($run, $mdb, 'main_data', null, $imported);
        return ['imported' => $imported, 'skipped' => $skipped];
    }

    // ── Artikel ───────────────────────────────────────────────────────────

    public function importArticles(PrimeurImportRun $run): array
    {
        $mdb = self::RAW_BASE . '/Tb_data.MDB';
        $this->line("  Lese Artikel aus {$mdb}");

        $imported = 0;
        $skipped  = 0;
        $batch    = [];

        $existingIds = $this->force ? [] : PrimeurArticle::pluck('primeur_id')->flip()->all();

        foreach (MdbReader::rows($mdb, 'Tb_Artikel') as $row) {
            $id = MdbReader::parseInt($row['RecordID'] ?? '');
            if (! $id) {
                $skipped++;
                continue;
            }
            if (! $this->force && isset($existingIds[$id])) {
                $skipped++;
                continue;
            }

            $batch[] = [
                'primeur_id'        => $id,
                'artikelnummer'     => MdbReader::parseStr($row['Artikelnummer'] ?? '', 10),
                'kurzbezeichnung'   => MdbReader::parseStr($row['Kurzbezeichnung'] ?? '', 20),
                'bezeichnung'       => MdbReader::parseStr($row['Bezeichnung'] ?? '', 40),
                'zusatz'            => MdbReader::parseStr($row['Zusatz'] ?? '', 15),
                'warengruppe'       => MdbReader::parseStr($row['Warengruppe'] ?? '', 30),
                'warenuntergruppe'  => MdbReader::parseStr($row['Warenuntergruppe'] ?? '', 30),
                'artikelgruppe'     => MdbReader::parseStr($row['Artikelgruppe'] ?? '', 30),
                'inhalt'            => MdbReader::parseFloat($row['Inhalt'] ?? ''),
                'masseinheit'       => MdbReader::parseStr($row['MasseinheitLang'] ?? '', 10),
                'vk_bezug'          => MdbReader::parseStr($row['VKBezug'] ?? '', 10),
                'hersteller'        => MdbReader::parseStr($row['Hersteller'] ?? '', 40),
                'aktiv'             => true,
                'anleg_time'        => MdbReader::parseDateTime($row['AnlegTime'] ?? ''),
                'update_time'       => MdbReader::parseDateTime($row['UpdateTime'] ?? ''),
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
            $imported++;

            if (count($batch) >= self::BATCH) {
                $this->flush('primeur_articles', $batch);
                $batch = [];
            }
        }

        if ($batch) {
            $this->flush('primeur_articles', $batch);
        }

        $this->recordSourceFile($run, $mdb, 'main_data', null, $imported);
        return ['imported' => $imported, 'skipped' => $skipped];
    }

    // ── Aufträge ──────────────────────────────────────────────────────────

    public function importOrders(PrimeurImportRun $run): array
    {
        $mdb = self::RAW_BASE . '/Tb_Auftr.mdb';
        $this->line("  Lese Aufträge aus {$mdb}");

        $imported = 0;
        $skipped  = 0;
        $batchH   = [];

        $existingIds = $this->force ? [] : PrimeurOrder::pluck('primeur_id')->flip()->all();

        foreach (MdbReader::rows($mdb, 'Tb_AuftragHaupt') as $row) {
            $id = MdbReader::parseInt($row['RecordID'] ?? '');
            if (! $id) {
                $skipped++;
                continue;
            }
            if (! $this->force && isset($existingIds[$id])) {
                $skipped++;
                continue;
            }

            $batchH[] = [
                'primeur_id'       => $id,
                'kunden_id'        => MdbReader::parseInt($row['KundenID'] ?? ''),
                'beleg_nr'         => MdbReader::parseInt($row['BelegNummer'] ?? ''),
                'auftragsart'      => MdbReader::parseStr($row['Auftragsart'] ?? '', 30),
                'rechnungsart'     => MdbReader::parseStr($row['Rechnungsart'] ?? '', 30),
                'belegdatum'       => MdbReader::parseDate($row['Belegdatum'] ?? ''),
                'lieferdatum'      => MdbReader::parseDate($row['Lieferdatum'] ?? ''),
                'rechnungsdatum'   => MdbReader::parseDate($row['Rechnungsdatum'] ?? ''),
                'tour'             => MdbReader::parseStr($row['Tour'] ?? '', 30),
                'sachbearbeiter'   => MdbReader::parseStr($row['Sachbearbeiter'] ?? '', 30),
                'status'           => MdbReader::parseStr($row['Status'] ?? '', 20),
                'storno'           => MdbReader::parseBool($row['Storno'] ?? '0'),
                'zahlungsart'      => MdbReader::parseStr($row['Zahlungsart'] ?? '', 30),
                'warenwert_gesamt' => MdbReader::parseFloat($row['WarenwertGesamt'] ?? ''),
                'gesamt_netto'     => MdbReader::parseFloat($row['ZwisuGesamtNetto'] ?? ''),
                'mehrwertsteuer'   => MdbReader::parseFloat($row['MehrwertsteuerGesamt'] ?? ''),
                'endbetrag'        => MdbReader::parseFloat($row['Endbetrag'] ?? ''),
                'skonto'           => MdbReader::parseFloat($row['Skonto'] ?? ''),
                'waehrung'         => MdbReader::parseStr($row['Währung'] ?? 'EUR', 5) ?? 'EUR',
                'anleg_time'       => MdbReader::parseDateTime($row['AnlegTime'] ?? ''),
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
            $imported++;

            if (count($batchH) >= self::BATCH) {
                $this->flush('primeur_orders', $batchH);
                $batchH = [];
            }
        }

        if ($batchH) {
            $this->flush('primeur_orders', $batchH);
        }

        $this->line("  Aufträge importiert: {$imported}. Importiere nun Positionen...");

        // Auftragspositionen
        $importedItems = 0;
        $batchI = [];
        $existingItemIds = $this->force ? [] : PrimeurOrderItem::pluck('primeur_id')->flip()->all();

        foreach (MdbReader::rows($mdb, 'Tb_AuftragArtikel') as $row) {
            $id = MdbReader::parseInt($row['RecordID'] ?? '');
            if (! $id) {
                continue;
            }
            if (! $this->force && isset($existingItemIds[$id])) {
                continue;
            }

            $batchI[] = [
                'primeur_id'            => $id,
                'order_id'              => MdbReader::parseInt($row['HauptSatzID'] ?? ''),
                'kunden_id'             => MdbReader::parseInt($row['KundenID'] ?? ''),
                'artikel_id'            => MdbReader::parseInt($row['ArtikelID'] ?? ''),
                'artikelnummer'         => MdbReader::parseStr($row['Artikelnummer'] ?? '', 10),
                'artikeleinheit'        => MdbReader::parseStr($row['Artikeleinheit'] ?? '', 1),
                'artikel_bezeichnung'   => MdbReader::parseStr($row['ArtikelBezeichnung'] ?? '', 60),
                'bestellmenge'          => MdbReader::parseFloat($row['Bestellmenge'] ?? ''),
                'liefermenge'           => MdbReader::parseFloat($row['Liefermenge'] ?? ''),
                'fehlmenge'             => MdbReader::parseFloat($row['Fehlmenge'] ?? ''),
                'vk_preis_regulaer'     => MdbReader::parseFloat($row['VKPreisRegulär'] ?? ''),
                'vk_preis_tatsaechlich' => MdbReader::parseFloat($row['VKPreisTatsächlich'] ?? ''),
                'vk_preis_aktion'       => MdbReader::parseFloat($row['VKPreisAktion'] ?? ''),
                'listen_ek'             => MdbReader::parseFloat($row['ListenEK'] ?? ''),
                'effektiver_ek'         => MdbReader::parseFloat($row['EffektiverEK'] ?? ''),
                'pfandbetrag'           => MdbReader::parseFloat($row['Pfandbetrag'] ?? ''),
                'storno'                => false,
                'created_at'            => now(),
                'updated_at'            => now(),
            ];
            $importedItems++;

            if (count($batchI) >= self::BATCH) {
                $this->flush('primeur_order_items', $batchI);
                $batchI = [];
            }
        }

        if ($batchI) {
            $this->flush('primeur_order_items', $batchI);
        }

        $this->recordSourceFile($run, $mdb, 'main_orders', null, $imported + $importedItems);
        return [
            'imported' => $imported + $importedItems,
            'skipped'  => $skipped,
            'notes'    => "Aufträge: {$imported}, Positionen: {$importedItems}",
        ];
    }

    // ── Tagesumsatz (pre-exported CSV von Tb_U*.mdb) ──────────────────────

    public function importCashDaily(PrimeurImportRun $run, ?int $onlyYear = null): array
    {
        $years    = $onlyYear ? [$onlyYear] : range(2015, 2024);
        $imported = 0;
        $skipped  = 0;

        $existingDates = $this->force ? [] : PrimeurCashDaily::pluck('datum', 'datum')->all();

        foreach ($years as $year) {
            // Tb_Umsätze has umlauts in its name – use pre-exported CSV
            $csv = self::EXPORTS_BASE . "/cash_daily_{$year}.csv";
            $mdb = self::RAW_BASE . "/{$year}/Tb_U{$year}.mdb";

            if (! file_exists($csv)) {
                $this->line("  [SKIP] CSV nicht gefunden: {$csv}");
                continue;
            }

            $this->line("  Lese Tagesumsätze {$year}...");
            $batch    = [];
            $yearImported = 0;

            foreach (MdbReader::csvRows($csv) as $row) {
                $datum = MdbReader::parseDate($row['Datum'] ?? '');
                if (! $datum) {
                    $skipped++;
                    continue;
                }
                if (! $this->force && isset($existingDates[$datum])) {
                    $skipped++;
                    continue;
                }

                $warenwert = (float) ($row['Warenwert1'] ?? 0)
                           + (float) ($row['Warenwert2'] ?? 0)
                           + (float) ($row['Warenwert3'] ?? 0);

                $batch[] = [
                    'datum'                      => $datum,
                    'markt_id'                   => MdbReader::parseInt($row['MarktID'] ?? ''),
                    'bankeinreichung'             => MdbReader::parseFloat($row['Bankeinreichung'] ?? ''),
                    'storno_ware'                => MdbReader::parseFloat($row['StornoWare'] ?? '') ?? 0,
                    'storno_pfand'               => MdbReader::parseFloat($row['StornoPfand'] ?? '') ?? 0,
                    'wechselgeld'                => MdbReader::parseFloat($row['Wechselgeld'] ?? '') ?? 0,
                    'bezahlt_bar'                => MdbReader::parseFloat($row['BezahltBar'] ?? ''),
                    'bezahlt_scheck'             => MdbReader::parseFloat($row['BezahltScheck'] ?? '') ?? 0,
                    'warenwert_gesamt'            => round($warenwert, 4),
                    'pfand_einnahmen'             => MdbReader::parseFloat($row['PfandEinnahmen'] ?? '') ?? 0,
                    'pfand_ausgaben'              => MdbReader::parseFloat($row['PfandAusgaben'] ?? '') ?? 0,
                    'anz_abschoepf_bar'           => MdbReader::parseInt($row['AnzAbschöpfBar'] ?? '') ?? 0,
                    'abschoepf_bar'               => MdbReader::parseFloat($row['AbschöpfBar'] ?? '') ?? 0,
                    'anz_ein_aus_zahlungen_bar'   => MdbReader::parseInt($row['AnzEinAusZahlungenBar'] ?? '') ?? 0,
                    'ein_aus_zahlungen_bar'       => MdbReader::parseFloat($row['EinAusZahlungenBar'] ?? '') ?? 0,
                    'ertrag'                      => MdbReader::parseFloat($row['Ertrag'] ?? ''),
                    'anz_rabatt'                  => MdbReader::parseInt($row['AnzRabatt'] ?? '') ?? 0,
                    'rabattbetrag'                => MdbReader::parseFloat($row['Rabattbetrag'] ?? '') ?? 0,
                    'anz_karte'                   => MdbReader::parseInt($row['AnzKarte'] ?? '') ?? 0,
                    'kartenbetrag'                => MdbReader::parseFloat($row['Kartenbetrag'] ?? '') ?? 0,
                    'barbetrag'                   => MdbReader::parseFloat($row['Barbetrag'] ?? ''),
                    'anz_belege'                  => MdbReader::parseInt($row['AnzBelege'] ?? '') ?? 0,
                    'belegbetrag'                 => MdbReader::parseFloat($row['Belegbetrag'] ?? ''),
                    'storno_belege'               => MdbReader::parseFloat($row['StornoBelege'] ?? '') ?? 0,
                    'storno_karte'                => MdbReader::parseFloat($row['StornoKarte'] ?? '') ?? 0,
                    'storno_scheck'               => MdbReader::parseFloat($row['StornoScheck'] ?? '') ?? 0,
                    'uebername_in_fibu'           => $row['ÜbernahmeInFibu'] !== '' && $row['ÜbernahmeInFibu'] !== null,
                    'created_at'                  => now(),
                    'updated_at'                  => now(),
                ];
                $imported++;
                $yearImported++;
                $existingDates[$datum] = $datum; // prevent duplicates in same run

                if (count($batch) >= self::BATCH) {
                    $this->flush('primeur_cash_daily', $batch);
                    $batch = [];
                }
            }

            if ($batch) {
                $this->flush('primeur_cash_daily', $batch);
            }

            $this->recordSourceFile($run, $csv, 'annual_summary', null, $yearImported);
            $this->line("  {$year}: {$yearImported} Tage importiert");
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    // ── Kassenbelege (01YYMMDD.mdb) ───────────────────────────────────────

    public function importCashReceipts(PrimeurImportRun $run, ?int $onlyYear = null): array
    {
        $years    = $onlyYear ? [$onlyYear] : range(2015, 2024);
        $imported = 0;
        $skipped  = 0;

        foreach ($years as $year) {
            $dir = self::RAW_BASE . "/{$year}";
            if (! is_dir($dir)) {
                continue;
            }

            // Only daily cash files: pattern 01YYMMDD.mdb
            $files = glob("{$dir}/01*.mdb");
            if (! $files) {
                continue;
            }

            $this->line("  Jahr {$year}: " . count($files) . " Tagesdateien");

            foreach ($files as $mdb) {
                $fileName = pathinfo($mdb, PATHINFO_FILENAME);

                // Parse date from filename: 01YYMMDD
                $dateStr = $this->parseDateFromFilename($fileName);

                // Check if already imported
                if (! $this->force && PrimeurCashReceipt::where('source_file', $fileName)->exists()) {
                    $skipped++;
                    continue;
                }

                if (! MdbReader::tableExists($mdb, 'Tb_BelegHaupt')) {
                    continue;
                }

                $batchH = [];
                $fileImported = 0;
                $receiptMap = []; // source_record_id → inserted DB id

                // Delete existing if force
                if ($this->force) {
                    PrimeurCashReceipt::where('source_file', $fileName)->delete();
                }

                foreach (MdbReader::rows($mdb, 'Tb_BelegHaupt') as $row) {
                    $srcId = MdbReader::parseInt($row['RecordID'] ?? '');
                    if (! $srcId) {
                        continue;
                    }

                    $datum    = MdbReader::parseDate($row['Datum'] ?? '') ?? $dateStr;
                    $status   = MdbReader::parseInt($row['Belegstatus'] ?? '1');
                    $istStorno = ($status === 0) || MdbReader::parseStr($row['Stornobenutzer'] ?? '') !== null;

                    $batchH[] = [
                        'source_file'      => $fileName,
                        'source_record_id' => $srcId,
                        'datum'            => $datum,
                        'belegnummer'      => MdbReader::parseInt($row['Belegnummer'] ?? ''),
                        'sitzungs_id'      => MdbReader::parseInt($row['SitzungsID'] ?? ''),
                        'kassen_nr'        => MdbReader::parseInt($row['KassenNrKassiert'] ?? ''),
                        'kunden_id'        => MdbReader::parseInt($row['KundenID'] ?? ''),
                        'preisgruppe'      => MdbReader::parseStr($row['Preisgruppe'] ?? '', 30),
                        'belegstatus'      => $status,
                        'belegtext'        => MdbReader::parseStr($row['Belegtext'] ?? '', 40),
                        'kartenart'        => MdbReader::parseInt($row['Kartenart'] ?? ''),
                        'ist_storno'       => $istStorno,
                        'gesamtbetrag'     => MdbReader::parseFloat($row['Gesamtbetrag'] ?? ''),
                        'pfandeinnahmen'   => MdbReader::parseFloat($row['Pfandeinnahmen'] ?? ''),
                        'pfandausgaben'    => MdbReader::parseFloat($row['Pfandausgaben'] ?? ''),
                        'bar_gegeben'      => MdbReader::parseFloat($row['BarGegeben'] ?? ''),
                        'scheckbetrag'     => MdbReader::parseFloat($row['Scheckbetrag'] ?? ''),
                        'gesamtertrag'     => MdbReader::parseFloat($row['Gesamtertrag'] ?? ''),
                        'belegrabatt'      => MdbReader::parseFloat($row['Belegrabatt'] ?? ''),
                        'kartenzahlung'    => MdbReader::parseFloat($row['Kartenzahlung'] ?? ''),
                        'barbetrag'        => MdbReader::parseFloat($row['Barbetrag'] ?? ''),
                        'mwst_betrag_1'    => MdbReader::parseFloat($row['MwstBetrag1'] ?? ''),
                        'mwst_betrag_2'    => MdbReader::parseFloat($row['MwstBetrag2'] ?? ''),
                        'mwst_satz_1'      => MdbReader::parseFloat($row['Mwstsatz1'] ?? ''),
                        'mwst_satz_2'      => MdbReader::parseFloat($row['Mwstsatz2'] ?? ''),
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];
                    $fileImported++;

                    if (count($batchH) >= self::BATCH) {
                        $this->flush('primeur_cash_receipts', $batchH);
                        $batchH = [];
                    }
                }

                if ($batchH) {
                    $this->flush('primeur_cash_receipts', $batchH);
                }

                // Now import items, linking to parent receipts
                if ($fileImported > 0 && MdbReader::tableExists($mdb, 'Tb_BelegArtikel')) {
                    $receiptMap = PrimeurCashReceipt::where('source_file', $fileName)
                        ->pluck('id', 'source_record_id')
                        ->all();

                    $batchI = [];
                    foreach (MdbReader::rows($mdb, 'Tb_BelegArtikel') as $row) {
                        $hauptId    = MdbReader::parseInt($row['HauptsatzID'] ?? '');
                        $receiptDbId = $receiptMap[$hauptId] ?? null;
                        if (! $receiptDbId) {
                            continue;
                        }

                        $batchI[] = [
                            'cash_receipt_id'       => $receiptDbId,
                            'source_record_id'      => MdbReader::parseInt($row['RecordID'] ?? ''),
                            'datum'                  => MdbReader::parseDate($row['Datum'] ?? '') ?? $dateStr,
                            'belegnummer'            => MdbReader::parseInt($row['Belegnummer'] ?? ''),
                            'artikel_id'             => MdbReader::parseInt($row['ArtikelID'] ?? ''),
                            'artikeleinheit'         => MdbReader::parseStr($row['Artikeleinheit'] ?? '', 1),
                            'artikel_bezeichnung'    => MdbReader::parseStr($row['ArtikelBezeichnung'] ?? '', 40),
                            'menge'                  => MdbReader::parseFloat($row['Menge'] ?? ''),
                            'vk_preis_regulaer'      => MdbReader::parseFloat($row['VKPreisRegulär'] ?? ''),
                            'vk_preis_tatsaechlich'  => MdbReader::parseFloat($row['VKPreisTatsächlich'] ?? ''),
                            'vk_preis'               => MdbReader::parseFloat($row['VKPreis'] ?? ''),
                            'vk_preis_aktion'        => MdbReader::parseFloat($row['VKPreisAktion'] ?? ''),
                            'vk_preis_rabatt'        => MdbReader::parseFloat($row['VKPreisRabatt'] ?? ''),
                            'pfandbetrag'            => MdbReader::parseFloat($row['Pfandbetrag'] ?? ''),
                            'mwst_satz'              => MdbReader::parseFloat($row['MwstSatz'] ?? ''),
                            'sonderverkauf'          => MdbReader::parseBool($row['Sonderverkauf'] ?? '0'),
                            'zugabe'                 => MdbReader::parseBool($row['Zugabe'] ?? '0'),
                            'aktion'                 => MdbReader::parseBool($row['Aktion'] ?? '0'),
                            'created_at'             => now(),
                            'updated_at'             => now(),
                        ];

                        if (count($batchI) >= self::BATCH) {
                            $this->flush('primeur_cash_receipt_items', $batchI);
                            $batchI = [];
                        }
                    }

                    if ($batchI) {
                        $this->flush('primeur_cash_receipt_items', $batchI);
                    }
                }

                $imported += $fileImported;
                $this->recordSourceFile($run, $mdb, 'daily_cash', $dateStr, $fileImported);
            }

            $this->line("  {$year} abgeschlossen.");
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    // ── Kassensitzungen (pre-exported CSV von Tb_Kas*.mdb) ────────────────

    public function importCashSessions(PrimeurImportRun $run, ?int $onlyYear = null): array
    {
        $years    = $onlyYear ? [$onlyYear] : range(2015, 2024);
        $imported = 0;
        $skipped  = 0;

        foreach ($years as $year) {
            $csv = self::EXPORTS_BASE . "/cash_sessions_{$year}.csv";
            if (! file_exists($csv)) {
                $this->line("  [SKIP] CSV nicht gefunden: {$csv}");
                continue;
            }

            $this->line("  Kassensitzungen {$year}...");
            $fileName = "sessions_{$year}";

            if ($this->force) {
                PrimeurCashSession::where('source_file', $fileName)->delete();
            }

            $existingIds = $this->force ? [] : PrimeurCashSession::where('source_file', $fileName)
                ->pluck('source_record_id')->flip()->all();

            $batch = [];

            foreach (MdbReader::csvRows($csv) as $row) {
                $srcId = MdbReader::parseInt($row['RecordID'] ?? '');
                if (! $srcId) {
                    $skipped++;
                    continue;
                }
                if (! $this->force && isset($existingIds[$srcId])) {
                    $skipped++;
                    continue;
                }

                // Actual column names from Tb_KassenSitzungen:
                // RecordID, AnlegUser, UpdateUser, AnlegTime, UpdateTime, HauptsatzID,
                // SitzungsID, MarktID, KassenNummer, Kassierer, Beginn, Ende,
                // Anfangsbestand, Umsatz, ...
                $batch[] = [
                    'source_record_id' => $srcId,
                    'source_file'      => $fileName,
                    'datum'            => MdbReader::parseDate($row['Beginn'] ?? ''), // date from session start
                    'kassen_nr'        => MdbReader::parseInt($row['KassenNummer'] ?? $row['KassenNr'] ?? ''),
                    'session_start'    => MdbReader::parseDateTime($row['Beginn'] ?? ''),
                    'session_end'      => MdbReader::parseDateTime($row['Ende'] ?? ''),
                    'benutzer'         => MdbReader::parseStr($row['Kassierer'] ?? $row['AnlegUser'] ?? '', 30),
                    'anfangsbestand'   => MdbReader::parseFloat($row['Anfangsbestand'] ?? ''),
                    'endbestand'       => null,
                    'kassenbestand'    => MdbReader::parseFloat($row['Geldzaehlung'] ?? $row['Bankeinreichung'] ?? ''),
                    'belegbetrag'      => MdbReader::parseFloat($row['Umsatz'] ?? ''),
                    'anzahl_belege'    => 0,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
                $imported++;

                if (count($batch) >= self::BATCH) {
                    $this->flush('primeur_cash_sessions', $batch);
                    $batch = [];
                }
            }

            if ($batch) {
                $this->flush('primeur_cash_sessions', $batch);
            }

            $this->recordSourceFile($run, $csv, 'annual_kasse', null, $imported);
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function flush(string $table, array $batch): void
    {
        if ($this->dryRun || empty($batch)) {
            return;
        }
        DB::table($table)->insertOrIgnore($batch);
    }

    private function recordSourceFile(
        PrimeurImportRun $run,
        string $filePath,
        string $sourceType,
        ?string $dataDate,
        int $count
    ): void {
        if ($this->dryRun) {
            return;
        }
        PrimeurSourceFile::create([
            'import_run_id'   => $run->id,
            'file_path'       => $filePath,
            'file_name'       => basename($filePath),
            'file_size'       => file_exists($filePath) ? filesize($filePath) : 0,
            'source_type'     => $sourceType,
            'data_date'       => $dataDate,
            'records_imported' => $count,
        ]);
    }

    private function parseDateFromFilename(string $fileName): ?string
    {
        // 01YYMMDD → 20YY-MM-DD
        if (preg_match('/^\d{2}(\d{2})(\d{2})(\d{2})$/', $fileName, $m)) {
            $year  = ((int) $m[1] > 30 ? '19' : '20') . $m[1];
            return "{$year}-{$m[2]}-{$m[3]}";
        }
        return null;
    }

    private function line(string $message): void
    {
        if (isset($this->output)) {
            $this->output->writeln($message);
        }
    }
}
