<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * PROJ-4: Generate unique, sequential order numbers.
 *
 * Format: B + YYMMDD + 3-digit daily sequence
 * Example: B260302001 (first order on March 2, 2026)
 *
 * Uses the order_number_sequences table with SELECT FOR UPDATE
 * to prevent race conditions under concurrent checkout requests.
 */
class OrderNumberService
{
    /**
     * Generate the next order number for the given date.
     *
     * MUST be called inside a DB::transaction() — the SELECT FOR UPDATE
     * lock is released when the outer transaction commits.
     *
     * @param  Carbon|null  $date  Defaults to today
     * @return string              e.g. "B260302001"
     *
     * @throws RuntimeException when daily sequence exceeds 999
     */
    public function generate(?Carbon $date = null): string
    {
        $date = $date ?? Carbon::today();
        $dateStr = $date->format('Y-m-d');

        // Acquire an exclusive lock on the row for this date.
        // If no row exists yet, insert one with last_sequence = 0.
        $row = DB::table('order_number_sequences')
            ->where('date', $dateStr)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            DB::table('order_number_sequences')->insert([
                'date'          => $dateStr,
                'last_sequence' => 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $nextSeq = 1;
        } else {
            $nextSeq = $row->last_sequence + 1;
            DB::table('order_number_sequences')
                ->where('date', $dateStr)
                ->update([
                    'last_sequence' => $nextSeq,
                    'updated_at'    => now(),
                ]);
        }

        if ($nextSeq > 999) {
            throw new RuntimeException(
                "Daily order sequence limit exceeded for {$dateStr}. Maximum 999 orders per day."
            );
        }

        return sprintf('B%s%03d', $date->format('ymd'), $nextSeq);
    }
}
