<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a beverage brand (e.g. "Bitburger", "Coca-Cola").
 *
 * @property int         $id
 * @property string      $name
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductLine> $productLines
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product>     $products
 */
class Brand extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * All product lines belonging to this brand.
     */
    public function productLines(): HasMany
    {
        return $this->hasMany(ProductLine::class);
    }

    /**
     * All products directly associated with this brand.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
