<?php
namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationBalance extends Model
{
    protected $fillable = ['employee_id', 'year', 'total_days', 'used_days', 'carried_over'];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }

    public function getRemainingDaysAttribute(): float
    {
        return (float)$this->total_days + (float)$this->carried_over - (float)$this->used_days;
    }
}
