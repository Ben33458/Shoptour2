<?php

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeDraft extends Model
{
    protected $table = 'knowledge_drafts';

    protected $fillable = [
        'tenant_id', 'gap_id', 'query_id', 'created_by',
        'title', 'content', 'bookstack_page_id',
        'bookstack_book_slug', 'status', 'error',
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PUSHED    = 'pushed';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED    = 'failed';

    public function gap(): BelongsTo
    {
        return $this->belongsTo(KnowledgeGap::class, 'gap_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
