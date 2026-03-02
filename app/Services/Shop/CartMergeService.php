<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\User;
use Illuminate\Support\Facades\Session;

/**
 * PROJ-3 -- Merges a guest shopping cart into the authenticated user's DB cart.
 *
 * After login the PHP session ID changes (regenerate). The guest cart data
 * was stored under the old session. We read it from the session store
 * before regeneration, then merge it into the user's persistent DB cart.
 *
 * Merge strategy: if the same product exists in both guest and auth cart,
 * quantities are added together (never replaced).
 *
 * For authenticated users, carts are persisted in the `carts` + `cart_items`
 * tables via CartService::mergeGuestCart().
 */
class CartMergeService
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    /**
     * Merge the guest cart into the authenticated user's DB cart.
     *
     * @param string $guestSessionId  The session ID before regeneration.
     * @param User|null $user         The authenticated user. If null, falls back to Auth::user().
     */
    public function merge(string $guestSessionId, ?User $user = null): void
    {
        $user = $user ?? \Illuminate\Support\Facades\Auth::user();

        if (!$user) {
            return;
        }

        // Delegate to CartService which handles DB persistence + session cleanup
        $this->cartService->mergeGuestCart($guestSessionId, $user);

        // Also clear the current session cart key (new session after regeneration)
        Session::forget('shop_cart');
    }
}
