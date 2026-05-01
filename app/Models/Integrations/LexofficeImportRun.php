<?php

declare(strict_types=1);

namespace App\Models\Integrations;

use Illuminate\Database\Eloquent\Model;

class LexofficeImportRun extends Model
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE    = 'done';
    public const STATUS_FAILED  = 'failed';

    public $timestamps = false;

    protected $fillable = [
        'status',
        'started_at',
        'finished_at',
        'result_json',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'result_json' => 'array',
        'created_at'  => 'datetime',
    ];
}
