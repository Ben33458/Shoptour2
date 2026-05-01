<?php

declare(strict_types=1);

namespace App\Models\Supplier;

use App\Models\Communications\Communication;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Procurement\GoodsReceipt;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A vendor / supplier from whom we purchase goods.
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property string|null $lieferanten_nr
 * @property string      $name
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string      $currency      ISO 4217, default 'EUR'
 * @property bool        $active
 * @property string|null $bestelltag
 * @property string|null $liefertag
 * @property string|null $bestell_schlusszeit
 * @property string|null $lieferintervall   wöchentlich|14-tägig|nach_bedarf
 * @property int         $mindestbestellwert_netto_ek_milli
 * @property string      $kontrollstufe_default
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Company|null                               $company
 * @property-read Collection<int, SupplierProduct>           $supplierProducts
 * @property-read Collection<int, PurchaseOrder>             $purchaseOrders
 * @property-read Collection<int, SupplierOrderProfile>      $orderProfiles
 * @property-read Collection<int, GoodsReceipt>              $goodsReceipts
 */
class Supplier extends Model
{
    /** Warenlieferant — erscheint in Einkaufsbestellungen */
    const TYPE_SUPPLIER = 'supplier';
    /** Geschäftspartner (Krankenkassen, Tankstellen, etc.) — kein Warenlieferant */
    const TYPE_PARTNER  = 'partner';

    protected $fillable = [
        'company_id',
        'type',
        'lieferanten_nr',
        'wawi_lieferant_id',
        'lexoffice_contact_id',
        'ninox_lieferanten_id',
        'name',
        'contact_name',
        'email',
        'phone',
        'address',
        'currency',
        'active',
        'bestelltag',
        'liefertag',
        'bestell_schlusszeit',
        'lieferintervall',
        'mindestbestellwert_netto_ek_milli',
        'kontrollstufe_default',
        'po_filter_own_products',
    ];

    protected $casts = [
        'active'                 => 'boolean',
        'po_filter_own_products' => 'boolean',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<SupplierProduct> */
    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    /** @return HasMany<PurchaseOrder> */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * Ansprechpartner (multiple contacts) for this supplier.
     */
    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable')->orderBy('sort_order');
    }

    /**
     * All communications linked to this supplier.
     */
    public function communications(): MorphMany
    {
        return $this->morphMany(Communication::class, 'communicable')->orderByDesc('received_at');
    }

    /** @return HasMany<SupplierOrderProfile> */
    public function orderProfiles(): HasMany
    {
        return $this->hasMany(SupplierOrderProfile::class);
    }

    public function standardOrderProfile(): ?SupplierOrderProfile
    {
        return $this->orderProfiles()->where('ist_standard', true)->where('aktiv', true)->first();
    }

    /** @return HasMany<GoodsReceipt> */
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
