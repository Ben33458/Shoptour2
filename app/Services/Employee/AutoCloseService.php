<?php
namespace App\Services\Employee;

use App\Models\Employee\TimeEntry;
use Illuminate\Support\Facades\Log;

class AutoCloseService
{
    public function __construct(private readonly BreakCalculationService $breakCalc) {}

    /**
     * Close time entries that have been open for more than 12h past planned shift end.
     */
    public function closeStale(): int
    {
        $closed = 0;
        $entries = TimeEntry::whereNull('clocked_out_at')
            ->with('shift')
            ->get();

        foreach ($entries as $entry) {
            $guard = $entry->shift
                ? $entry->shift->planned_end->addHours(12)
                : $entry->clocked_in_at->addHours(12);

            if (now()->isAfter($guard)) {
                $entry->update([
                    'clocked_out_at'        => $entry->shift?->planned_end ?? $entry->clocked_in_at->addHours(8),
                    'compliance_notes'       => ['Automatisch geschlossen durch System (12h Guard).'],
                    'compliance_status'      => 'warning',
                ]);
                if ($entry->shift) {
                    $entry->shift->update(['auto_closed_by_system' => true, 'status' => 'completed']);
                }
                $result = $this->breakCalc->finalize($entry->fresh());
                $entry->update([
                    'break_minutes' => $result['break_minutes'],
                    'net_minutes'   => $result['net_minutes'],
                ]);
                $closed++;
                Log::info("AutoClose: closed TimeEntry #{$entry->id} for Employee #{$entry->employee_id}");
            }
        }
        return $closed;
    }
}
