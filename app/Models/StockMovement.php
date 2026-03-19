<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lagerbewegung (Append-only Journal).
 *
 * movement_type: purchase_in | sale_out | correction |
 *                transfer_in | transfer_out | bundle_explosion
 *
 * @property int         $id
 * @property int         $product_id
 * @property int         $warehouse_id
 * @property string      $movement_type
 * @property float       $quantity_delta   positive = Zugang, negative = Abgang
 * @property string|null $reference_type
 * @property int|null    $reference_id
 * @property string|null $note
 * @property int|null    $created_by_user_id
 * @property \Carbon\Carbon $created_at
 */
class StockMovement extends Model
{
    // Append-only: no updated_at
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'movement_type',
        'quantity_delta',
        'reference_type',
        'reference_id',
        'note',
        'created_by_user_id',
    ];

    protected $casts = [
        'quantity_delta' => 'float',
        'created_at'     => 'datetime',
    ];

    // ── Movement type labels ───────────────────────────────────────────────────

    public const TYPES = [
        'purchase_in'      => 'Wareneingang',
        'sale_out'         => 'Verkauf',
        'correction'       => 'Korrektur',
        'transfer_in'      => 'Transfer (Ein)',
        'transfer_out'     => 'Transfer (Aus)',
        'bundle_explosion' => 'Gebinde-Auflösung',
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->movement_type] ?? $this->movement_type;
    }

    // ── Relations ──────────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
