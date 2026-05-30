<?php

declare(strict_types=1);

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeSource extends Model
{
    protected $table = 'knowledge_sources';

    protected $fillable = [
        'tenant_id', 'source_type', 'name', 'base_url',
        'enabled', 'config', 'last_synced_at',
    ];

    protected $casts = [
        'config'         => 'array',
        'enabled'        => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public const TYPE_BOOKSTACK  = 'bookstack';
    public const TYPE_PAPERLESS  = 'paperless';
    public const TYPE_SHOPTOUR2  = 'shoptour2';
    public const TYPE_PRICE_LIST = 'price_list';
    public const TYPE_LMIV       = 'lmiv';
    public const TYPE_MANUAL     = 'manual';

    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class, 'source_id');
    }
}
