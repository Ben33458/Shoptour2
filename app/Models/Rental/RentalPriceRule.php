<?php
declare(strict_types=1);
namespace App\Models\Rental;
use App\Models\Pricing\CustomerGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preisregel je Mietartikel / Mietzeitmodell / Menge.
 * price_net_milli: Preis in Milli-Cent (1_000_000 = 1,00 €)
 */
class RentalPriceRule extends Model
{
    protected $fillable = [
        'company_id','rental_item_id','rental_time_model_id','packaging_unit_id',
        'min_quantity','max_quantity','price_type','price_net_milli',
        'valid_from','valid_until','customer_group_id','requires_drink_order',
    ];

    protected $casts = [
        'price_net_milli' => 'integer',
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'requires_drink_order' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function rentalItem(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class);
    }

    public function timeModel(): BelongsTo
    {
        return $this->belongsTo(RentalTimeModel::class, 'rental_time_model_id');
    }

    public function packagingUnit(): BelongsTo
    {
        return $this->belongsTo(RentalPackagingUnit::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    /** Price in euros */
    public function getPriceEurAttribute(): float
    {
        return $this->price_net_milli / 1_000_000;
    }
}
