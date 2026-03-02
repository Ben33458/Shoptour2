<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a product line within a brand (e.g. "Bitburger Premium Pils" under "Bitburger").
 * The combination of brand_id + name is unique.
 *
 * A ProductLine optionally defines the default Gebinde (packaging) and PfandSet (deposit set)
 * that products in this line use.
 *
 * @property int         $id
 * @property int         $brand_id
 * @property int|null    $gebinde_id
 * @property int|null    $pfand_set_id
 * @property string      $name
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Brand                                                        $brand
 * @property-read Gebinde|null                                                 $gebinde
 * @property-read PfandSet|null                                                $pfandSet
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product>      $products
 */
class ProductLine extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'brand_id',
        'gebinde_id',
        'pfand_set_id',
        'name',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The brand this product line belongs to.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * The default Gebinde (packaging type) for products in this line.
     */
    public function gebinde(): BelongsTo
    {
        return $this->belongsTo(Gebinde::class);
    }

    /**
     * The default PfandSet for products in this line.
     */
    public function pfandSet(): BelongsTo
    {
        return $this->belongsTo(PfandSet::class);
    }

    /**
     * All products under this product line.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
