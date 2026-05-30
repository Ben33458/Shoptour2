<?php

declare(strict_types=1);

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeChunk extends Model
{
    protected $table = 'knowledge_chunks';

    protected $fillable = [
        'tenant_id', 'document_id', 'qdrant_point_id',
        'chunk_index', 'chunk_text', 'chunk_hash', 'metadata', 'indexed_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'indexed_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }
}
