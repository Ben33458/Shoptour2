<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lagerort / Warehouse
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $location
 * @property bool        $active
 * @property bool        $is_pickup_location
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Warehouse extends Model
{
    protected $fillable = [
        'name',
        'location',
        'active',
        'is_pickup_location',
    ];

    protected $casts = [
        'active'             => 'boolean',
        'is_pickup_location' => 'boolean',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
