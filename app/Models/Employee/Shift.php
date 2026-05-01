<?php
namespace App\Models\Employee;

use App\Enums\ShiftStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shift extends Model
{
    protected $fillable = [
        'employee_id', 'shift_area_id', 'planned_start', 'planned_end',
        'actual_start', 'actual_end', 'status', 'auto_closed_by_system', 'notes', 'created_by',
    ];

    protected $casts = [
        'planned_start'          => 'datetime',
        'planned_end'            => 'datetime',
        'actual_start'           => 'datetime',
        'actual_end'             => 'datetime',
        'auto_closed_by_system'  => 'boolean',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function shiftArea(): BelongsTo { return $this->belongsTo(ShiftArea::class); }
    public function timeEntries(): HasMany { return $this->hasMany(TimeEntry::class); }
    public function report(): HasOne { return $this->hasOne(ShiftReport::class); }
    public function tasks(): HasMany { return $this->hasMany(EmployeeTask::class); }

    public function scopePlanned($query) { return $query->where('status', 'planned'); }
    public function scopeActive($query) { return $query->where('status', 'active'); }

    public function getPlannedDurationMinutesAttribute(): int
    {
        return (int) $this->planned_start->diffInMinutes($this->planned_end);
    }
}
