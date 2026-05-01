<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class LexofficeArticle extends Model
{
    protected $fillable = [
        'company_id',
        'lexoffice_uuid',
        'version',
        'archived',
        'article_number',
        'title',
        'description',
        'unit_name',
        'type',
        'gtin',
        'price_net',
        'price_gross',
        'tax_rate_percent',
        'raw_json',
        'synced_at',
    ];

    protected $casts = [
        'archived'         => 'boolean',
        'raw_json'         => 'array',
        'synced_at'        => 'datetime',
        'tax_rate_percent' => 'decimal:2',
    ];
}
