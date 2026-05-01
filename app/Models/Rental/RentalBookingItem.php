<?php
declare(strict_types=1);
namespace App\Models\Rental;
use App\Models\Orders\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Mietposition innerhalb eines Eventauftrags.
 *
 * Reservierungen blockieren sofort (status = unreviewed direkt nach Buchung).
 * Standard: keine Überbuchung. Nur artikelweise overridbar via allow_overbooking.
 *
 * unit_price_net_milli / total_price_net_milli: in Milli-Cent
 */
class RentalBookingItem extends Model
{
    protected $fillable = [
        'company_id','order_id','rental_item_id','packaging_unit_id','rental_time_model_id',
        'quantity','pieces_per_pack','total_pieces',
        'unit_price_net_milli','total_price_net_milli',
        'desired_specific_inventory_unit_id','fixed_inventory_unit_id',
        'status','notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'pieces_per_pack' => 'integer',
        'total_pieces' => 'integer',
        'unit_price_net_milli' => 'integer',
        'total_price_net_milli' => 'integer',
    ];

    public const STATUS_RESERVED  = 'reserved';
    public const STATUS_UNREVIEWED = 'unreviewed';
    public const STATUS_CONFIRMED  = 'confirmed';
    public const STATUS_REJECTED   = 'rejected';
    public const STATUS_CANCELLED  = 'cancelled';
    public const STATUS_EXPIRED    = 'expired';
    public const STATUS_DELIVERED  = 'delivered';
    public const STATUS_RETURNED   = 'returned';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function rentalItem(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class);
    }

    public function packagingUnit(): BelongsTo
    {
        return $this->belongsTo(RentalPackagingUnit::class);
    }

    public function timeModel(): BelongsTo
    {
        return $this->belongsTo(RentalTimeModel::class, 'rental_time_model_id');
    }

    public function desiredInventoryUnit(): BelongsTo
    {
        return $this->belongsTo(RentalInventoryUnit::class, 'desired_specific_inventory_unit_id');
    }

    public function fixedInventoryUnit(): BelongsTo
    {
        return $this->belongsTo(RentalInventoryUnit::class, 'fixed_inventory_unit_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(RentalBookingAllocation::class);
    }

    public function returnSlipItem(): HasOne
    {
        return $this->hasOne(\App\Models\Rental\RentalReturnSlipItem::class);
    }

    public function blocksAvailability(): bool
    {
        return in_array($this->status, [
            self::STATUS_UNREVIEWED,
            self::STATUS_RESERVED,
            self::STATUS_CONFIRMED,
            self::STATUS_DELIVERED,
        ]);
    }
}
