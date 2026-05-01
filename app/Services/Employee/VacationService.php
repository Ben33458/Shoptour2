<?php
namespace App\Services\Employee;

use App\Models\Employee\Employee;
use App\Models\Employee\PublicHoliday;
use App\Models\Employee\VacationBalance;
use App\Models\Employee\VacationRequest;
use Carbon\Carbon;

class VacationService
{
    /**
     * Count working days between two dates.
     * Respects half-holidays (0.5 day deducted) and per-employee Saturday workdays.
     */
    public function countWorkingDays(string $startDate, string $endDate, ?Employee $employee = null): float
    {
        $holidays = PublicHoliday::whereBetween('date', [$startDate, $endDate])
            ->get()
            ->keyBy(fn($h) => Carbon::parse($h->date)->format('Y-m-d'));

        $workOnSaturdays = $employee?->work_on_saturdays ?? false;

        $current = Carbon::parse($startDate);
        $end     = Carbon::parse($endDate);
        $count   = 0.0;

        while ($current->lte($end)) {
            $dateStr = $current->format('Y-m-d');
            $isWeekend = $workOnSaturdays ? $current->isSunday() : $current->isWeekend();

            if (!$isWeekend) {
                if (isset($holidays[$dateStr])) {
                    $holiday = $holidays[$dateStr];
                    if ($holiday->is_half_day) {
                        $count += 0.5;
                    }
                    // Full holidays: don't count (add nothing)
                } else {
                    $count += 1.0;
                }
            }
            $current->addDay();
        }

        return $count;
    }

    public function getBalance(int $employeeId, int $year): VacationBalance
    {
        return VacationBalance::firstOrCreate(
            ['employee_id' => $employeeId, 'year' => $year],
            ['total_days' => 24, 'used_days' => 0, 'carried_over' => 0]
        );
    }

    public function approve(VacationRequest $request, int $approvedByUserId): void
    {
        $request->update([
            'status'         => 'approved',
            'approved_by'    => $approvedByUserId,
            'decided_at'     => now(),
        ]);
        // Deduct from balance
        $year    = $request->start_date->year;
        $balance = $this->getBalance($request->employee_id, $year);
        $balance->increment('used_days', $request->days_requested);
    }

    public function reject(VacationRequest $request, int $approvedByUserId, ?string $notes = null): void
    {
        $request->update([
            'status'         => 'rejected',
            'approved_by'    => $approvedByUserId,
            'decided_at'     => now(),
            'decision_notes' => $notes,
        ]);
    }
}
