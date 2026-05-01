<?php
namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ShiftReport extends Model
{
    protected $fillable = [
        'shift_id', 'employee_id', 'report_date', 'summary', 'customer_count', 'cash_difference',
        'incident_level', 'incident_notes', 'is_submitted', 'submitted_at',
    ];

    protected $casts = [
        'report_date'     => 'date',
        'submitted_at'    => 'datetime',
        'is_submitted'    => 'boolean',
        'cash_difference' => 'decimal:2',
    ];

    public function shift(): BelongsTo { return $this->belongsTo(Shift::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }

    public function checklistItems(): BelongsToMany
    {
        return $this->belongsToMany(ChecklistItem::class, 'shift_report_checklist')
                    ->withPivot('is_checked', 'note')
                    ->withTimestamps();
    }
}
