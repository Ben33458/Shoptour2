<?php

declare(strict_types=1);

namespace App\Models\Pricing;

use App\Models\Address;
use App\Models\Contact;
use App\Models\Orders\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Represents a business or consumer customer in the Kolabri system.
 *
 * Customers are distinct from Laravel auth Users — a Customer is the commercial
 * entity; a User is the login principal. They are linked via user_id (nullable).
 *
 * Pricing look-up chain for a Customer:
 *   1. customer_prices  (valid today, for this customer + product)
 *   2. customer_group_prices (valid today, for this customer's group + product)
 *   3. base_price + customer_group.adjustment
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property int         $customer_group_id
 * @property string      $customer_number
 * @property string|null $lexoffice_contact_id  Lexoffice contact UUID (WP-17)
 * @property string      $price_display_mode   "gross"|"net"
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $delivery_address_text  Free-form delivery address shown to driver
 * @property string|null $delivery_note          Standing per-customer delivery note for driver
 * @property bool        $active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read CustomerGroup                       $customerGroup
 * @property-read Collection<int, CustomerPrice>      $customerPrices
 * @property-read Collection<int, Order>              $orders
 */
class Customer extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'customer_group_id',
        'customer_number',
        'company_name',
        'lexoffice_contact_id',
        'price_display_mode',
        'first_name',
        'last_name',
        'email',
        'phone',
        'delivery_address_text',
        'delivery_note',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The pricing group this customer belongs to.
     */
    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    /**
     * Individual negotiated prices for this customer.
     */
    public function customerPrices(): HasMany
    {
        return $this->hasMany(CustomerPrice::class);
    }

    /**
     * All orders placed by this customer.
     *
     * @return HasMany<Order>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Ansprechpartner (multiple contacts) for this customer.
     */
    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable')->orderBy('sort_order');
    }

    // -------------------------------------------------------------------------
    // Address relations (WP-21)
    // -------------------------------------------------------------------------

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class)->orderByDesc('is_default')->orderBy('id');
    }

    public function deliveryAddresses(): HasMany
    {
        return $this->hasMany(Address::class)->where('type', 'delivery')->orderByDesc('is_default');
    }

    public function billingAddresses(): HasMany
    {
        return $this->hasMany(Address::class)->where('type', 'billing')->orderByDesc('is_default');
    }

    public function defaultDeliveryAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('type', 'delivery')->where('is_default', true);
    }

    public function defaultBillingAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('type', 'billing')->where('is_default', true);
    }
}
