<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringTaskCompletion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ninox_task_id',
        'user_id',
        'employee_id',
        'note',
        'images',
        'next_due_date',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'images'       => 'array',
            'next_due_date' => 'date',
            'completed_at'  => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employeeModel(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Employee\Employee::class, 'employee_id');
    }

    public function completedByName(): string
    {
        if ($this->user) return $this->user->name ?? $this->user->first_name . ' ' . $this->user->last_name;
        if ($this->employeeModel) return $this->employeeModel->full_name;
        return '—';
    }

    /**
     * Calculate the next due date based on the ninox task recurrence rules.
     *
     * @param  object       $task        Row from ninox_77_regelmaessige_aufgaben
     * @param  Carbon       $completedAt When the task was completed
     * @param  string|null  $basisOverride  'from_completion'|'fixed_schedule'|null (from recurring_task_settings)
     */
    public static function calcNextDue(object $task, Carbon $completedAt, ?string $basisOverride = null): ?Carbon
    {
        $rule = $task->ab_wann_wiederholen ?? '';

        // No recurrence
        if (str_contains(strtolower($rule), 'nie')) {
            return null;
        }

        // Determine whether we go from completion or from the fixed schedule
        $fromCompletion = match($basisOverride) {
            'from_completion' => true,
            'fixed_schedule'  => false,
            default           => str_contains($rule, 'Erledigung'), // 'ab Erledigung' vs 'Fester Tag'
        };

        // Helper: advance a base date by the task interval once
        $addInterval = static function (Carbon $base) use ($task): ?Carbon {
            if (!empty($task->alle_x_tage))     return $base->copy()->addDays((int) $task->alle_x_tage);
            if (!empty($task->alle_x_wochen))   return $base->copy()->addWeeks((int) $task->alle_x_wochen);
            if (!empty($task->alle_x_monate))   return $base->copy()->addMonths((int) $task->alle_x_monate);
            if (!empty($task->alle_x_quartale)) return $base->copy()->addMonths((int) $task->alle_x_quartale * 3);
            if (!empty($task->alle_x_jahre))    return $base->copy()->addYears((int) $task->alle_x_jahre);
            return null;
        };

        if ($fromCompletion) {
            // Simple: one interval from completion date
            return $addInterval($completedAt);
        }

        // Fixed schedule: advance from original due date until we land in the future
        $currentDue = ($task->naechste_faelligkeit && $task->naechste_faelligkeit !== '')
            ? Carbon::parse($task->naechste_faelligkeit)->startOfDay()
            : $completedAt->copy();

        $next = $addInterval($currentDue);
        if ($next === null) {
            return null;
        }

        // For very overdue tasks keep advancing until the next occurrence is strictly in the future
        $today = $completedAt->copy()->startOfDay();
        $guard = 0;
        while ($next->lte($today) && $guard < 500) {
            $next = $addInterval($next);
            if ($next === null) return null;
            $guard++;
        }

        return $next;
    }
}
