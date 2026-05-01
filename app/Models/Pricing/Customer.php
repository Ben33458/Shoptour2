<?php

declare(strict_types=1);

namespace App\Models\Pricing;

use App\Models\Address;
use App\Models\Admin\LexofficeVoucher;
use App\Models\Communications\Communication;
use App\Models\Contact;
use App\Models\CustomerFavorite;
use App\Models\Debtor\DebtorNote;
use App\Models\SubUser;
use App\Models\Orders\Order;
use App\Models\Pricing\CustomerNote;
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
    // Lieferfreigabe delivery_status values
    public const DELIVERY_NORMAL  = 'normal';
    public const DELIVERY_WARNING = 'warning';
    public const DELIVERY_BLOCKED = 'blocked';

    // Optional payment condition hints
    public const CONDITION_CASH_ONLY  = 'cash_only';
    public const CONDITION_PREPAYMENT = 'prepayment';
    public const CONDITION_STOP_CHECK = 'stop_check';

    protected $fillable = [
        'company_id',
        'user_id',
        'customer_group_id',
        'customer_number',
        'company_name',
        'lexoffice_contact_id',
        'ninox_kunden_id',
        'wawi_kunden_id',
        'price_display_mode',
        'display_preferences',
        'first_name',
        'last_name',
        'email',
        'phone',
        'delivery_address_text',
        'delivery_note',
        'active',
        'billing_email',
        'notification_email',
        'email_notification_shipping',
        'newsletter_consent',
        // Debtor / Lieferfreigabe fields
        'delivery_status',
        'delivery_condition',
        'delivery_status_note',
        'delivery_status_set_by',
        'debt_hold',
        'debt_hold_reason',
        'kunde_von',
        'birth_date',
    ];

    protected $casts = [
        'active'                      => 'boolean',
        'email_notification_shipping' => 'boolean',
        'debt_hold'                   => 'boolean',
        'display_preferences'         => 'array',
        'birth_date'                  => 'date',
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
     * History notes and Lexoffice diff entries for this customer.
     *
     * @return HasMany<CustomerNote>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->orderByDesc('created_at');
    }

    /**
     * Unreviewed notes (e.g. pending Lexoffice diffs).
     *
     * @return HasMany<CustomerNote>
     */
    public function unreviewedNotes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->whereNull('reviewed_at')->orderByDesc('created_at');
    }

    /**
     * Ansprechpartner (multiple contacts) for this customer.
     */
    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable')->orderBy('sort_order');
    }

    /**
     * All communications linked to this customer.
     */
    public function communications(): MorphMany
    {
        return $this->morphMany(Communication::class, 'communicable')->orderByDesc('received_at');
    }

    // -------------------------------------------------------------------------
    // Address relations (WP-21)
    // -------------------------------------------------------------------------

    public function subUsers(): HasMany
    {
        return $this->hasMany(SubUser::class, 'parent_customer_id')->with('user');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(CustomerFavorite::class)->orderBy('sort_order');
    }

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

    // ── Debtor / Mahnwesen ───────────────────────────────────────────────────

    /** All Lexoffice vouchers (invoices) linked to this customer. */
    public function lexofficeVouchers(): HasMany
    {
        return $this->hasMany(LexofficeVoucher::class);
    }

    /** Open/overdue sales invoices (offene Posten). */
    public function openVouchers(): HasMany
    {
        return $this->hasMany(LexofficeVoucher::class)
            ->whereIn('voucher_type', [LexofficeVoucher::TYPE_SALES_INVOICE])
            ->whereIn('voucher_status', [LexofficeVoucher::STATUS_OPEN, LexofficeVoucher::STATUS_OVERDUE]);
    }

    /** Debtor notes (Notizen, Aufgaben, Zahlungszusagen, Klärfälle). */
    public function debtorNotes(): HasMany
    {
        return $this->hasMany(DebtorNote::class)->orderByDesc('created_at');
    }

    /** Open debtor notes only. */
    public function openDebtorNotes(): HasMany
    {
        return $this->hasMany(DebtorNote::class)
            ->where('status', DebtorNote::STATUS_OPEN)
            ->orderByDesc('created_at');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Is this a B2B (commercial) customer? */
    public function isB2B(): bool
    {
        return ! empty($this->company_name);
    }

    /** Display name for lists. */
    public function displayName(): string
    {
        if ($this->company_name) {
            return $this->company_name;
        }

        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: $this->customer_number;
    }

    /** True when this customer originally came from Getränke Kehr. */
    public function isKehr(): bool
    {
        return $this->kunde_von === 'kehr';
    }

    /** Is delivery blocked or warned? */
    public function isDeliveryBlocked(): bool
    {
        return $this->delivery_status === self::DELIVERY_BLOCKED;
    }

    public function deliveryStatusLabel(): string
    {
        return match ($this->delivery_status ?? self::DELIVERY_NORMAL) {
            self::DELIVERY_WARNING => 'Warnhinweis',
            self::DELIVERY_BLOCKED => 'Liefersperre',
            default                => 'Freigegeben',
        };
    }
}
