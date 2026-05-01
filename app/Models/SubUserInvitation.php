<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Pricing\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property string      $email
 * @property string      $first_name
 * @property string      $last_name
 * @property int         $parent_customer_id
 * @property array       $permissions
 * @property string      $token
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $used_at
 */
class SubUserInvitation extends Model
{
    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'parent_customer_id',
        'permissions',
        'token',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'expires_at'  => 'datetime',
        'used_at'     => 'datetime',
    ];

    public function parentCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'parent_customer_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isUsed() && ! $this->isExpired();
    }
}
