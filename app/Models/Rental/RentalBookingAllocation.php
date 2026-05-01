<?php
declare(strict_types=1);
namespace App\Models\Rental;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zuordnung einer Buchungsposition auf eine konkrete Inventareinheit.
 * Für unit_based Mietartikel.
 */
class RentalBookingAllocation extends Model
{
    protected $fillable = [
        'rental_booking_item_id','rental_inventory_unit_id',
        'allocated_from','allocated_until','status',
    ];

    protected $casts = [
        'allocated_from' => 'datetime',
        'allocated_until' => 'datetime',
    ];

    public function bookingItem(): BelongsTo
    {
        return $this->belongsTo(RentalBookingItem::class);
    }

    public function inventoryUnit(): BelongsTo
    {
        return $this->belongsTo(RentalInventoryUnit::class, 'rental_inventory_unit_id');
    }
}
