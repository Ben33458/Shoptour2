<?php

declare(strict_types=1);

namespace App\Services\Reconcile;

use App\Models\Employee\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Matches ninox_mitarbeiter records against the local employees table.
 * Uses source_matches (entity_type='employee') for tracking match state.
 * On confirm, also sets ninox_source_id / ninox_source_table on the employee.
 */
class EmployeeReconcileService
{
    private const ENTITY_TYPE = 'employee';
    private const SOURCE      = 'ninox';
    private const NINOX_TABLE = 'I';   // kehr table_id for Mitarbeiter

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Return proposals for all Ninox employees.
     *
     * Each proposal:
     *   ninox_id, source_data, candidate (Employee|null), confidence, rule, status
     *
     * @param array{status?: string, unmatched_only?: bool} $filters
     * @return list<array>
     */
    public function proposeMatches(array $filters = []): array
    {
        $ninoxEmployees = DB::table('ninox_mitarbeiter')->orderBy('ninox_id')->get();

        $localEmployees = Employee::all()->keyBy('id');

        // Ninox IDs already tracked (any status)
        $existingMatches = DB::table('source_matches')
            ->where('entity_type', self::ENTITY_TYPE)
            ->where('source', self::SOURCE)
            ->get()
            ->keyBy('source_id');

        // Local IDs already matched (auto or confirmed) — to avoid suggesting them twice
        $matchedLocalIds = DB::table('source_matches')
            ->where('entity_type', self::ENTITY_TYPE)
            ->where('source', self::SOURCE)
            ->whereIn('status', ['auto', 'confirmed'])
            ->pluck('local_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $proposals = [];

        // ── Kehr DB (primary) ─────────────────────────────────────────────
        foreach ($ninoxEmployees as $n) {
            $ninoxId = (string) $n->ninox_id;
            $match   = $existingMatches->get($ninoxId);
            $status  = $match?->status ?? 'unmatched';

            if (isset($filters['status']) && $status !== $filters['status']) {
                continue;
            }
            if (!empty($filters['unmatched_only']) && $status !== 'unmatched') {
                continue;
            }

            if ($match && $match->local_id) {
                $candidate  = $localEmployees->get((int) $match->local_id);
                $confidence = (int) ($match->confidence ?? 0);
                $rule       = $match->rule ?? null;
            } else {
                [$candidate, $confidence, $rule] = $this->findBestMatch(
                    $n,
                    $localEmployees,
                    $matchedLocalIds
                );
            }

            $proposals[] = [
                'ninox_id'    => $ninoxId,
                'source_data' => (array) $n,
                'candidate'   => $candidate,
                'confidence'  => $confidence,
                'rule'        => $rule,
                'status'      => $status,
                'source_db'   => 'kehr',
            ];
        }

        // ── Alt DB (secondary) — only employees not already in kehr DB ────
        // Employees confirmed from kehr DB have ninox_source_id set; skip those.
        $kehrNinoxIds = $ninoxEmployees->pluck('ninox_id')->map(fn ($v) => (string) $v)->all();
        $altDbId      = config('services.ninox.db_id_alt', 'fadrrq8poh9b');
        $altTrackedIds = DB::table('source_matches')
            ->where('entity_type', self::ENTITY_TYPE)
            ->where('source', 'ninox_alt')
            ->get()
            ->keyBy('source_id');

        $altRecs = DB::table('ninox_raw_records')
            ->where('db_id', $altDbId)
            ->where('table_id', 'D')
            ->where('is_latest', true)
            ->get(['ninox_id', 'record_data']);

        // Build kehr-DB name set to detect duplicates
        $kehrNames = $ninoxEmployees->map(function ($n) {
            return mb_strtolower(trim(($n->vorname ?? '') . ' ' . ($n->nachname ?? '')));
        })->all();

        foreach ($altRecs as $rec) {
            $d     = json_decode($rec->record_data, true) ?? [];
            $first = (string) ($d['Vorname'] ?? '');
            $last  = (string) ($d['Nachname'] ?? '');
            $full  = mb_strtolower(trim($first . ' ' . $last));

            // Skip if a kehr DB employee with the same name already exists
            if (in_array($full, $kehrNames, true)) {
                continue;
            }

            $altSrcId = 'alt-' . $rec->ninox_id;
            $match    = $altTrackedIds->get($altSrcId);
            $status   = $match?->status ?? 'unmatched';

            if (isset($filters['status']) && $status !== $filters['status']) {
                continue;
            }
            if (!empty($filters['unmatched_only']) && $status !== 'unmatched') {
                continue;
            }

            $stub = (object) ['vorname' => $first, 'nachname' => $last];
            if ($match && $match->local_id) {
                $candidate  = $localEmployees->get((int) $match->local_id);
                $confidence = (int) ($match->confidence ?? 0);
                $rule       = $match->rule ?? null;
            } else {
                [$candidate, $confidence, $rule] = $this->findBestMatch(
                    $stub,
                    $localEmployees,
                    $matchedLocalIds
                );
            }

            $proposals[] = [
                'ninox_id'    => $altSrcId,
                'source_data' => array_merge($d, ['_db' => 'alt', '_ninox_id' => $rec->ninox_id]),
                'candidate'   => $candidate,
                'confidence'  => $confidence,
                'rule'        => $rule,
                'status'      => $status,
                'source_db'   => 'alt',
            ];
        }

        return $proposals;
    }

    /**
     * Stats for the dashboard.
     *
     * @return array{total: int, unmatched: int, auto: int, confirmed: int, ignored: int}
     */
    public function stats(): array
    {
        $total = DB::table('ninox_mitarbeiter')->count();

        $counts = DB::table('source_matches')
            ->where('entity_type', self::ENTITY_TYPE)
            ->where('source', self::SOURCE)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $tracked  = $counts->sum();
        $auto      = (int) ($counts['auto']      ?? 0);
        $confirmed = (int) ($counts['confirmed'] ?? 0);
        $ignored   = (int) ($counts['ignored']   ?? 0);
        $unmatched = $total - $auto - $confirmed - $ignored;

        return compact('total', 'unmatched', 'auto', 'confirmed', 'ignored');
    }

    /**
     * Auto-match all unmatched Ninox employees whose best candidate
     * reaches >= $threshold confidence.
     * Each local employee can only be claimed once (first best-match wins).
     *
     * @return array{auto_matched: int, skipped: int}
     */
    public function autoMatchAll(int $threshold = 85): array
    {
        $proposals   = $this->proposeMatches(['unmatched_only' => true]);
        $autoMatched = 0;
        $skipped     = 0;
        $usedLocalIds = [];

        // Sort by confidence desc so best matches are processed first
        usort($proposals, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        foreach ($proposals as $p) {
            if (! $p['candidate'] || $p['confidence'] < $threshold) {
                $skipped++;
                continue;
            }

            $localId = (int) $p['candidate']->id;
            if (in_array($localId, $usedLocalIds, true)) {
                $skipped++;
                continue;
            }

            $this->saveMatch(
                $p['ninox_id'],
                $localId,
                'auto',
                null,
                $p['confidence'],
                $p['rule'],
                (array) $p['source_data'],
            );
            $usedLocalIds[] = $localId;
            $autoMatched++;
        }

        return ['auto_matched' => $autoMatched, 'skipped' => $skipped];
    }

    /**
     * Confirm a match between a Ninox employee and a local employee.
     * Updates the employee's ninox_source_id / ninox_source_table.
     */
    public function confirm(string $ninoxId, int $employeeId, int $userId): void
    {
        $ninox    = DB::table('ninox_mitarbeiter')->where('ninox_id', $ninoxId)->first();
        $employee = Employee::findOrFail($employeeId);

        $this->saveMatch(
            $ninoxId,
            $employeeId,
            'confirmed',
            $userId,
            100,
            'manual',
            $ninox ? (array) $ninox : [],
        );

        $employee->update([
            'ninox_source_id'    => (string) $ninoxId,
            'ninox_source_table' => self::NINOX_TABLE,
        ]);
    }

    /**
     * Confirm all auto matches.
     *
     * @return int  number of matches confirmed
     */
    public function confirmAllAuto(int $userId): int
    {
        $autoMatches = DB::table('source_matches')
            ->where('entity_type', self::ENTITY_TYPE)
            ->where('source', self::SOURCE)
            ->where('status', 'auto')
            ->get();

        $count = 0;
        foreach ($autoMatches as $m) {
            $employee = Employee::find($m->local_id);
            if (! $employee) {
                continue;
            }

            DB::table('source_matches')
                ->where('id', $m->id)
                ->update([
                    'status'       => 'confirmed',
                    'matched_by'   => $userId,
                    'confirmed_at' => now(),
                    'updated_at'   => now(),
                ]);

            $employee->update([
                'ninox_source_id'    => (string) $m->source_id,
                'ninox_source_table' => self::NINOX_TABLE,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Ignore a Ninox employee (won't be suggested again).
     */
    public function ignore(string $ninoxId, int $userId): void
    {
        DB::table('source_matches')->updateOrInsert(
            [
                'entity_type' => self::ENTITY_TYPE,
                'source'      => self::SOURCE,
                'source_id'   => $ninoxId,
            ],
            [
                'local_id'    => 0,
                'status'      => 'ignored',
                'matched_by'  => $userId,
                'confidence'  => null,
                'rule'        => null,
                'updated_at'  => now(),
                'created_at'  => now(),
            ]
        );
    }

    /**
     * Create a new employee from a Ninox record and confirm the match.
     * Also enriches from the alt Ninox DB when available.
     *
     * @return Employee  the newly created employee
     */
    public function createFrom(string $ninoxId, int $userId): Employee
    {
        $ninox = DB::table('ninox_mitarbeiter')->where('ninox_id', $ninoxId)->firstOrFail();

        // Try to get rich data from alt DB by name
        $altData = $this->findAltByName((string) ($ninox->vorname ?? ''), (string) ($ninox->nachname ?? ''));

        $employeeData = [
            'employee_number'    => $this->generateEmployeeNumber(),
            'first_name'         => $ninox->vorname ?? null,
            'last_name'          => $ninox->nachname ?? null,
            'nickname'           => $ninox->spitzname ?? null,
            'is_active'          => ($ninox->status ?? '') === 'Aktiv',
            'role'               => 'employee',
            'employment_type'    => 'part_time',
            'weekly_hours'       => 20,
            'vacation_days_per_year' => 20,
            'hire_date'          => now()->toDateString(),
            'onboarding_status'  => 'pending',
            'ninox_source_id'    => (string) $ninoxId,
            'ninox_source_table' => self::NINOX_TABLE,
        ];

        // Enrich from alt DB
        if ($altData) {
            $employeeData['ninox_alt_source_id'] = (string) $altData['_ninox_id'];

            $altMap = [
                'email'         => 'E-Mail',
                'phone'         => 'Telefon',
                'address_zip'   => 'PLZ',
                'address_city'  => 'Ort',
                'iban'          => 'IBAN',
                'clothing_size' => 'T-Shirt Größe',
                'shoe_size'     => 'Schuhgröße',
            ];
            foreach ($altMap as $local => $ninoxKey) {
                if (! empty($altData[$ninoxKey])) {
                    $employeeData[$local] = (string) $altData[$ninoxKey];
                }
            }
            $street = trim(($altData['Strasse'] ?? '') . ' ' . ($altData['Hausnummer'] ?? ''));
            if ($street !== '') {
                $employeeData['address_street'] = $street;
            }
            if (! empty($altData['Geburtsdatum'])) {
                $employeeData['birth_date'] = $altData['Geburtsdatum'];
            }
            if (! empty($altData['beschäftigt seit'])) {
                $employeeData['hire_date'] = $altData['beschäftigt seit'];
            }
            if (! empty($altData['Planstunden pro Woche'])) {
                $employeeData['weekly_hours'] = (int) $altData['Planstunden pro Woche'];
            }
            $typeMap = ['vollzeit' => 'full_time', 'teilzeit' => 'part_time', 'minijob' => 'mini_job'];
            $altType = mb_strtolower((string) ($altData['Art der Anstellung'] ?? ''));
            if (isset($typeMap[$altType])) {
                $employeeData['employment_type'] = $typeMap[$altType];
            }
        }

        $employee = Employee::create($employeeData);

        $this->saveMatch(
            $ninoxId,
            $employee->id,
            'confirmed',
            $userId,
            100,
            'created',
            (array) $ninox,
        );

        return $employee;
    }

    /**
     * Update an employee's fields from Ninox data (after confirmation).
     * Only fills fields that are empty on the local record.
     */
    public function syncFields(int $employeeId, string $ninoxId): void
    {
        $ninox    = DB::table('ninox_mitarbeiter')->where('ninox_id', $ninoxId)->first();
        $employee = Employee::findOrFail($employeeId);

        if (! $ninox) {
            return;
        }

        $updates = [];
        if (empty($employee->nickname) && ! empty($ninox->spitzname)) {
            $updates['nickname'] = $ninox->spitzname;
        }

        if (! empty($updates)) {
            $employee->update($updates);
        }
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    /**
     * Find the best matching local employee for a Ninox employee record.
     *
     * @return array{0: Employee|null, 1: int, 2: string|null}
     */
    private function findBestMatch(
        object $ninox,
        \Illuminate\Support\Collection $localEmployees,
        array $alreadyMatchedLocalIds
    ): array {
        $ninoxFirst = mb_strtolower(trim((string) ($ninox->vorname ?? '')));
        $ninoxLast  = mb_strtolower(trim((string) ($ninox->nachname ?? '')));
        $ninoxFull  = trim($ninoxFirst . ' ' . $ninoxLast);

        $bestEmployee   = null;
        $bestConfidence = 0;
        $bestRule       = null;

        foreach ($localEmployees as $emp) {
            if (in_array((int) $emp->id, $alreadyMatchedLocalIds, true)) {
                continue;
            }

            $localFirst = mb_strtolower(trim((string) ($emp->first_name ?? '')));
            $localLast  = mb_strtolower(trim((string) ($emp->last_name ?? '')));
            $localFull  = trim($localFirst . ' ' . $localLast);

            // Rule 1: exact full name
            if ($ninoxFull === $localFull && $ninoxFull !== '') {
                return [$emp, 100, 'exact-name'];
            }

            // Rule 2: last name exact + first name fuzzy
            if ($ninoxLast === $localLast && $ninoxLast !== '') {
                $firstSim = $this->similarity($ninoxFirst, $localFirst);
                // base 50 + up to 50 from first-name similarity
                $conf = (int) round(50 + $firstSim * 50);
                if ($conf > $bestConfidence) {
                    $bestConfidence = $conf;
                    $bestEmployee   = $emp;
                    $bestRule       = 'exact-last';
                }
                continue;
            }

            // Rule 3: full name fuzzy
            if ($ninoxFull !== '' && $localFull !== '') {
                $sim  = $this->similarity($ninoxFull, $localFull);
                $conf = (int) round($sim * 100);
                if ($conf > $bestConfidence && $conf >= 60) {
                    $bestConfidence = $conf;
                    $bestEmployee   = $emp;
                    $bestRule       = 'fuzzy-name';
                }
            }
        }

        return [$bestEmployee, $bestConfidence, $bestRule];
    }

    /**
     * Upsert a source_match record.
     */
    private function saveMatch(
        string  $ninoxId,
        int     $localId,
        string  $status,
        ?int    $matchedBy,
        int     $confidence,
        ?string $rule,
        array   $snapshot,
    ): void {
        $existing = DB::table('source_matches')
            ->where('entity_type', self::ENTITY_TYPE)
            ->where('source', self::SOURCE)
            ->where('source_id', $ninoxId)
            ->first();

        $data = [
            'entity_type'     => self::ENTITY_TYPE,
            'source'          => self::SOURCE,
            'source_id'       => $ninoxId,
            'local_id'        => $localId,
            'status'          => $status,
            'matched_by'      => $matchedBy,
            'confidence'      => $confidence,
            'rule'            => $rule,
            'source_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'confirmed_at'    => $status === 'confirmed' ? now() : null,
            'updated_at'      => now(),
        ];

        if ($existing) {
            DB::table('source_matches')->where('id', $existing->id)->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('source_matches')->insert($data);
        }
    }

    /**
     * String similarity as a value between 0.0 and 1.0.
     */
    private function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        similar_text($a, $b, $percent);
        return $percent / 100;
    }

    /**
     * Find a Mitarbeiter record in the alt Ninox DB by first + last name.
     * Returns the decoded record_data array with added _ninox_id key, or null.
     *
     * @return array<string,mixed>|null
     */
    public function findAltByName(string $firstName, string $lastName): ?array
    {
        $altDbId = config('services.ninox.db_id_alt', 'fadrrq8poh9b');
        $first   = mb_strtolower(trim($firstName));
        $last    = mb_strtolower(trim($lastName));

        if (! $first && ! $last) {
            return null;
        }

        $candidates = DB::table('ninox_raw_records')
            ->where('db_id', $altDbId)
            ->where('table_id', 'D')
            ->where('is_latest', true)
            ->get(['ninox_id', 'record_data']);

        // Exact match
        foreach ($candidates as $rec) {
            $d = json_decode($rec->record_data, true) ?? [];
            if (mb_strtolower(trim($d['Vorname'] ?? '')) === $first
                && mb_strtolower(trim($d['Nachname'] ?? '')) === $last) {
                $d['_ninox_id'] = $rec->ninox_id;
                return $d;
            }
        }

        // Fuzzy: last name exact + first name starts-with
        if (strlen($first) >= 4) {
            foreach ($candidates as $rec) {
                $d = json_decode($rec->record_data, true) ?? [];
                if (mb_strtolower(trim($d['Nachname'] ?? '')) === $last
                    && str_starts_with(mb_strtolower(trim($d['Vorname'] ?? '')), mb_substr($first, 0, 4))) {
                    $d['_ninox_id'] = $rec->ninox_id;
                    return $d;
                }
            }
        }

        // Fuzzy: first name exact + last name similar (Levenshtein ≤ 2, handles Huetter/Huther)
        foreach ($candidates as $rec) {
            $d      = json_decode($rec->record_data, true) ?? [];
            $rFirst = mb_strtolower(trim($d['Vorname'] ?? ''));
            $rLast  = mb_strtolower(trim($d['Nachname'] ?? ''));

            if ($rFirst === $first && $rLast !== '' && $last !== ''
                && levenshtein($rLast, $last) <= 2) {
                $d['_ninox_id'] = $rec->ninox_id;
                return $d;
            }
        }

        // Fuzzy: first name exact + last name is an abbreviation (≤2 chars = initial)
        if (mb_strlen($last) <= 2 && $last !== '') {
            foreach ($candidates as $rec) {
                $d      = json_decode($rec->record_data, true) ?? [];
                $rFirst = mb_strtolower(trim($d['Vorname'] ?? ''));
                $rLast  = mb_strtolower(trim($d['Nachname'] ?? ''));

                if ($rFirst === $first && str_starts_with($rLast, $last)) {
                    $d['_ninox_id'] = $rec->ninox_id;
                    return $d;
                }
            }
        }

        return null;
    }

    /**
     * Generate a unique employee number (MA-001, MA-002, …).
     */
    private function generateEmployeeNumber(): string
    {
        $max = Employee::withTrashed()
            ->where('employee_number', 'like', 'MA-%')
            ->orderByRaw('CAST(SUBSTRING(employee_number, 4) AS UNSIGNED) DESC')
            ->value('employee_number');

        if (! $max || ! preg_match('/MA-(\d+)/', $max, $m)) {
            return 'MA-001';
        }

        return 'MA-' . str_pad((string) ((int) $m[1] + 1), 3, '0', STR_PAD_LEFT);
    }
}
