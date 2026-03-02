<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical (or logical) storage location where stock is tracked.
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $location
 * @property bool        $active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Collection<int, ProductStock>   $productStocks
 * @property-read Collection<int, StockMovement>  $stockMovements
 */
class Warehouse extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'location',
        'active',
        'is_pickup_location',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'active'             => 'boolean',
        'is_pickup_location' => 'boolean',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * Current stock snapshots held in this warehouse.
     *
     * @return HasMany<ProductStock>
     */
    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    /**
     * All movement journal entries that reference this warehouse.
     *
     * @return HasMany<StockMovement>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
