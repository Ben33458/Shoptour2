<?php

declare(strict_types=1);

namespace App\Models\Shop;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PROJ-3: Persistent shopping cart for authenticated users.
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property int|null    $user_id
 * @property string|null $session_id
 * @property string      $status        active|merged|abandoned
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read User|null                                        $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CartItem> $items
 */
class Cart extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'session_id',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
