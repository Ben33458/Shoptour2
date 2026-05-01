<?php
namespace App\Models\Employee;

use App\Enums\VacationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationRequest extends Model
{
    protected $fillable = [
        'employee_id', 'start_date', 'end_date', 'days_requested', 'status',
        'notes', 'approved_by', 'decided_at', 'decision_notes',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'decided_at'  => 'datetime',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function approver(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'approved_by'); }

    public function scopePending($query) { return $query->where('status', 'pending'); }
}
