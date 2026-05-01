<?php

declare(strict_types=1);

namespace App\Services\Employee;

use App\Models\Employee\Employee;
use App\Models\Employee\LoginSecurity;

/**
 * PIN lockout logic.
 *
 * Lockout schedule:
 *   - After  3 failures → 5 minutes
 *   - After  6 failures → 1 hour  (60 min)
 *   - After  9 failures → 60 * 12 = 720 min (12 h)
 *   - After 12 failures → 720 * 12 = 8640 min (6 d) … und so weiter
 */
class PinLockService
{
    private const ATTEMPTS_PER_LEVEL = 3;

    private const FIRST_LOCK_MINUTES  = 5;
    private const SECOND_LOCK_MINUTES = 60;

    public function __construct(private readonly SystemLogService $log) {}

    // ── Public API ────────────────────────────────────────────────────────────

    public function isLocked(Employee $employee): bool
    {
        return $this->security($employee)->isLocked();
    }

    /**
     * Return ['locked' => bool, 'seconds' => int, 'level' => int].
     */
    public function getLockInfo(Employee $employee): array
    {
        $sec = $this->security($employee);
        return [
            'locked'  => $sec->isLocked(),
            'seconds' => $sec->remainingSeconds(),
            'level'   => $sec->lockout_level,
        ];
    }

    /**
     * Record a failed PIN attempt.  Applies lockout if threshold reached.
     *
     * Returns true if a new lockout was applied.
     */
    public function recordFailure(Employee $employee, ?string $ip = null): bool
    {
        $sec = $this->security($employee);

        $sec->increment('failed_attempts');
        $sec->last_attempt_at = now();

        $newLockApplied = false;

        if ($sec->failed_attempts % self::ATTEMPTS_PER_LEVEL === 0) {
            $level = intdiv($sec->failed_attempts, self::ATTEMPTS_PER_LEVEL);
            $minutes = $this->lockMinutesForLevel($level);

            $sec->lockout_level = $level;
            $sec->locked_until  = now()->addMinutes($minutes);
            $newLockApplied     = true;

            $this->log->log('pin.locked', null, $employee->id, 'Employee', $employee->id, [
                'level'   => $level,
                'minutes' => $minutes,
                'ip'      => $ip,
            ]);
        } else {
            $this->log->log('pin.failed', null, $employee->id, 'Employee', $employee->id, [
                'attempts' => $sec->failed_attempts,
                'ip'       => $ip,
            ]);
        }

        $sec->save();

        return $newLockApplied;
    }

    /**
     * Clear failures after a successful login.
     */
    public function clearFailures(Employee $employee): void
    {
        $sec = $this->security($employee);

        if ($sec->failed_attempts > 0) {
            $sec->failed_attempts = 0;
            $sec->lockout_level   = 0;
            $sec->locked_until    = null;
            $sec->save();
        }

        $this->log->log('pin.login_success', null, $employee->id, 'Employee', $employee->id);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function security(Employee $employee): LoginSecurity
    {
        return LoginSecurity::firstOrCreate(['employee_id' => $employee->id]);
    }

    private function lockMinutesForLevel(int $level): int
    {
        if ($level <= 1) {
            return self::FIRST_LOCK_MINUTES;
        }
        if ($level === 2) {
            return self::SECOND_LOCK_MINUTES;
        }
        // Level 3+: previous * 12
        $minutes = self::SECOND_LOCK_MINUTES;
        for ($l = 3; $l <= $level; $l++) {
            $minutes *= 12;
        }
        return $minutes;
    }
}
