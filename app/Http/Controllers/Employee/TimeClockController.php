<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\DevicePreference;
use App\Models\Employee\Employee;
use App\Models\Employee\Shift;
use App\Models\Employee\TimeEntry;
use App\Services\Employee\PinLockService;
use App\Services\Employee\TimeTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TimeClockController extends Controller
{
    public function __construct(
        private readonly TimeTrackingService $tracking,
        private readonly PinLockService $pinLock,
    ) {}

    public function index()
    {
        return view('employee.timeclock.index');
    }

    // ── Device preference endpoints ────────────────────────────────────────

    public function deviceInit(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|min:10|max:128']);

        $pref = DevicePreference::setType($data['token'], 'public');

        return response()->json(['device_type' => $pref->device_type]);
    }

    public function deviceGet(string $token): JsonResponse
    {
        $pref = DevicePreference::findByToken($token);

        if (!$pref) {
            return response()->json(['device_type' => 'public']);
        }

        $pref->update(['last_seen_at' => now()]);

        return response()->json(['device_type' => $pref->device_type]);
    }

    public function deviceSet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'       => 'required|string|min:10|max:128',
            'device_type' => 'required|in:public,private',
        ]);

        $pref = DevicePreference::setType($data['token'], $data['device_type']);

        return response()->json(['device_type' => $pref->device_type]);
    }

    // ── Authentication + status ────────────────────────────────────────────

    public function authenticate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_number' => 'required|string',
            'pin'             => 'required|digits:4',
            'device_token'    => 'nullable|string',
            'force_portal'    => 'nullable|boolean',
        ]);

        $employee = Employee::where('employee_number', $data['employee_number'])
            ->where('is_active', true)
            ->first();

        // Lockout-Check BEFORE PIN verification (avoids timing attacks)
        if ($employee && $this->pinLock->isLocked($employee)) {
            $info = $this->pinLock->getLockInfo($employee);
            $mins = (int) ceil($info['seconds'] / 60);
            return response()->json([
                'success' => false,
                'message' => "Zu viele Fehlversuche. Bitte warte noch {$mins} Minute(n).",
                'locked'  => true,
            ], 429);
        }

        if (!$employee || !Hash::check($data['pin'], $employee->pin_hash)) {
            if ($employee) {
                $newLock = $this->pinLock->recordFailure($employee, $request->ip());
                if ($newLock) {
                    $info = $this->pinLock->getLockInfo($employee);
                    $mins = (int) ceil($info['seconds'] / 60);
                    return response()->json([
                        'success' => false,
                        'message' => "Konto gesperrt für {$mins} Minute(n) nach zu vielen Fehlversuchen.",
                        'locked'  => true,
                    ], 429);
                }
            }
            return response()->json(['success' => false, 'message' => 'Mitarbeiternummer oder PIN ungültig.'], 401);
        }

        // Check employee can use timeclock (onboarding completed)
        if (! $employee->canUseTimeclock()) {
            return response()->json(['success' => false, 'message' => 'Dein Zugang ist noch nicht aktiviert.'], 403);
        }

        $this->pinLock->clearFailures($employee);

        // force_portal = direkt ins Mitarbeiterportal (ohne Gerätepräferenz)
        if (!empty($data['force_portal'])) {
            $request->session()->put('employee_id', $employee->id);
            return response()->json(['success' => true, 'redirect' => '/mein']);
        }

        // Check device type
        $deviceType = 'public';
        if (!empty($data['device_token'])) {
            $pref = DevicePreference::findByToken($data['device_token']);
            if ($pref) {
                $deviceType = $pref->device_type;
                $pref->update(['last_seen_at' => now()]);
            }
        }

        if ($deviceType === 'private') {
            $request->session()->put('employee_id', $employee->id);
            return response()->json(['success' => true, 'redirect' => '/mein']);
        }

        return response()->json(array_merge(['success' => true], $this->buildStatus($employee)));
    }

    // ── Action (clock in/out/break) ────────────────────────────────────────

    public function action(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_number' => 'required|string',
            'pin'             => 'required|digits:4',
            'action'          => 'required|in:clock_in,clock_out,break_start,break_end',
        ]);

        $employee = Employee::where('employee_number', $data['employee_number'])
            ->where('is_active', true)
            ->first();

        if ($employee && $this->pinLock->isLocked($employee)) {
            $mins = (int) ceil($this->pinLock->getLockInfo($employee)['seconds'] / 60);
            return response()->json(['success' => false, 'message' => "Konto gesperrt. Bitte warte {$mins} Minute(n).", 'locked' => true], 429);
        }

        if (!$employee || !Hash::check($data['pin'], $employee->pin_hash)) {
            if ($employee) {
                $this->pinLock->recordFailure($employee, $request->ip());
            }
            return response()->json(['success' => false, 'message' => 'Mitarbeiternummer oder PIN ungültig.'], 401);
        }

        if (! $employee->canUseTimeclock()) {
            return response()->json(['success' => false, 'message' => 'Zugang noch nicht aktiviert.'], 403);
        }

        $this->pinLock->clearFailures($employee);

        try {
            match ($data['action']) {
                'clock_in'    => $this->performClockIn($employee),
                'clock_out'   => $this->performClockOut($employee),
                'break_start' => $this->performBreakStart($employee),
                'break_end'   => $this->performBreakEnd($employee),
            };
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(array_merge(['success' => true], $this->buildStatus($employee)));
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function performClockIn(Employee $employee): void
    {
        $shift = Shift::where('employee_id', $employee->id)
            ->whereDate('planned_start', today())
            ->where('status', 'planned')
            ->orderBy('planned_start')
            ->first();

        if (!$shift) {
            $shift = Shift::create([
                'employee_id'   => $employee->id,
                'planned_start' => now(),
                'planned_end'   => now()->addHours(8),
                'status'        => 'active',
                'notes'         => 'Ad-hoc Stempelung',
            ]);
        } else {
            $shift->update(['status' => 'active', 'actual_start' => now()]);
        }

        $this->tracking->clockIn($employee, $shift);
    }

    private function performClockOut(Employee $employee): void
    {
        $entry = $this->tracking->getActiveEntry($employee);
        if (!$entry) {
            throw new \RuntimeException('Kein aktiver Stempeleintrag gefunden.');
        }
        $this->tracking->clockOut($entry);
    }

    private function performBreakStart(Employee $employee): void
    {
        $entry = $this->tracking->getActiveEntry($employee);
        if (!$entry) {
            throw new \RuntimeException('Kein aktiver Stempeleintrag.');
        }
        $this->tracking->startBreak($entry);
    }

    private function performBreakEnd(Employee $employee): void
    {
        $entry = $this->tracking->getActiveEntry($employee);
        if (!$entry) {
            throw new \RuntimeException('Kein aktiver Stempeleintrag.');
        }
        $openBreak = $entry->breakSegments()->whereNull('ended_at')->first();
        if (!$openBreak) {
            throw new \RuntimeException('Keine laufende Pause.');
        }
        $this->tracking->endBreak($openBreak);
    }

    private function buildStatus(Employee $employee): array
    {
        $entry = $this->tracking->getActiveEntry($employee);

        // Determine status
        if (!$entry) {
            $status = 'clocked_out';
            $clockedInAt = null;
        } elseif ($entry->breakSegments->whereNull('ended_at')->isNotEmpty()) {
            $status = 'on_break';
            $clockedInAt = $entry->clocked_in_at->format('H:i');
        } else {
            $status = 'active';
            $clockedInAt = $entry->clocked_in_at->format('H:i');
        }

        // Net minutes today (sum of all completed entries today + running entry)
        $netMinutesToday = TimeEntry::where('employee_id', $employee->id)
            ->whereDate('clocked_in_at', today())
            ->whereNotNull('clocked_out_at')
            ->sum('net_minutes');

        // Add running entry minutes (no break deduction for live display)
        if ($entry) {
            $netMinutesToday += $entry->clocked_in_at->diffInMinutes(now());
        }

        // Shift area
        $shiftArea = $entry?->shift?->shiftArea?->name ?? null;

        return [
            'name'              => $employee->full_name,
            'employee_number'   => $employee->employee_number,
            'net_minutes_today' => (int) $netMinutesToday,
            'status'            => $status,
            'clocked_in_at'     => $clockedInAt,
            'shift_area'        => $shiftArea,
        ];
    }
}
