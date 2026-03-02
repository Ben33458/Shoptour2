<?php

declare(strict_types=1);

namespace App\Models\Supplier;

use App\Models\Company;
use App\Models\Contact;
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
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Company|null                          $company
 * @property-read Collection<int, SupplierProduct>      $supplierProducts
 * @property-read Collection<int, PurchaseOrder>        $purchaseOrders
 */
class Supplier extends Model
{
    protected $fillable = [
        'company_id',
        'lieferanten_nr',
        'name',
        'contact_name',
        'email',
        'phone',
        'address',
        'currency',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
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
}
