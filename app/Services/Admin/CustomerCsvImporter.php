<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Pricing\Customer;
use App\Models\Pricing\CustomerGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Imports/updates customers from a CSV file.
 *
 * Identification key: customer_number (unique).
 * - If customer_number exists → update allowed fields.
 * - Otherwise → create new customer.
 *
 * Required column: customer_number
 * Optional columns:
 *   name (mapped to first_name + last_name by splitting on first space),
 *   first_name, last_name, email, phone (ignored — no phone col),
 *   group (customer_group name or id), active,
 *   address_delivery (→ delivery_address_text), postal_code, city
 *   (postal_code + city appended to delivery_address_text if not standalone column)
 *
 * Returns ImportResult with per-row errors, counts.
 */
class CustomerCsvImporter
{
    /** Columns that this importer understands (lowercased). */
    public const KNOWN_COLUMNS = [
        'customer_number',
        'name',
        'first_name',
        'last_name',
        'email',
        'group',
        'active',
        'address_delivery',
        'postal_code',
        'city',
        'delivery_note',
    ];

    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * Parse and validate the CSV. Returns a preview + errors without writing to DB.
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
     * Execute the import (create or update customers).
     *
     * @param  string $filePath  Absolute path to the uploaded CSV file
     * @return array{created: int, updated: int, skipped: int, errors: array<int,string[]>}
     */
    public function import(string $filePath): array
    {
        $file = new \SplFileObject($filePath, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

        $headers  = null;
        $rows     = [];
        $rowIndex = 0;
        foreach ($file as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $rowIndex++;
            if ($headers === null) {
                $headers = array_map('trim', array_map('strtolower', $raw));
                continue;
            }
            if (count($raw) !== count($headers)) {
                continue;
            }
            $rows[] = array_combine($headers, array_map('trim', $raw));
        }

        $errors  = $this->validate($rows, $headers ?? []);
        $created = 0;
        $updated = 0;
        $skipped = 0;

        $defaultGroup = CustomerGroup::first();

        DB::transaction(function () use ($rows, $errors, $defaultGroup, &$created, &$updated, &$skipped): void {
            foreach ($rows as $lineNo => $row) {
                if (! empty($errors[$lineNo + 2])) {
                    $skipped++;
                    continue;
                }

                $customerNumber = $row['customer_number'];
                $existing       = Customer::where('customer_number', $customerNumber)->first();

                // Resolve group
                $groupId = $this->resolveGroupId($row['group'] ?? null, $defaultGroup);

                $data = array_filter([
                    'customer_group_id'    => $groupId,
                    'first_name'           => $this->resolveFirstName($row),
                    'last_name'            => $this->resolveLastName($row),
                    'email'                => $row['email'] ?? null,
                    'delivery_address_text' => $this->resolveAddress($row),
                    'delivery_note'        => $row['delivery_note'] ?? null,
                    'active'               => isset($row['active'])
                        ? in_array(strtolower($row['active']), ['1', 'true', 'yes', 'ja', 'aktiv'], true)
                        : null,
                ], fn ($v) => $v !== null && $v !== '');

                if ($existing !== null) {
                    $existing->update($data);
                    $updated++;
                } else {
                    Customer::create(array_merge($data, [
                        'customer_number' => $customerNumber,
                        'customer_group_id' => $groupId ?? $defaultGroup?->id ?? 1,
                    ]));
                    $created++;
                }
            }
        });

        $this->auditLog->log('csv.import.customers', null, [
            'file'    => basename($filePath),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors'  => count(array_filter($errors)),
        ]);

        return compact('created', 'updated', 'skipped', 'errors');
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /** @return array{headers: string[], rows: array<int,array<string,string>>} */
    private function parseCsv(UploadedFile $file): array
    {
        $content  = file_get_contents($file->getRealPath());
        // Normalize line endings
        $content  = str_replace(["\r\n", "\r"], "\n", $content ?? '');
        $lines    = explode("\n", trim($content));

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
     * Validate rows. Returns errors keyed by 1-based CSV line number (header = line 1).
     *
     * @param  array<int,array<string,string>> $rows
     * @param  string[] $headers
     * @return array<int,string[]>
     */
    private function validate(array $rows, array $headers): array
    {
        $errors = [];

        if (! in_array('customer_number', $headers, true)) {
            $errors[1][] = 'Spalte "customer_number" ist erforderlich.';

            return $errors;
        }

        foreach ($rows as $index => $row) {
            $lineNo    = $index + 2; // header = line 1
            $rowErrors = [];

            if (empty($row['customer_number'])) {
                $rowErrors[] = 'customer_number darf nicht leer sein.';
            }

            if (! empty($row['email']) && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = "E-Mail \"{$row['email']}\" ist ungültig.";
            }

            if (! empty($row['active']) && ! in_array(
                strtolower($row['active']),
                ['0', '1', 'true', 'false', 'yes', 'no', 'ja', 'nein', 'aktiv', 'inaktiv'],
                true
            )) {
                $rowErrors[] = "active \"{$row['active']}\" ist kein gültiger Wahrheitswert.";
            }

            if (! empty($rowErrors)) {
                $errors[$lineNo] = $rowErrors;
            }
        }

        return $errors;
    }

    private function resolveFirstName(array $row): ?string
    {
        if (! empty($row['first_name'])) {
            return $row['first_name'];
        }
        if (! empty($row['name'])) {
            return explode(' ', $row['name'], 2)[0];
        }

        return null;
    }

    private function resolveLastName(array $row): ?string
    {
        if (! empty($row['last_name'])) {
            return $row['last_name'];
        }
        if (! empty($row['name']) && str_contains($row['name'], ' ')) {
            return explode(' ', $row['name'], 2)[1];
        }

        return null;
    }

    private function resolveAddress(array $row): ?string
    {
        $parts = array_filter([
            $row['address_delivery'] ?? null,
            trim(($row['postal_code'] ?? '') . ' ' . ($row['city'] ?? '')),
        ]);

        return ! empty($parts) ? implode("\n", $parts) : null;
    }

    private function resolveGroupId(?string $groupHint, ?CustomerGroup $default): ?int
    {
        if (empty($groupHint)) {
            return $default?->id;
        }

        // Try by ID
        if (ctype_digit($groupHint)) {
            $group = CustomerGroup::find((int) $groupHint);
            if ($group !== null) {
                return $group->id;
            }
        }

        // Try by name (case-insensitive)
        $group = CustomerGroup::whereRaw('LOWER(name) = ?', [strtolower($groupHint)])->first();

        return $group?->id ?? $default?->id;
    }
}
