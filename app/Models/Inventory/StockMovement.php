<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit journal of every stock change.
 *
 * Rules:
 *   - Rows are NEVER updated or deleted after creation.
 *   - quantity_delta > 0 means stock was added.
 *   - quantity_delta < 0 means stock was removed.
 *   - The current stock level is always the sum of all quantity_delta
 *     values for a (product_id, warehouse_id) pair — though for
 *     performance the snapshot is kept in product_stocks.
 *
 * Movement types:
 *   purchase_in      – goods received from supplier
 *   sale_out         – goods dispatched for a customer order
 *   correction       – manual correction by warehouse staff
 *   transfer_in      – stock received from another warehouse
 *   transfer_out     – stock sent to another warehouse
 *   bundle_explosion – child product deducted as part of a bundle sale
 *
 * @property int         $id
 * @property int         $product_id
 * @property int         $warehouse_id
 * @property string      $movement_type
 * @property float       $quantity_delta
 * @property string|null $reference_type
 * @property int|null    $reference_id
 * @property string|null $note
 * @property int|null    $created_by_user_id
 * @property \Carbon\Carbon $created_at
 *
 * @property-read Product   $product
 * @property-read Warehouse $warehouse
 */
class StockMovement extends Model
{
    // Append-only: no updated_at
    public const UPDATED_AT = null;

    // Allowed movement type constants — use these instead of raw strings
    public const TYPE_PURCHASE_IN      = 'purchase_in';
    public const TYPE_SALE_OUT         = 'sale_out';
    public const TYPE_CORRECTION       = 'correction';
    public const TYPE_TRANSFER_IN      = 'transfer_in';
    public const TYPE_TRANSFER_OUT     = 'transfer_out';
    public const TYPE_BUNDLE_EXPLOSION = 'bundle_explosion';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'movement_type',
        'quantity_delta',
        'reference_type',
        'reference_id',
        'note',
        'created_by_user_id',
        'korrekturgrund',
        'bestandsaufnahme_session_id',
        'mhd_batch_id',
        'employee_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'quantity_delta'      => 'float',
        'reference_id'        => 'integer',
        'created_by_user_id'  => 'integer',
        'created_at'          => 'datetime',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * @return BelongsTo<Product, StockMovement>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Warehouse, StockMovement>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
