<?php

declare(strict_types=1);

namespace App\Models\Orders;

use App\Models\Address;
use App\Models\Admin\OrderAdjustment;
use App\Models\Delivery\RegularDeliveryTour;
use App\Models\Delivery\TourStop;
use App\Models\Event\EventLocation;
use App\Models\Inventory\Warehouse;
use App\Models\Pricing\Customer;
use App\Models\Pricing\CustomerGroup;
use App\Models\Rental\RentalBookingItem;
use App\Models\Rental\RentalReturnSlip;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Represents a customer order in the Kolabri Getränke system.
 *
 * Monetary convention: all *_milli fields are milli-cents (int).
 *   1 EUR = 1_000_000 milli-cents
 *
 * Status lifecycle:
 *   pending → confirmed → shipped → delivered
 *                       ↘ cancelled (from any state)
 *
 * Backorder model:
 *   Orders are always accepted. When one or more items exceed available
 *   warehouse stock, has_backorder is set to true and the affected
 *   order_items carry is_backorder = true. Fulfilment is handled separately.
 *
 * @property int              $id
 * @property int|null         $company_id
 * @property string|null      $order_number
 * @property int              $customer_id
 * @property int              $customer_group_id_snapshot
 * @property string           $status
 * @property string           $delivery_type          home_delivery|pickup
 * @property string|null      $payment_method         stripe|paypal|sepa|invoice|cash|ec
 * @property \Carbon\Carbon|null $delivery_date
 * @property int|null         $warehouse_id
 * @property int|null         $pickup_location_id
 * @property string|null      $payment_reference
 * @property int|null         $regular_delivery_tour_id
 * @property int|null         $delivery_address_id
 * @property bool             $has_backorder
 * @property int              $total_net_milli
 * @property int              $total_gross_milli
 * @property int              $total_pfand_brutto_milli
 * @property string|null      $notes
 * @property string|null      $customer_notes
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 *
 * @property-read Customer                          $customer
 * @property-read CustomerGroup                     $customerGroupSnapshot
 * @property-read Warehouse|null                    $warehouse
 * @property-read Warehouse|null                    $pickupLocation
 * @property-read Address|null                      $deliveryAddress
 * @property-read RegularDeliveryTour|null          $regularDeliveryTour
 * @property-read Collection<int, OrderItem>        $items
 * @property-read Collection<int, OrderAdjustment>  $adjustments
 * @property-read TourStop|null                     $tourStop
 */
class Order extends Model
{
    // -------------------------------------------------------------------------
    // Status constants
    // -------------------------------------------------------------------------

    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_SHIPPED   = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    // Delivery type constants
    public const DELIVERY_HOME = 'home_delivery';
    public const DELIVERY_PICKUP = 'pickup';

    // Payment method constants
    public const PAY_STRIPE  = 'stripe';
    public const PAY_PAYPAL  = 'paypal';
    public const PAY_SEPA    = 'sepa';
    public const PAY_INVOICE = 'invoice';
    public const PAY_CASH    = 'cash';
    public const PAY_EC      = 'ec';

    /** Payment methods that require external redirect */
    public const REDIRECT_PAYMENT_METHODS = [self::PAY_STRIPE, self::PAY_PAYPAL];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'order_number',
        'customer_id',
        'customer_group_id_snapshot',
        'status',
        'delivery_type',
        'payment_method',
        'delivery_date',
        'warehouse_id',
        'pickup_location_id',
        'payment_reference',
        'regular_delivery_tour_id',
        'delivery_address_id',
        'has_backorder',
        'immediate_payment',
        'is_pos_sale',
        'total_net_milli',
        'total_gross_milli',
        'total_pfand_brutto_milli',
        'notes',
        'customer_notes',
        'is_event_order',
        'event_location_id',
        'event_location_name',
        'event_location_street',
        'event_location_zip',
        'event_location_city',
        'event_contact_name',
        'event_contact_phone',
        'event_access_notes',
        'event_setup_notes',
        'event_has_power',
        'event_suitable_ground',
        'desired_delivery_date',
        'desired_delivery_window_from',
        'desired_delivery_window_until',
        'desired_pickup_date',
        'desired_pickup_window_from',
        'desired_pickup_window_until',
        'confirmed_delivery_window_from',
        'confirmed_delivery_window_until',
        'confirmed_pickup_window_from',
        'confirmed_pickup_window_until',
        'logistics_class',
        'event_delivery_mode',
        'event_pickup_mode',
        'prepayment_required_milli',
        'prepayment_due_date',
        'prepayment_received',
        'distance_km',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'has_backorder'            => 'boolean',
        'immediate_payment'        => 'boolean',
        'is_pos_sale'              => 'boolean',
        'is_event_order'           => 'boolean',
        'event_has_power'          => 'boolean',
        'event_suitable_ground'    => 'boolean',
        'prepayment_received'      => 'boolean',
        'total_net_milli'          => 'integer',
        'total_gross_milli'        => 'integer',
        'total_pfand_brutto_milli' => 'integer',
        'prepayment_required_milli' => 'integer',
        'delivery_date'            => 'date',
        'desired_delivery_date'    => 'date',
        'desired_pickup_date'      => 'date',
        'prepayment_due_date'      => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The customer who placed this order.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The customer group that was active at order time (snapshot FK).
     */
    public function customerGroupSnapshot(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id_snapshot');
    }

    /**
     * The warehouse this order is fulfilled from (nullable).
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * The pickup location warehouse (when delivery_type = pickup).
     */
    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'pickup_location_id');
    }

    /**
     * The delivery address for this order.
     */
    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    /**
     * All line items belonging to this order.
     *
     * @return HasMany<OrderItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * All adjustments (deposit returns, breakage, corrections) for this order.
     *
     * @return HasMany<OrderAdjustment>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class);
    }

    /**
     * The regular delivery tour this order is assigned to (set at checkout).
     */
    public function regularDeliveryTour(): BelongsTo
    {
        return $this->belongsTo(RegularDeliveryTour::class);
    }

    /**
     * The TourStop that includes this order (one order appears at most once across all tours).
     *
     * @return HasOne<TourStop>
     */
    public function tourStop(): HasOne
    {
        return $this->hasOne(TourStop::class);
    }

    /**
     * The event location for this order (when is_event_order = true).
     */
    public function eventLocation(): BelongsTo
    {
        return $this->belongsTo(EventLocation::class);
    }

    /**
     * All rental booking items for this order.
     *
     * @return HasMany<RentalBookingItem>
     */
    public function rentalBookingItems(): HasMany
    {
        return $this->hasMany(RentalBookingItem::class);
    }

    /**
     * The rental return slip for this order.
     *
     * @return HasOne<RentalReturnSlip>
     */
    public function returnSlip(): HasOne
    {
        return $this->hasOne(RentalReturnSlip::class);
    }
}
