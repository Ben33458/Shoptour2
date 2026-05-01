<?php
namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'employee_id', 'action', 'entity_type', 'entity_id', 'payload', 'ip_address', 'logged_at'];
    protected $casts = [
        'payload'   => 'array',
        'logged_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(\App\Models\User::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
