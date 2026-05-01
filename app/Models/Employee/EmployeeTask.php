<?php
namespace App\Models\Employee;

use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeTask extends Model
{
    protected $fillable = [
        'shift_id', 'assigned_to', 'assigned_by', 'title', 'description', 'body', 'images',
        'status', 'priority', 'due_date', 'ninox_task_id', 'completed_at', 'completed_by',
        'parent_task_id', 'depends_on_task_id', 'timer_started_at', 'time_spent_seconds',
    ];

    protected $casts = [
        'due_date'          => 'date',
        'completed_at'      => 'datetime',
        'timer_started_at'  => 'datetime',
        'images'            => 'array',
    ];

    public function shift(): BelongsTo { return $this->belongsTo(Shift::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(Employee::class, 'assigned_to'); }
    public function completedByEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'completed_by'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_task_id'); }
    public function subtasks(): HasMany { return $this->hasMany(self::class, 'parent_task_id'); }
    public function dependsOn(): BelongsTo { return $this->belongsTo(self::class, 'depends_on_task_id'); }
    public function comments(): HasMany { return $this->hasMany(EmployeeTaskComment::class, 'task_id'); }

    public function scopeOpen($query) { return $query->where('status', 'open'); }
    public function scopeForEmployee($query, int $employeeId) { return $query->where('assigned_to', $employeeId); }
}
