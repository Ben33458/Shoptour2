<?php

declare(strict_types=1);

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeQuerySource extends Model
{
    protected $table = 'knowledge_query_sources';

    protected $fillable = [
        'query_id', 'source_type', 'source_id', 'source_title',
        'source_url', 'chunk_id', 'score', 'snippet', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'score'    => 'float',
    ];

    public function knowledgeQuery(): BelongsTo
    {
        return $this->belongsTo(KnowledgeQuery::class, 'query_id');
    }
}
