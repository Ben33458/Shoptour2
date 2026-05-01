<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeFeedback extends Model
{
    protected $fillable = [
        'employee_id', 'category', 'subject', 'body', 'status', 'admin_note',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'bug'         => 'Fehler',
            'improvement' => 'Verbesserung',
            default       => 'Sonstiges',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'open'        => 'Offen',
            'in_progress' => 'In Bearbeitung',
            'done'        => 'Erledigt',
            'wontfix'     => 'Kein Handlungsbedarf',
            default       => $this->status,
        };
    }
}
