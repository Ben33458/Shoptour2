<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Pricing\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verknüpft einen Laravel-User (role=sub_user) mit einem Hauptkunden.
 *
 * @property int   $id
 * @property int   $user_id
 * @property int   $parent_customer_id
 * @property array $permissions  {orders, order_history, invoices, addresses, assortment, sub_users, bestellen_all, bestellen_favoritenliste, sollbestaende_bearbeiten, preise_sehen}
 * @property bool  $active
 */
class SubUser extends Model
{
    protected $fillable = [
        'user_id',
        'parent_customer_id',
        'permissions',
        'active',
        'company_id',
    ];

    protected $casts = [
        'permissions' => 'array',
        'active'      => 'boolean',
    ];

    public static function defaultPermissions(): array
    {
        return [
            // PROJ-21 existing
            'orders'        => true,
            'order_history' => 'own',  // 'own' | 'all'
            'invoices'      => false,
            'addresses'     => false,
            'assortment'    => false,
            'sub_users'     => false,
            // PROJ-20 new
            'bestellen_all'               => false,
            'bestellen_favoritenliste'    => true,
            'sollbestaende_bearbeiten'    => false,
            'preise_sehen'                => false,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parentCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'parent_customer_id');
    }

    public function can(string $permission): bool
    {
        return (bool) ($this->permissions[$permission] ?? false);
    }
}
