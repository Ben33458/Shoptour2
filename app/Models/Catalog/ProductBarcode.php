<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scannable barcode assigned to a product.
 *
 * A product can carry multiple barcodes (e.g. EAN-13 for retail + ITF-14 for logistics),
 * but only one should be marked as primary (enforced at application layer).
 *
 * Barcodes may have a validity window to support product relabelling or
 * seasonal packaging variants without losing historical scan data.
 *
 * @property int              $id
 * @property int              $product_id
 * @property string           $barcode
 * @property string|null      $barcode_type   e.g. "EAN-13", "EAN-8", "UPC-A", "ITF-14", "QR"
 * @property bool             $is_primary
 * @property \Carbon\Carbon|null $valid_from
 * @property \Carbon\Carbon|null $valid_to
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 *
 * @property-read Product $product
 */
class ProductBarcode extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'barcode',
        'barcode_type',
        'is_primary',
        'valid_from',
        'valid_to',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'valid_from' => 'datetime',
        'valid_to'   => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The product this barcode identifies.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // -------------------------------------------------------------------------
    // Domain helpers
    // -------------------------------------------------------------------------

    /**
     * Returns true when this barcode is currently valid.
     */
    public function isActiveAt(\DateTimeInterface $at = null): bool
    {
        $at ??= now();

        if ($this->valid_from !== null && $this->valid_from->greaterThan($at)) {
            return false;
        }

        if ($this->valid_to !== null && $this->valid_to->lessThan($at)) {
            return false;
        }

        return true;
    }
}
