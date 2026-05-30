<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports the Bundesbank BLZ file (Bankleitzahlendatei) into bank_codes.
 *
 * Download from: https://www.bundesbank.de/de/aufgaben/unbarer-zahlungsverkehr/
 *                serviceangebot/bankleitzahlen/download-bankleitzahlen-602592
 *
 * Fixed-width format (columns 0-indexed):
 *   BLZ          0-7   (8 chars)
 *   Merkmal      8     (1 char) — 1=Hauptstelle, 2=Filiale, 3=Zahlungsabwicklung
 *   Bezeichnung  9-66  (58 chars)
 *   PLZ          67-71 (5 chars)
 *   Ort          72-106 (35 chars)
 *   Kurzbezeich. 107-133 (27 chars)
 *   PAN          134-138 (5 chars)
 *   BIC          139-149 (11 chars)
 */
class ImportBankCodes extends Command
{
    protected $signature   = 'bank-codes:import {file : Path to the Bundesbank BLZ fixed-width text file}';
    protected $description = 'Import Bundesbank BLZ-Datei into bank_codes table (Hauptstellen only)';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Cannot open file: {$path}");
            return self::FAILURE;
        }

        $rows    = [];
        $skipped = 0;

        while (($line = fgets($handle)) !== false) {
            // Work on raw bytes (ISO-8859-1) so substr() positions stay correct.
            // Multi-byte UTF-8 chars would shift all subsequent field offsets.
            if (strlen($line) < 150) {
                $skipped++;
                continue;
            }

            $merkmal = substr($line, 8, 1);
            if ($merkmal !== '1') {
                continue; // Only Hauptstellen
            }

            // Extract fields by byte position, then convert to UTF-8
            $blz      = trim(substr($line, 0, 8));
            $bankName = trim($this->toUtf8(substr($line, 9, 58)));
            $bic      = trim(substr($line, 139, 11));

            if ($blz === '' || $bankName === '') {
                continue;
            }

            $rows[] = [
                'blz'       => $blz,
                'bank_name' => $bankName,
                'bic'       => $bic !== '' ? $bic : null,
            ];
        }

        fclose($handle);

        if (empty($rows)) {
            $this->warn('No Hauptstellen found. Check file format.');
            return self::FAILURE;
        }

        // Upsert in batches of 500
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('bank_codes')->upsert($chunk, ['blz'], ['bank_name', 'bic']);
        }

        $this->info(count($rows) . ' Datensätze importiert (' . $skipped . ' Zeilen übersprungen).');

        return self::SUCCESS;
    }

    private function toUtf8(string $s): string
    {
        return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    }
}
