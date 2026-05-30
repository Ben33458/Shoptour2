<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Models\Events\OfferNumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Generates sequential, year-based offer numbers in the format ST-A-YYYY-NNNNNN.
 *
 * Uses SELECT FOR UPDATE to prevent race conditions.
 */
class OfferNumberService
{
    /**
     * Generate the next offer number for the given year.
     *
     * Format: ST-A-{YYYY}-{6-digit zero-padded sequence}
     * Example: ST-A-2026-000001
     *
     * @param  int|null  $year  Defaults to current year
     * @return string           e.g. "ST-A-2026-000001"
     */
    public function generate(?int $year = null): string
    {
        $year = $year ?? now()->year;

        return DB::transaction(function () use ($year) {
            $seq = OfferNumberSequence::lockForUpdate()->firstOrCreate(
                ['year' => $year],
                ['last_sequence' => 0]
            );

            $seq->increment('last_sequence');
            $seq->refresh();

            return sprintf('ST-A-%d-%06d', $year, $seq->last_sequence);
        });
    }
}
