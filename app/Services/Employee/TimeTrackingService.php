<?php
namespace App\Services\Employee;

use App\Models\Employee\BreakSegment;
use App\Models\Employee\Employee;
use App\Models\Employee\Shift;
use App\Models\Employee\TimeEntry;
use Illuminate\Support\Facades\Log;

class TimeTrackingService
{
    public function __construct(private readonly BreakCalculationService $breakCalc) {}

    public function clockIn(Employee $employee, Shift $shift): TimeEntry
    {
        // Ensure no open entry exists
        $existing = TimeEntry::where('employee_id', $employee->id)
            ->whereNull('clocked_out_at')
            ->first();
        if ($existing) {
            throw new \RuntimeException('Mitarbeiter ist bereits eingestempelt (Eintrag #' . $existing->id . ').');
        }

        return TimeEntry::create([
            'shift_id'    => $shift->id,
            'employee_id' => $employee->id,
            'clocked_in_at' => now(),
        ]);
    }

    public function clockOut(TimeEntry $entry): TimeEntry
    {
        if ($entry->clocked_out_at) {
            throw new \RuntimeException('Dieser Eintrag ist bereits ausgestempelt.');
        }

        $entry->update(['clocked_out_at' => now()]);

        // Close any open break segment
        $openBreak = $entry->breakSegments()->whereNull('ended_at')->first();
        if ($openBreak) {
            $this->endBreak($openBreak);
        }

        // Finalize compliance
        $entry->refresh();
        $result = $this->breakCalc->finalize($entry);
        $entry->update([
            'break_minutes'    => $result['break_minutes'],
            'net_minutes'      => $result['net_minutes'],
            'compliance_status'=> $result['compliance_status'],
            'compliance_notes' => $result['compliance_notes'],
        ]);

        return $entry;
    }

    public function startBreak(TimeEntry $entry): BreakSegment
    {
        $open = $entry->breakSegments()->whereNull('ended_at')->first();
        if ($open) {
            throw new \RuntimeException('Pause läuft bereits.');
        }
        return BreakSegment::create([
            'time_entry_id' => $entry->id,
            'started_at'    => now(),
        ]);
    }

    public function endBreak(BreakSegment $segment): BreakSegment
    {
        if ($segment->ended_at) {
            throw new \RuntimeException('Pause bereits beendet.');
        }
        $endedAt  = now();
        $duration = (int) $segment->started_at->diffInMinutes($endedAt);
        $segment->update([
            'ended_at'           => $endedAt,
            'duration_minutes'   => $duration,
            'counted_as_break'   => $duration >= 15,
        ]);
        return $segment;
    }

    public function getActiveEntry(Employee $employee): ?TimeEntry
    {
        return TimeEntry::where('employee_id', $employee->id)
            ->whereNull('clocked_out_at')
            ->with('breakSegments', 'shift')
            ->first();
    }
}
