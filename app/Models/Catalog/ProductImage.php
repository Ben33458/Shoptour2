<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * WP-21 – A product image stored on the public disk.
 *
 * Multiple images per product are supported. sort_order determines display
 * sequence; the image with the lowest sort_order is treated as the main image.
 *
 * Files are stored via the "public" disk:
 *   Storage::disk('public')->put("products/{product_id}/{uuid}.jpg", $file)
 *   → accessible via /storage/products/{product_id}/{uuid}.jpg
 *
 * @property int         $id
 * @property int         $product_id
 * @property string      $path        Relative path on the public disk
 * @property int         $sort_order
 * @property string|null $alt_text
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Product $product
 */
class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'path',
        'sort_order',
        'alt_text',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the public URL for this image.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
