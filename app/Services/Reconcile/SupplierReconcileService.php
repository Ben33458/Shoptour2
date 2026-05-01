<?php

declare(strict_types=1);

namespace App\Services\Reconcile;

use App\Models\ReconcileFeedbackLog;
use App\Models\SourceMatch;
use App\Models\Supplier\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Matches external supplier records (Ninox) against local suppliers.
 *
 * Matching priority:
 *   1. Email exact match (case-insensitive)
 *   2. Name exact match (case-insensitive)
 *   3. Name fuzzy match (Levenshtein ≥ 80 %)
 *   4. No match → unlinked
 */
class SupplierReconcileService
{
    /**
     * Returns all Ninox supplier rows with match proposals.
     *
     * @return array{
     *   source: string,
     *   source_id: string,
     *   source_data: array,
     *   match: Supplier|null,
     *   confidence: int,
     *   match_method: string,
     *   diff: array,
     *   existing_match: SourceMatch|null
     * }[]
     */
    public function proposeMatches(string $source = 'ninox'): array
    {
        $rows = DB::table('ninox_lieferanten')->get()->all();

        $results = [];

        foreach ($rows as $row) {
            $sourceId   = (string) $row->ninox_id;
            $sourceData = (array) $row;

            $existing = SourceMatch::where('entity_type', SourceMatch::ENTITY_SUPPLIER)
                ->where('source', $source)
                ->where('source_id', $sourceId)
                ->first();

            [$supplier, $confidence, $method] = $this->findMatch($row);

            $diff = $supplier ? $this->detectDiff($sourceData, $supplier) : [];

            $results[] = [
                'source'         => $source,
                'source_id'      => $sourceId,
                'source_data'    => $sourceData,
                'match'          => $supplier,
                'confidence'     => $confidence,
                'match_method'   => $method,
                'diff'           => $diff,
                'existing_match' => $existing,
            ];
        }

        return $results;
    }

    /**
     * Confirm a match.
     */
    public function confirm(string $source, string $sourceId, int $supplierId, int $userId): SourceMatch
    {
        $supplier = Supplier::findOrFail($supplierId);

        $row = (array) DB::table('ninox_lieferanten')->where('ninox_id', $sourceId)->first();

        $diff = $this->detectDiff($row, $supplier);

        $match = SourceMatch::updateOrCreate(
            [
                'entity_type' => SourceMatch::ENTITY_SUPPLIER,
                'source'      => $source,
                'source_id'   => $sourceId,
            ],
            [
                'local_id'        => $supplierId,
                'status'          => SourceMatch::STATUS_CONFIRMED,
                'matched_by'      => $userId,
                'source_snapshot' => $row,
                'diff_at_match'   => $diff,
                'confirmed_at'    => now(),
            ]
        );

        $supplier->update(['ninox_lieferanten_id' => (int) $sourceId]);

        [, $confidence, $method] = $this->findMatchById($sourceId);
        ReconcileFeedbackLog::create([
            'entity_type'    => SourceMatch::ENTITY_SUPPLIER,
            'source'         => $source,
            'source_id'      => $sourceId,
            'action'         => 'confirmed',
            'user_id'        => $userId,
            'target_id'      => (string) $supplierId,
            'target_name'    => $supplier->name,
            'confidence'     => $confidence,
            'match_method'   => $method,
            'was_auto_match' => false,
        ]);

        return $match;
    }

    /**
     * Datensatz ablehnen.
     */
    public function ignore(string $source, string $sourceId, int $userId = 0): SourceMatch
    {
        $match = SourceMatch::updateOrCreate(
            [
                'entity_type' => SourceMatch::ENTITY_SUPPLIER,
                'source'      => $source,
                'source_id'   => $sourceId,
            ],
            [
                'local_id' => 0,
                'status'   => SourceMatch::STATUS_IGNORED,
            ]
        );

        [, $confidence, $method] = $this->findMatchById($sourceId);
        ReconcileFeedbackLog::create([
            'entity_type'    => SourceMatch::ENTITY_SUPPLIER,
            'source'         => $source,
            'source_id'      => $sourceId,
            'action'         => 'ignored',
            'user_id'        => $userId ?: null,
            'confidence'     => $confidence,
            'match_method'   => $method,
            'was_auto_match' => false,
        ]);

        return $match;
    }

    /**
     * Detect field differences between external Ninox data and local supplier.
     *
     * @return array<string, array{source: mixed, local: mixed}>
     */
    public function detectDiff(array $sourceData, Supplier $supplier): array
    {
        $diff = [];

        $this->compareDiff($diff, 'email', $sourceData['kontakt_e_mail'] ?? null, $supplier->email);
        $this->compareDiff($diff, 'name', $sourceData['name'] ?? null, $supplier->name);
        $this->compareDiff($diff, 'phone', $sourceData['telefon'] ?? null, $supplier->phone);

        return $diff;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @return array{0: Supplier|null, 1: int, 2: string}
     */
    private function findMatchById(string $sourceId): array
    {
        $row = DB::table('ninox_lieferanten')->where('ninox_id', $sourceId)->first();
        return $row ? $this->findMatch($row) : [null, 0, 'none'];
    }

    private function findMatch(object $row): array
    {
        // Priority 1: Email
        $email = $row->kontakt_e_mail ?? null;
        if ($email) {
            $supplier = Supplier::whereRaw('LOWER(email) = ?', [strtolower(trim((string) $email))])->first();
            if ($supplier) {
                return [$supplier, 95, 'email'];
            }
        }

        // Priority 2: Name exact
        $name = $row->name ?? null;
        if ($name) {
            $supplier = Supplier::whereRaw('LOWER(name) = ?', [strtolower(trim((string) $name))])->first();
            if ($supplier) {
                return [$supplier, 90, 'name_exact'];
            }
        }

        // Priority 3: Fuzzy name
        if ($name) {
            $best           = null;
            $bestConfidence = 0;

            Supplier::select(['id', 'name'])->chunk(200, function ($chunk) use ($name, &$best, &$bestConfidence): void {
                foreach ($chunk as $s) {
                    $confidence = $this->levenshteinPercent((string) $name, $s->name ?? '');
                    if ($confidence >= 80 && $confidence > $bestConfidence) {
                        $bestConfidence = $confidence;
                        $best           = $s;
                    }
                }
            });

            if ($best) {
                return [Supplier::find($best->id), $bestConfidence, 'fuzzy_name'];
            }
        }

        return [null, 0, 'none'];
    }

    private function levenshteinPercent(string $a, string $b): int
    {
        $a   = mb_strtolower(trim($a));
        $b   = mb_strtolower(trim($b));
        $max = max(mb_strlen($a), mb_strlen($b));

        if ($max === 0) {
            return 100;
        }

        return (int) round((1 - levenshtein($a, $b) / $max) * 100);
    }

    private function compareDiff(array &$diff, string $field, mixed $sourceValue, mixed $localValue): void
    {
        $s = $sourceValue !== null ? trim((string) $sourceValue) : null;
        $l = $localValue  !== null ? trim((string) $localValue)  : null;

        if ($s !== $l && ($s !== null && $s !== '') && ($l !== null && $l !== '')) {
            $diff[$field] = ['source' => $s, 'local' => $l];
        }
    }
}
