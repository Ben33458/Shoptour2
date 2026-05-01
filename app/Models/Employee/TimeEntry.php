<?php
namespace App\Models\Employee;

use App\Enums\ComplianceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeEntry extends Model
{
    protected $fillable = [
        'shift_id', 'employee_id', 'clocked_in_at', 'clocked_out_at',
        'break_minutes', 'net_minutes', 'compliance_status', 'compliance_notes',
        'is_manual_correction', 'corrected_by',
    ];

    protected $casts = [
        'clocked_in_at'        => 'datetime',
        'clocked_out_at'       => 'datetime',
        'compliance_notes'     => 'array',
        'is_manual_correction' => 'boolean',
    ];

    public function shift(): BelongsTo { return $this->belongsTo(Shift::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function breakSegments(): HasMany { return $this->hasMany(BreakSegment::class); }

    public function getTotalMinutesAttribute(): int
    {
        if (!$this->clocked_out_at) return 0;
        return (int) $this->clocked_in_at->diffInMinutes($this->clocked_out_at);
    }
}
