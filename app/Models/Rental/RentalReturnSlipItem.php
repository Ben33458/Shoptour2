<?php
declare(strict_types=1);
namespace App\Models\Rental;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Position auf dem Rückgabeschein.
 *
 * damage_status:
 *   none                          = kein Schaden
 *   damaged                       = defekt, nicht verleihbar
 *   not_rentable                  = nicht verleihbar
 *   damaged_but_still_rentable    = defekt (Kleinigkeit), weiterhin verleihbar
 *
 * suggested_extra_charge_milli: aus Schadenstarif ermittelt
 * manual_extra_charge_milli: von Admin/Fahrer korrigiert
 */
class RentalReturnSlipItem extends Model
{
    protected $fillable = [
        'rental_return_slip_id','rental_booking_item_id','returned_quantity',
        'clean_status','damage_status','damage_tariff_id',
        'suggested_extra_charge_milli','manual_extra_charge_milli',
        'notes','photo_path',
    ];

    protected $casts = [
        'returned_quantity' => 'integer',
        'suggested_extra_charge_milli' => 'integer',
        'manual_extra_charge_milli' => 'integer',
    ];

    public function returnSlip(): BelongsTo
    {
        return $this->belongsTo(RentalReturnSlip::class);
    }

    public function bookingItem(): BelongsTo
    {
        return $this->belongsTo(RentalBookingItem::class);
    }

    public function damageTariff(): BelongsTo
    {
        return $this->belongsTo(DamageTariff::class);
    }

    public function effectiveChargeMillis(): int
    {
        return $this->manual_extra_charge_milli ?? $this->suggested_extra_charge_milli;
    }
}
