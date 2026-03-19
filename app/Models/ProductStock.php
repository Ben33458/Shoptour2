<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aktueller Bestand eines Produkts in einem Lager (Snapshot-Zeile).
 *
 * @property int   $id
 * @property int   $product_id
 * @property int   $warehouse_id
 * @property float $quantity
 * @property float $reserved_quantity
 * @property \Carbon\Carbon $updated_at
 */
class ProductStock extends Model
{
    // Append-only pattern: no created_at, only updated_at
    public const UPDATED_AT = 'updated_at';
    public const CREATED_AT = null;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'reserved_quantity',
    ];

    protected $casts = [
        'quantity'          => 'float',
        'reserved_quantity' => 'float',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // ── Computed ───────────────────────────────────────────────────────────────

    /** Verfügbarer Bestand (Gesamt minus Reserviert) */
    public function getAvailableAttribute(): float
    {
        return $this->quantity - $this->reserved_quantity;
    }
}
