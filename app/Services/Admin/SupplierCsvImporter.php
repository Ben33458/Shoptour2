<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Supplier\Supplier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Imports/updates suppliers from a CSV file.
 *
 * Identification key: lieferanten_nr (unique).
 * - If lieferanten_nr exists → update.
 * - Otherwise → create.
 *
 * Required column: lieferanten_nr
 * Optional columns: name, contact_name, email, phone, address, active
 */
class SupplierCsvImporter
{
    public const KNOWN_COLUMNS = [
        'lieferanten_nr',
        'name',
        'contact_name',
        'email',
        'phone',
        'address',
        'active',
    ];

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

                $nr       = $row['lieferanten_nr'];
                $existing = Supplier::where('lieferanten_nr', $nr)->first();

                $data = array_filter([
                    'name'         => $row['name']         ?? null,
                    'contact_name' => $row['contact_name'] ?? null,
                    'email'        => $row['email']        ?? null,
                    'phone'        => $row['phone']        ?? null,
                    'address'      => $row['address']      ?? null,
                    'active'       => isset($row['active'])
                        ? in_array(strtolower($row['active']), ['1', 'true', 'yes', 'ja', 'aktiv'], true)
                        : null,
                ], fn ($v) => $v !== null && $v !== '');

                if ($existing !== null) {
                    $existing->update($data);
                    $updated++;
                } else {
                    Supplier::create(array_merge($data, ['lieferanten_nr' => $nr]));
                    $created++;
                }
            }
        });

        $this->auditLog->log('csv.import.suppliers', null, [
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

        if (! in_array('lieferanten_nr', $headers, true)) {
            $errors[1][] = 'Spalte "lieferanten_nr" ist erforderlich.';
            return $errors;
        }

        foreach ($rows as $index => $row) {
            $lineNo    = $index + 2;
            $rowErrors = [];

            if (empty($row['lieferanten_nr'])) {
                $rowErrors[] = 'lieferanten_nr darf nicht leer sein.';
            }
            if (! empty($row['email']) && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = "E-Mail \"{$row['email']}\" ist ungültig.";
            }

            if (! empty($rowErrors)) {
                $errors[$lineNo] = $rowErrors;
            }
        }

        return $errors;
    }
}
