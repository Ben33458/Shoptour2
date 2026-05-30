<?php

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeGap extends Model
{
    protected $table = 'knowledge_gaps';

    protected $fillable = [
        'tenant_id', 'user_id', 'query_id', 'question', 'comment',
        'status', 'bookstack_draft_id', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public const STATUS_OPEN          = 'open';
    public const STATUS_IN_PROGRESS   = 'in_progress';
    public const STATUS_DRAFT_CREATED = 'draft_created';
    public const STATUS_RESOLVED      = 'resolved';
    public const STATUS_IGNORED       = 'ignored';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function knowledgeQuery(): BelongsTo
    {
        return $this->belongsTo(KnowledgeQuery::class, 'query_id');
    }
}
