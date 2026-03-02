<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Pricing\CustomerGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Imports/updates customer groups from a CSV file.
 *
 * Identification key: name (case-insensitive).
 * - If name exists → update allowed fields.
 * - Otherwise → create.
 *
 * Required column: name
 * Optional columns: active, is_business, is_deposit_exempt,
 *                   price_adjustment_type (none|fixed|percent),
 *                   price_adjustment_percent (e.g. 5.00 for 5%),
 *                   price_adjustment_fixed_eur (e.g. 1.50)
 */
class CustomerGroupCsvImporter
{
    public const KNOWN_COLUMNS = [
        'name',
        'active',
        'is_business',
        'is_deposit_exempt',
        'price_adjustment_type',
        'price_adjustment_percent',
        'price_adjustment_fixed_eur',
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

                $name     = $row['name'];
                $existing = CustomerGroup::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();

                $data = array_filter([
                    'active'             => isset($row['active'])
                        ? in_array(strtolower($row['active']), ['1', 'true', 'yes', 'ja', 'aktiv'], true)
                        : null,
                    'is_business'        => isset($row['is_business'])
                        ? in_array(strtolower($row['is_business']), ['1', 'true', 'yes', 'ja'], true)
                        : null,
                    'is_deposit_exempt'  => isset($row['is_deposit_exempt'])
                        ? in_array(strtolower($row['is_deposit_exempt']), ['1', 'true', 'yes', 'ja'], true)
                        : null,
                    'price_adjustment_type' => $row['price_adjustment_type'] ?? null,
                    'price_adjustment_percent_basis_points' => isset($row['price_adjustment_percent'])
                        ? (int) round((float) str_replace(',', '.', $row['price_adjustment_percent']) * 10000)
                        : null,
                    'price_adjustment_fixed_milli' => isset($row['price_adjustment_fixed_eur'])
                        ? (int) round((float) str_replace(',', '.', $row['price_adjustment_fixed_eur']) * 1_000_000)
                        : null,
                ], fn ($v) => $v !== null && $v !== '');

                if ($existing !== null) {
                    $existing->update($data);
                    $updated++;
                } else {
                    CustomerGroup::create(array_merge($data, ['name' => $name]));
                    $created++;
                }
            }
        });

        $this->auditLog->log('csv.import.customer_groups', null, [
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

        $validTypes = ['none', 'fixed', 'percent'];

        foreach ($rows as $index => $row) {
            $lineNo    = $index + 2;
            $rowErrors = [];

            if (empty($row['name'])) {
                $rowErrors[] = 'name darf nicht leer sein.';
            }

            if (! empty($row['price_adjustment_type']) &&
                ! in_array($row['price_adjustment_type'], $validTypes, true)) {
                $rowErrors[] = "price_adjustment_type muss none, fixed oder percent sein.";
            }

            if (! empty($rowErrors)) {
                $errors[$lineNo] = $rowErrors;
            }
        }

        return $errors;
    }
}
