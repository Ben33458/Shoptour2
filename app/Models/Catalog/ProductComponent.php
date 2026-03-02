<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line item linking a bundle product to one of its component products.
 *
 * The unique index on (parent_product_id, child_product_id) ensures each
 * component product appears at most once per bundle; qty handles the count.
 *
 * Example: A "Mixkasten" bundle product may contain:
 *   - 12× "Bitburger 0,33l Kasten" (child product)
 *   - 12× "Coca-Cola 0,33l Kasten" (child product)
 *
 * @property int    $id
 * @property int    $parent_product_id
 * @property int    $child_product_id
 * @property int    $qty
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Product $parentProduct
 * @property-read Product $childProduct
 */
class ProductComponent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'parent_product_id',
        'child_product_id',
        'qty',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'qty' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The bundle product that owns this component line.
     */
    public function parentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    /**
     * The individual product that is a component of the bundle.
     */
    public function childProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'child_product_id');
    }
}
