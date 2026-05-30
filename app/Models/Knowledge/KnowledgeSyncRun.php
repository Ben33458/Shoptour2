<?php

declare(strict_types=1);

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;

class KnowledgeSyncRun extends Model
{
    protected $table = 'knowledge_sync_runs';

    protected $fillable = [
        'tenant_id', 'source', 'status',
        'started_at', 'finished_at',
        'documents_seen', 'documents_indexed',
        'chunks_created', 'chunks_updated', 'chunks_deleted',
        'errors', 'error_log',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'error_log'   => 'array',
    ];

    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';
}
