<?php
declare(strict_types=1);
namespace App\Models\Rental;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Katalog buchbarer Mietartikel.
 *
 * inventory_mode:
 *   unit_based       = einzelne identifizierbare Geräte (Kühlanhänger, Zapfanlage)
 *   quantity_based   = Mengenbestand (Stehtische, Sonnenschirme)
 *   packaging_based  = VPE-pflichtig (Gläser)
 *   component_based  = Sets aus Komponenten (Garnituren)
 *
 * transport_class: small | normal | truck
 * billing_mode: per_rental_period (keine Tagesabrechnung)
 */
class RentalItem extends Model
{
    protected $fillable = [
        'company_id','article_number','name','slug','description','category_id',
        'active','visible_in_shop','requires_event_order','billing_mode','inventory_mode',
        'transport_class','allow_overbooking','price_on_request','damage_tariff_group_id','cleaning_fee_rule_id',
        'deposit_rule_id','preferred_time_model_id','total_quantity','unit_label','internal_notes',
    ];

    protected $casts = [
        'active' => 'boolean',
        'visible_in_shop' => 'boolean',
        'requires_event_order' => 'boolean',
        'allow_overbooking' => 'boolean',
        'price_on_request' => 'boolean',
    ];

    // Inventory mode constants
    public const MODE_UNIT = 'unit_based';
    public const MODE_QUANTITY = 'quantity_based';
    public const MODE_PACKAGING = 'packaging_based';
    public const MODE_COMPONENT = 'component_based';

    // Transport class constants
    public const TRANSPORT_SMALL = 'small';
    public const TRANSPORT_NORMAL = 'normal';
    public const TRANSPORT_TRUCK = 'truck';

    public function category(): BelongsTo
    {
        return $this->belongsTo(RentalItemCategory::class, 'category_id');
    }

    public function inventoryUnits(): HasMany
    {
        return $this->hasMany(RentalInventoryUnit::class);
    }

    public function packagingUnits(): HasMany
    {
        return $this->hasMany(RentalPackagingUnit::class);
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(RentalPriceRule::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(RentalComponent::class, 'parent_rental_item_id');
    }

    public function usedAsComponent(): HasMany
    {
        return $this->hasMany(RentalComponent::class, 'component_rental_item_id');
    }

    public function depositRule(): BelongsTo
    {
        return $this->belongsTo(DepositRule::class);
    }

    public function cleaningFeeRule(): BelongsTo
    {
        return $this->belongsTo(CleaningFeeRule::class);
    }

    public function preferredTimeModel(): BelongsTo
    {
        return $this->belongsTo(RentalTimeModel::class, 'preferred_time_model_id');
    }

    public function isUnitBased(): bool { return $this->inventory_mode === self::MODE_UNIT; }
    public function isQuantityBased(): bool { return $this->inventory_mode === self::MODE_QUANTITY; }
    public function isPackagingBased(): bool { return $this->inventory_mode === self::MODE_PACKAGING; }
    public function isComponentBased(): bool { return $this->inventory_mode === self::MODE_COMPONENT; }
}
