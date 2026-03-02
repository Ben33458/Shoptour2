<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Catalog\Brand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Imports/updates brands from a CSV file.
 *
 * Identification key: name (case-insensitive unique match).
 * - If name exists → no change (names are the full identity).
 * - Otherwise → create.
 *
 * Required column: name
 */
class BrandCsvImporter
{
    public const KNOWN_COLUMNS = ['name'];

    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function preview(UploadedFile $file): array
    {
        ['headers' => $headers, 'rows' => $rows] = $this->parseCsv($file);
        $preview = array_slice($rows, 0, 20);
        $errors  = $this->validate($rows, $headers);

        return compact('headers', 'rows', 'errors', 'preview');
    }

    public function import(string $filePath): array
    {
        ['headers' => $headers, 'rows' => $rows] = $this->parseCsvFromPath($filePath);

        $errors  = $this->validate($rows, $headers);
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

                $name     = $row['name'];
                $existing = Brand::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();

                if ($existing !== null) {
                    // Name is the only field — already identical, count as updated
                    $updated++;
                } else {
                    Brand::create(['name' => $name]);
                    $created++;
                }
            }
        });

        $this->auditLog->log('csv.import.brands', null, [
            'created' => $created, 'updated' => $updated, 'skipped' => $skipped,
        ]);

        return compact('created', 'updated', 'skipped', 'errors');
    }

    // -------------------------------------------------------------------------

    private function parseCsv(UploadedFile $file): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", file_get_contents($file->getRealPath()) ?? '');
        return $this->parseLines(explode("\n", trim($content)));
    }

    private function parseCsvFromPath(string $filePath): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", file_get_contents($filePath) ?? '');
        return $this->parseLines(explode("\n", trim($content)));
    }

    private function parseLines(array $lines): array
    {
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

    private function validate(array $rows, array $headers): array
    {
        $errors = [];

        if (! in_array('name', $headers, true)) {
            $errors[1][] = 'Spalte "name" ist erforderlich.';
            return $errors;
        }

        foreach ($rows as $index => $row) {
            $lineNo    = $index + 2;
            $rowErrors = [];

            if (empty($row['name'])) {
                $rowErrors[] = 'name darf nicht leer sein.';
            }

            if (! empty($rowErrors)) {
                $errors[$lineNo] = $rowErrors;
            }
        }

        return $errors;
    }
}
