<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Pricing\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores a one-time verification code for activating an existing customer account.
 *
 * @property int              $id
 * @property int              $customer_id
 * @property string           $email
 * @property string           $code_hash        SHA-256 of the 6-digit code
 * @property \Carbon\Carbon   $expires_at
 * @property \Carbon\Carbon|null $used_at
 * @property int              $verify_attempts  wrong code entries so far
 * @property string|null      $ip_address
 */
class CustomerActivationToken extends Model
{
    protected $fillable = [
        'customer_id',
        'email',
        'code_hash',
        'expires_at',
        'used_at',
        'verify_attempts',
        'ip_address',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isExhausted(): bool
    {
        return $this->verify_attempts >= 10;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isUsed() && ! $this->isExhausted();
    }

    public function verifyCode(string $plainCode): bool
    {
        return hash_equals($this->code_hash, hash('sha256', $plainCode));
    }
}
