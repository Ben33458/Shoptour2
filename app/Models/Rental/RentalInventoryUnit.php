<?php
declare(strict_types=1);
namespace App\Models\Rental;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Konkrete identifizierbare Inventareinheit.
 * Beispiele: Kühlanhänger 1, Zapfanlage 2L-01
 *
 * status:
 *   available   = buchbar
 *   reserved    = für einen Auftrag reserviert
 *   in_use      = aktuell beim Kunden
 *   maintenance = in Wartung
 *   defective   = defekt, nicht verleihbar
 *   retired     = ausgemustert
 */
class RentalInventoryUnit extends Model
{
    protected $fillable = [
        'company_id','rental_item_id','inventory_number','serial_number','title',
        'status','condition_notes','location','preferred_for_booking','sync_source','sync_source_id',
    ];

    protected $casts = ['preferred_for_booking' => 'boolean'];

    public const STATUS_AVAILABLE   = 'available';
    public const STATUS_RESERVED    = 'reserved';
    public const STATUS_IN_USE      = 'in_use';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_DEFECTIVE   = 'defective';
    public const STATUS_RETIRED     = 'retired';

    public function rentalItem(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(RentalBookingAllocation::class);
    }

    public function assetIssues(): HasMany
    {
        return $this->hasMany(\App\Models\Assets\AssetIssue::class, 'asset_id')
            ->where('asset_type', 'rental_inventory_unit');
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }
}
