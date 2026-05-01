<?php

declare(strict_types=1);

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SentEmployeeEmail extends Model
{
    protected $fillable = [
        'employee_id',
        'to_address',
        'subject',
        'type',
        'body_preview',
        'triggered_by',
        'sent_by_user_id',
        'status',
        'error_message',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
