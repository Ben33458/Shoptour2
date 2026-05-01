<?php
declare(strict_types=1);
namespace App\Models\Rental;
use App\Models\Orders\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rückgabeschein für Leihartikel.
 * PFLICHT für alle Leihartikel bei Rückgabe.
 */
class RentalReturnSlip extends Model
{
    protected $fillable = [
        'company_id','order_id','driver_user_id','returned_at','location','status','notes',
    ];

    protected $casts = ['returned_at' => 'datetime'];

    public const STATUS_OPEN     = 'open';
    public const STATUS_PARTIAL  = 'partial';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_CHARGED  = 'charged';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RentalReturnSlipItem::class);
    }
}
