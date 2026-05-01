<?php

declare(strict_types=1);

namespace App\Models\Driver;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    public const TYPE_WALLET   = 'wallet';
    public const TYPE_SAFE     = 'safe';
    public const TYPE_REGISTER = 'register';
    public const TYPE_BANK     = 'bank';

    protected $fillable = ['name', 'is_active', 'register_type'];

    protected $casts = ['is_active' => 'boolean'];

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }
}
