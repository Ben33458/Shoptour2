<?php

declare(strict_types=1);

namespace App\Models\Events;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A task associated with an EventOccurrence.
 *
 * @property int         $id
 * @property int         $event_occurrence_id
 * @property string      $title
 * @property string|null $description
 * @property string      $status
 * @property \Carbon\Carbon|null $due_at
 * @property int|null    $assigned_user_id
 * @property int|null    $created_by_user_id
 * @property string      $task_type
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EventTask extends Model
{
    protected $table = 'event_tasks';

    protected $fillable = [
        'event_occurrence_id',
        'title',
        'description',
        'status',
        'due_at',
        'assigned_user_id',
        'created_by_user_id',
        'task_type',
    ];

    protected $casts = [
        'due_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
