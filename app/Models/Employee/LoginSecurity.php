<?php

declare(strict_types=1);

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginSecurity extends Model
{
    protected $table = 'employee_login_security';

    protected $fillable = [
        'employee_id', 'failed_attempts', 'lockout_level', 'locked_until', 'last_attempt_at',
    ];

    protected $casts = [
        'locked_until'    => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function remainingSeconds(): int
    {
        if (! $this->isLocked()) {
            return 0;
        }
        return (int) now()->diffInSeconds($this->locked_until, false);
    }
}
