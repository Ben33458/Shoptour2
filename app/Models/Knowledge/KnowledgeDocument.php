<?php

declare(strict_types=1);

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeDocument extends Model
{
    protected $table = 'knowledge_documents';

    protected $fillable = [
        'tenant_id', 'source_id', 'source_type', 'external_id',
        'title', 'url', 'content_hash', 'tags', 'visibility', 'permissions',
        'is_price_list', 'is_lmiv', 'is_technical',
        'document_date', 'valid_from', 'valid_until',
        'source_category', 'metadata', 'indexed', 'source_updated_at', 'indexed_at',
    ];

    protected $casts = [
        'tags'               => 'array',
        'permissions'        => 'array',
        'metadata'           => 'array',
        'is_price_list'      => 'boolean',
        'is_lmiv'            => 'boolean',
        'is_technical'       => 'boolean',
        'indexed'            => 'boolean',
        'document_date'      => 'date',
        'valid_from'         => 'date',
        'valid_until'        => 'date',
        'source_updated_at'  => 'datetime',
        'indexed_at'         => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'source_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'document_id');
    }
}
