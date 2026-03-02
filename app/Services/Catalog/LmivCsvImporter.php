<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Catalog\Product;
use Illuminate\Http\UploadedFile;

/**
 * WP-15 – Imports LMIV data from a CSV file.
 *
 * CSV format (semicolon-separated, UTF-8 with BOM accepted):
 *   Required column: artikelnummer
 *   Optional columns: any of LMIV_COLUMNS below
 *   The EAN column triggers an EAN change (and version rollover) when it differs
 *   from the currently active version's EAN.
 *
 * Import logic:
 *   1. For each row look up the product by artikelnummer.
 *   2. If not found → skip with error.
 *   3. If found but is_base_item = false → automatically mark as base item.
 *   4. Build the data_json from all known LMIV columns present in the CSV.
 *   5. If "ean" column is present and differs from active version → onEanChange().
 *   6. Otherwise → updateData().
 *   7. If no active version exists yet → create first version.
 */
class LmivCsvImporter
{
    /** Recognised LMIV field columns (maps CSV header → data_json key) */
    public const LMIV_COLUMNS = [
        'produktname',
        'hersteller',
        'herstelleranschrift',
        'nettofuellmenge',
        'alkoholgehalt',
        'zutaten',
        'allergene',
        'nw_energie_kj',
        'nw_energie_kcal',
        'nw_fett',
        'nw_fett_gesaettigt',
        'nw_kohlenhydrate',
        'nw_zucker',
        'nw_ballaststoffe',
        'nw_eiweiss',
        'nw_salz',
        'lagerhinweis',
        'herkunftsland',
        'zusatzinfos',
    ];

    public function __construct(
        private readonly LmivVersioningService $versioning,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Parse CSV and return a preview (first 20 rows) + per-row validation errors.
     *
     * @return array{headers: string[], rows: array<int,array<string,string>>, errors: array<int,string[]>, preview: array<int,array<string,string>>}
     */
    public function preview(UploadedFile $file): array
    {
        ['headers' => $headers, 'rows' => $rows] = $this->parseCsv($file);
        $preview = array_slice($rows, 0, 20);
        $errors  = $this->validateRows($rows, $headers);

        return compact('headers', 'rows', 'errors', 'preview');
    }

    /**
     * Execute the LMIV import.
     *
     * @param  string   $filePath     Absolute path to stored CSV
     * @param  int|null $actorUserId  Admin user triggering the import
     * @return array{updated: int, created: int, skipped: int, errors: array<int,string[]>}
     */
    public function import(string $filePath, ?int $actorUserId = null): array
    {
        $content  = (string) file_get_contents($filePath);
        $content  = str_replace(["\r\n", "\r"], "\n", $content);
        $lines    = explode("\n", trim($content));

        $headers  = null;
        $rows     = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $cols = str_getcsv($line, ';');
            if ($headers === null) {
                // Strip BOM
                $cols[0] = ltrim($cols[0], "\xEF\xBB\xBF");
                $headers = array_map('trim', $cols);
                continue;
            }
            if (count($cols) < count($headers)) {
                $cols = array_pad($cols, count($headers), '');
            }
            $rows[] = array_combine($headers, array_map('trim', $cols));
        }

        $updated = 0;
        $created = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2; // 1-indexed, +1 for header

            // ── Find product ──────────────────────────────────────────────
            $artikelnummer = trim($row['artikelnummer'] ?? '');
            if ($artikelnummer === '') {
                $errors[$rowNum][] = 'Artikelnummer fehlt.';
                $skipped++;
                continue;
            }

            $product = Product::where('artikelnummer', $artikelnummer)->first();
            if ($product === null) {
                $errors[$rowNum][] = "Produkt '{$artikelnummer}' nicht gefunden.";
                $skipped++;
                continue;
            }

            // ── Auto-mark as base item ─────────────────────────────────────
            if (! $product->is_base_item) {
                $product->update(['is_base_item' => true]);
            }

            // ── Build LMIV data from CSV ───────────────────────────────────
            $lmivData = $this->extractLmivData($row, $headers ?? []);

            // ── Handle EAN change / version rollover ───────────────────────
            $csvEan = trim($row['ean'] ?? '');

            try {
                $activeVersion = $product->activeLmivVersion;

                if ($csvEan !== '' && $activeVersion?->ean !== $csvEan) {
                    // EAN change → version rollover
                    $this->versioning->onEanChange(
                        product:      $product,
                        newEan:       $csvEan,
                        changeReason: 'CSV-Import',
                        overrideData: $lmivData ?: null,
                        actorUserId:  $actorUserId,
                    );

                    // Update data_json on new active version if lmivData was provided separately
                    if (! empty($lmivData)) {
                        // onEanChange already received overrideData — nothing more needed
                    }

                    $created++;
                } else {
                    // No EAN change — just update data
                    if (! empty($lmivData)) {
                        $version = $this->versioning->updateData($product, $lmivData, $actorUserId);
                        // Merge EAN if present and no rollover needed
                        if ($csvEan !== '' && $version->ean !== $csvEan) {
                            $version->ean = $csvEan;
                            $version->save();
                        }
                    }
                    $updated++;
                }
            } catch (\Throwable $e) {
                $errors[$rowNum][] = 'Fehler: ' . $e->getMessage();
                $skipped++;
            }
        }

        return compact('updated', 'created', 'skipped', 'errors');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * @param  UploadedFile $file
     * @return array{headers: string[], rows: array<int, array<string, string>>}
     */
    private function parseCsv(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath()) ?: '';
        $content = ltrim($content, "\xEF\xBB\xBF"); // Strip UTF-8 BOM
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines   = explode("\n", trim($content));

        $headers = null;
        $rows    = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $cols = str_getcsv($line, ';');
            if ($headers === null) {
                $headers = array_map('trim', $cols);
                continue;
            }
            if (count($cols) < count($headers)) {
                $cols = array_pad($cols, count($headers), '');
            }
            $rows[] = array_combine($headers, array_map('trim', $cols));
        }

        return ['headers' => $headers ?? [], 'rows' => $rows];
    }

    /**
     * Validate rows and return per-row errors (row number → list of errors).
     *
     * @param  array<int, array<string, string>> $rows
     * @param  string[]                          $headers
     * @return array<int, string[]>
     */
    private function validateRows(array $rows, array $headers): array
    {
        $errors = [];

        if (! in_array('artikelnummer', $headers, true)) {
            $errors[0][] = "Pflicht-Spalte 'artikelnummer' fehlt.";
            return $errors;
        }

        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2;
            if (trim($row['artikelnummer'] ?? '') === '') {
                $errors[$rowNum][] = 'Artikelnummer ist leer.';
            }
        }

        return $errors;
    }

    /**
     * Extract LMIV fields from a CSV row, keyed by their data_json names.
     *
     * @param  array<string, string> $row
     * @param  string[]              $headers
     * @return array<string, mixed>
     */
    private function extractLmivData(array $row, array $headers): array
    {
        $data = [];

        foreach (self::LMIV_COLUMNS as $col) {
            if (! in_array($col, $headers, true)) {
                continue;
            }
            $val = trim($row[$col] ?? '');
            if ($val !== '') {
                // Numeric fields
                if (str_starts_with($col, 'nw_') || $col === 'alkoholgehalt') {
                    $data[$col] = is_numeric($val) ? (float) $val : $val;
                } else {
                    $data[$col] = $val;
                }
            }
        }

        return $data;
    }
}
