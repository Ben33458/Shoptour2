<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class LexofficeCountry extends Model
{
    protected $fillable = [
        'country_code',
        'country_name_de',
        'country_name_en',
        'tax_classification',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];
}
