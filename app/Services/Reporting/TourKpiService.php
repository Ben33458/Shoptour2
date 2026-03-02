<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Delivery\Tour;
use App\Models\Delivery\TourStop;

/**
 * WP-16 - Tour KPI report (Tourenauswertung).
 *
 * Collects per-tour stop counts, skip rate and average stop duration
 * for all tours in the given date range that include at least one stop
 * belonging to the specified company.
 */
class TourKpiService
{
    /**
     * @return array{
     *   tour_count: int,
     *   total_stops: int,
     *   finished_stops: int,
     *   skipped_stops: int,
     *   rows: list<array<string, mixed>>
     * }
     */
    public function summary(int $companyId, string $from, string $to): array
    {
        $tours = Tour::whereBetween('tour_date', [$from, $to])
            ->with([
                'stops' => static function ($q): void {
                    $q->with('order:id,company_id');
                },
            ])
            ->orderBy('tour_date')
            ->get()
            ->filter(static function (Tour $tour) use ($companyId): bool {
                // Keep only tours that have at least one stop for this company
                return $tour->stops->contains(
                    static fn (TourStop $stop): bool => (int) ($stop->order?->company_id ?? 0) === $companyId
                );
            });

        $totalStops    = 0;
        $finishedTotal = 0;
        $skippedTotal  = 0;
        $rows          = [];

        foreach ($tours as $tour) {
            // Scope stops to this company only
            $stops = $tour->stops->filter(
                static fn (TourStop $stop): bool => (int) ($stop->order?->company_id ?? 0) === $companyId
            );

            $stopCount    = $stops->count();
            $finishedCnt  = $stops->where('status', TourStop::STATUS_FINISHED)->count();
            $skippedCnt   = $stops->where('status', TourStop::STATUS_SKIPPED)->count();

            // Average stop duration (arrived_at -> finished_at) for completed stops
            $durations = $stops
                ->where('status', TourStop::STATUS_FINISHED)
                ->filter(static fn (TourStop $s): bool => $s->arrived_at !== null && $s->finished_at !== null)
                ->map(static fn (TourStop $s): float => (float) $s->arrived_at->diffInSeconds($s->finished_at) / 60.0);

            $avgStopMin = $durations->count() > 0 ? round($durations->avg(), 1) : null;

            $totalStops    += $stopCount;
            $finishedTotal += $finishedCnt;
            $skippedTotal  += $skippedCnt;

            $rows[] = [
                'tour_id'        => $tour->id,
                'tour_date'      => $tour->tour_date->format('d.m.Y'),
                'status'         => $tour->status,
                'total_stops'    => $stopCount,
                'finished_stops' => $finishedCnt,
                'skipped_stops'  => $skippedCnt,
                'avg_stop_min'   => $avgStopMin,
            ];
        }

        return [
            'tour_count'     => $tours->count(),
            'total_stops'    => $totalStops,
            'finished_stops' => $finishedTotal,
            'skipped_stops'  => $skippedTotal,
            'rows'           => $rows,
        ];
    }
}
