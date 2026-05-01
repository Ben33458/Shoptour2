<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class LexofficePostingCategory extends Model
{
    protected $fillable = [
        'company_id',
        'lexoffice_uuid',
        'name',
        'type',
        'raw_json',
        'synced_at',
    ];

    protected $casts = [
        'raw_json'  => 'array',
        'synced_at' => 'datetime',
    ];
}
