<?php
namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakSegment extends Model
{
    protected $fillable = ['time_entry_id', 'started_at', 'ended_at', 'duration_minutes', 'counted_as_break'];
    protected $casts = [
        'started_at'         => 'datetime',
        'ended_at'           => 'datetime',
        'counted_as_break'   => 'boolean',
    ];

    public function timeEntry(): BelongsTo { return $this->belongsTo(TimeEntry::class); }
}
