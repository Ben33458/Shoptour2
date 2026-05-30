<?php

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeQuery extends Model
{
    use HasFactory;

    protected $table = 'knowledge_queries';

    protected $fillable = [
        'tenant_id', 'user_id', 'question', 'answer',
        'question_type', 'used_internal_sources', 'used_live_data',
        'has_customer_data', 'has_invoice_data',
        'filter', 'source_count',
    ];

    protected $casts = [
        'used_internal_sources' => 'boolean',
        'used_live_data'        => 'boolean',
        'has_customer_data'     => 'boolean',
        'has_invoice_data'      => 'boolean',
    ];

    public const TYPE_KNOWLEDGE = 'knowledge';
    public const TYPE_DATA      = 'data';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(KnowledgeQuerySource::class, 'query_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(KnowledgeFeedback::class, 'query_id');
    }
}
