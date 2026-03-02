<?php

declare(strict_types=1);

namespace App\Models\Supplier;

use App\Models\Company;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A purchase order sent to a supplier.
 *
 * Status lifecycle: draft → sent → confirmed → received | cancelled
 *
 * po_number follows the format PO-YYYY-NNNNN and is assigned on creation.
 *
 * @property int              $id
 * @property int|null         $company_id
 * @property int              $supplier_id
 * @property int              $warehouse_id      Destination warehouse for goods receipt
 * @property string|null      $po_number
 * @property string           $status
 * @property \Carbon\Carbon|null $ordered_at
 * @property \Carbon\Carbon|null $expected_at
 * @property int              $total_milli
 * @property string|null      $notes
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 *
 * @property-read Company|null                     $company
 * @property-read Supplier                         $supplier
 * @property-read Warehouse                        $warehouse
 * @property-read Collection<int, PurchaseOrderItem> $items
 */
class PurchaseOrder extends Model
{
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SENT      = 'sent';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_RECEIVED  = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'warehouse_id',
        'po_number',
        'status',
        'ordered_at',
        'expected_at',
        'total_milli',
        'notes',
    ];

    protected $casts = [
        'total_milli' => 'integer',
        'ordered_at'  => 'date',
        'expected_at' => 'date',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return HasMany<PurchaseOrderItem> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    // =========================================================================
    // Domain helpers
    // =========================================================================

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
