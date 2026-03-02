<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductBarcode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Imports/updates products from a CSV file.
 *
 * Identification key: artikelnummer (unique).
 * - Existing: update allowed fields + EANs.
 * - New: create product (requires at minimum: artikelnummer, produktname, base_price_gross_milli or base_price_net_milli).
 *
 * EAN handling:
 *   Column "ean" or "eans" — multiple EANs separated by | (pipe).
 *   First listed EAN becomes is_primary = true; rest is_primary = false.
 *   Existing EANs not in the new list are left untouched (no delete).
 *
 * Updatable fields:
 *   produktname, base_price_net_milli, base_price_gross_milli,
 *   active, ean/eans
 *
 * Milli-cent convention: columns named *_milli are stored as-is.
 * If the column provides a plain EUR value (no _milli suffix), it is multiplied
 * by 1,000,000 before storing.
 */
class ProductCsvImporter
{
    public const KNOWN_COLUMNS = [
        'artikelnummer',
        'produktname',
        'base_price_net_milli',
        'base_price_net_eur',
        'base_price_gross_milli',
        'base_price_gross_eur',
        'tax_rate',        // percent, e.g. "19" or "7"
        'active',
        'ean',
        'eans',
    ];

    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * Parse CSV and return preview + per-row validation errors without writing to DB.
     *
     * @return array{headers: string[], rows: array<int,array<string,string>>, errors: array<int,string[]>, preview: array<int,array<string,string>>}
     */
    public function preview(UploadedFile $file): array
    {
        ['headers' => $headers, 'rows' => $rows] = $this->parseCsv($file);
        $preview = array_slice($rows, 0, 20);
        $errors  = $this->validate($rows, $headers);

        return compact('headers', 'rows', 'errors', 'preview');
    }

    /**
     * Execute the product import.
     *
     * @param  string $filePath  Absolute path to stored CSV
     * @return array{created: int, updated: int, skipped: int, errors: array<int,string[]>}
     */
    public function import(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $content = str_replace(["\r\n", "\r"], "\n", $content ?? '');
        $lines   = explode("\n", trim($content));

        $headers = null;
        $rows    = [];

        foreach ($lines as $line) {
            $cols = str_getcsv($line);
            if ($headers === null) {
                $headers = array_map('trim', array_map('strtolower', $cols));
                continue;
            }
            if (count($cols) !== count($headers)) {
                continue;
            }
            $rows[] = array_combine($headers, array_map('trim', $cols));
        }

        $errors  = $this->validate($rows, $headers ?? []);
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $errors, &$created, &$updated, &$skipped): void {
            foreach ($rows as $index => $row) {
                $lineNo = $index + 2;

                if (! empty($errors[$lineNo])) {
                    $skipped++;
                    continue;
                }

                $artikelnummer = $row['artikelnummer'];
                $product       = Product::where('artikelnummer', $artikelnummer)->first();
                $data          = $this->buildData($row);

                if ($product !== null) {
                    $product->update($data);
                    $updated++;
                } else {
                    if (empty($row['produktname'])) {
                        $skipped++;
                        continue; // Cannot create without name
                    }
                    $data['artikelnummer'] = $artikelnummer;
                    $data['produktname']   = $row['produktname'];
                    $product = Product::create($data);
                    $created++;
                }

                // Handle EANs
                $eanRaw = $row['ean'] ?? $row['eans'] ?? null;
                if ($eanRaw !== null && $eanRaw !== '') {
                    $this->syncEans($product, $eanRaw);
                }
            }
        });

        $this->auditLog->log('csv.import.products', null, [
            'file'    => basename($filePath),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        return compact('created', 'updated', 'skipped', 'errors');
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /** @return array{headers: string[], rows: array<int,array<string,string>>} */
    private function parseCsv(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());
        $content = str_replace(["\r\n", "\r"], "\n", $content ?? '');
        $lines   = explode("\n", trim($content));

        $headers = null;
        $rows    = [];

        foreach ($lines as $line) {
            $cols = str_getcsv($line);
            if ($headers === null) {
                $headers = array_map('trim', array_map('strtolower', $cols));
                continue;
            }
            if (count($cols) !== count($headers)) {
                continue;
            }
            $rows[] = array_combine($headers, array_map('trim', $cols));
        }

        return ['headers' => $headers ?? [], 'rows' => $rows];
    }

    /**
     * @param  array<int,array<string,string>> $rows
     * @param  string[]                        $headers
     * @return array<int,string[]>
     */
    private function validate(array $rows, array $headers): array
    {
        $errors = [];

        if (! in_array('artikelnummer', $headers, true)) {
            $errors[1][] = 'Spalte "artikelnummer" ist erforderlich.';

            return $errors;
        }

        foreach ($rows as $index => $row) {
            $lineNo    = $index + 2;
            $rowErrors = [];

            if (empty($row['artikelnummer'])) {
                $rowErrors[] = 'artikelnummer darf nicht leer sein.';
            }

            foreach (['base_price_net_milli', 'base_price_gross_milli'] as $col) {
                if (! empty($row[$col]) && ! is_numeric($row[$col])) {
                    $rowErrors[] = "{$col} muss eine Zahl sein.";
                }
            }

            foreach (['base_price_net_eur', 'base_price_gross_eur'] as $col) {
                if (! empty($row[$col])) {
                    $val = str_replace(',', '.', $row[$col]);
                    if (! is_numeric($val)) {
                        $rowErrors[] = "{$col} muss eine Dezimalzahl sein (z.B. 12.50).";
                    }
                }
            }

            if (! empty($rowErrors)) {
                $errors[$lineNo] = $rowErrors;
            }
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    private function buildData(array $row): array
    {
        $data = [];

        if (! empty($row['produktname'])) {
            $data['produktname'] = $row['produktname'];
        }

        // Milli-cent columns (stored directly)
        foreach (['base_price_net_milli', 'base_price_gross_milli'] as $col) {
            if (isset($row[$col]) && $row[$col] !== '') {
                $data[$col] = (int) $row[$col];
            }
        }

        // EUR columns (convert × 1_000_000)
        if (isset($row['base_price_net_eur']) && $row['base_price_net_eur'] !== '') {
            $data['base_price_net_milli'] = (int) round(
                (float) str_replace(',', '.', $row['base_price_net_eur']) * 1_000_000
            );
        }
        if (isset($row['base_price_gross_eur']) && $row['base_price_gross_eur'] !== '') {
            $data['base_price_gross_milli'] = (int) round(
                (float) str_replace(',', '.', $row['base_price_gross_eur']) * 1_000_000
            );
        }

        if (isset($row['active']) && $row['active'] !== '') {
            $data['active'] = in_array(
                strtolower($row['active']),
                ['1', 'true', 'yes', 'ja', 'aktiv'],
                true
            );
        }

        return $data;
    }

    private function syncEans(Product $product, string $eanRaw): void
    {
        $eans = array_filter(array_map('trim', explode('|', $eanRaw)));

        foreach ($eans as $index => $ean) {
            $isPrimary = ($index === 0);

            $barcode = ProductBarcode::where('product_id', $product->id)
                ->where('barcode', $ean)
                ->first();

            if ($barcode !== null) {
                // Update primary flag if changed
                if ($barcode->is_primary !== $isPrimary) {
                    $barcode->update(['is_primary' => $isPrimary]);
                }
            } else {
                // Demote old primary if we are inserting a new one
                if ($isPrimary) {
                    ProductBarcode::where('product_id', $product->id)
                        ->where('is_primary', true)
                        ->update(['is_primary' => false]);
                }

                ProductBarcode::create([
                    'product_id'   => $product->id,
                    'barcode'      => $ean,
                    'barcode_type' => 'ean13',
                    'is_primary'   => $isPrimary,
                ]);
            }
        }
    }
}
