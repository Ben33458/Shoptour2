<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SubUser;
use App\Models\User;

/**
 * PROJ-20/21 — Unified permission helper for the customer account area.
 *
 * Main customers (role=kunde) have all permissions.
 * Sub-users (role=sub_user) read their permissions from SubUser::permissions JSON.
 */
final readonly class CustomerPermissions
{
    private array $permissions;
    private bool  $isSubUser;

    public function __construct(User $user)
    {
        $this->isSubUser = $user->isSubUser();

        if ($this->isSubUser) {
            $subUser            = $user->subUser;
            $this->permissions  = $subUser?->permissions ?? [];
        } else {
            $this->permissions = [];
        }
    }

    // -------------------------------------------------------------------------
    // PROJ-21 existing permissions
    // -------------------------------------------------------------------------

    public function canViewOrders(): bool
    {
        return $this->isSubUser
            ? (bool) ($this->permissions['orders'] ?? false)
            : true;
    }

    public function canViewAllOrderHistory(): bool
    {
        if (! $this->isSubUser) {
            return true;
        }
        return ($this->permissions['order_history'] ?? 'own') === 'all';
    }

    public function canViewInvoices(): bool
    {
        return $this->isSubUser
            ? (bool) ($this->permissions['invoices'] ?? false)
            : true;
    }

    public function canManageAddresses(): bool
    {
        return $this->isSubUser
            ? (bool) ($this->permissions['addresses'] ?? false)
            : true;
    }

    public function canManageSubUsers(): bool
    {
        return $this->isSubUser
            ? (bool) ($this->permissions['sub_users'] ?? false)
            : true;
    }

    // -------------------------------------------------------------------------
    // PROJ-20 new permissions
    // -------------------------------------------------------------------------

    /** Can place orders from anywhere in the shop */
    public function canOrderAll(): bool
    {
        return $this->isSubUser
            ? (bool) ($this->permissions['bestellen_all'] ?? false)
            : true;
    }

    /** Can add to cart from the Stammsortiment/favorites list */
    public function canOrderFromFavorites(): bool
    {
        return $this->isSubUser
            ? (bool) ($this->permissions['bestellen_favoritenliste'] ?? false)
            : true;
    }

    /** Can edit Sollbestand (target stock) on the favorites list */
    public function canEditTargetStock(): bool
    {
        return $this->isSubUser
            ? (bool) ($this->permissions['sollbestaende_bearbeiten'] ?? false)
            : true;
    }

    /** Can see prices */
    public function canSeePrices(): bool
    {
        return $this->isSubUser
            ? (bool) ($this->permissions['preise_sehen'] ?? false)
            : true;
    }

    /** Can view the Stammsortiment / favorites list */
    public function canViewAssortment(): bool
    {
        return $this->isSubUser
            ? (bool) ($this->permissions['assortment'] ?? false)
            : true;
    }
}
