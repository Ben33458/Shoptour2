<?php
namespace App\Services\Employee;

use App\Models\Employee\BreakSegment;
use App\Models\Employee\TimeEntry;

class BreakCalculationService
{
    /**
     * German ArbZG break requirements:
     * Work > 6h → 30 min break required
     * Work > 9h → 45 min break required
     * Break segments < 15 min do not count legally.
     */
    public function calculateRequiredBreak(int $totalWorkMinutes): int
    {
        if ($totalWorkMinutes > 9 * 60) return 45;
        if ($totalWorkMinutes > 6 * 60) return 30;
        return 0;
    }

    /**
     * Calculate counted break minutes (only segments >= 15 min count).
     */
    public function countedBreakMinutes(array $breakSegments): int
    {
        $total = 0;
        foreach ($breakSegments as $seg) {
            $duration = $seg['duration_minutes'] ?? 0;
            if ($duration >= 15) {
                $total += $duration;
            }
        }
        return $total;
    }

    /**
     * Finalize a TimeEntry: compute break, net_minutes, compliance_status.
     * Returns array: ['break_minutes', 'net_minutes', 'compliance_status', 'compliance_notes']
     */
    public function finalize(TimeEntry $entry): array
    {
        if (!$entry->clocked_out_at) {
            return ['break_minutes' => 0, 'net_minutes' => null, 'compliance_status' => 'ok', 'compliance_notes' => []];
        }

        $totalMinutes  = $entry->total_minutes;
        $segments      = $entry->breakSegments()->get();
        $countedBreak  = 0;
        foreach ($segments as $seg) {
            if ($seg->duration_minutes !== null && $seg->duration_minutes >= 15) {
                $countedBreak += $seg->duration_minutes;
            }
        }

        $requiredBreak  = $this->calculateRequiredBreak($totalMinutes);
        $notes          = [];
        $status         = 'ok';

        if ($countedBreak < $requiredBreak) {
            $deduct = $requiredBreak;
            if ($requiredBreak > 0 && $countedBreak < $requiredBreak) {
                $notes[] = "Pflichtpause {$requiredBreak} min nicht vollständig genommen (genommen: {$countedBreak} min). Gesetzlich abgezogen.";
                $status  = 'warning';
            }
        } else {
            $deduct = $countedBreak;
        }

        $netMinutes = max(0, $totalMinutes - $deduct);

        // Breach: net work > 10h after break deduction
        if ($netMinutes > 10 * 60) {
            $notes[] = 'Nettoarbeitszeit überschreitet 10 Stunden (ArbZG § 3).';
            $status  = 'breach';
        }

        return [
            'break_minutes'    => $deduct,
            'net_minutes'      => $netMinutes,
            'compliance_status'=> $status,
            'compliance_notes' => $notes,
        ];
    }
}
