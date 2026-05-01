<?php
declare(strict_types=1);
namespace App\Models\Rental;
use App\Models\Orders\Order;
use App\Models\Pricing\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rücknahme bei normaler Lieferung oder Eventbelieferung.
 * Typen: deposit (Pfandrückgabe) | full_goods (Vollgut-Rückgabe)
 */
class DeliveryReturn extends Model
{
    protected $fillable = [
        'company_id','order_id','customer_id','driver_user_id','returned_at','return_type','notes',
    ];

    protected $casts = ['returned_at' => 'datetime'];

    public const TYPE_DEPOSIT    = 'deposit';
    public const TYPE_FULL_GOODS = 'full_goods';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryReturnItem::class);
    }
}
