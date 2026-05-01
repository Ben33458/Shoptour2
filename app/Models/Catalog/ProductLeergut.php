<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductLeergut extends Model
{
    protected $table = 'product_leergut';

    protected $fillable = [
        'company_id',
        'product_id',
        'leergut_art_nr',
        'leergut_name',
        'unit_price_net_milli',
        'unit_price_gross_milli',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
