<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a hierarchical product category (e.g. "Bier" > "Helles" > "Märzen").
 * Categories support unlimited depth via the self-referential parent_id.
 *
 * @property int          $id
 * @property string       $name
 * @property int|null     $parent_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Category|null                                                   $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Category>        $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product>         $products
 */
class Category extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'parent_id',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The parent category. Null for root-level categories.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Direct child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * All products assigned to this category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
