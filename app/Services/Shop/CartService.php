<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\DTOs\Pricing\PriceResult;
use App\Models\Catalog\Product;
use App\Models\Pricing\Customer;
use App\Models\Shop\Cart;
use App\Models\Shop\CartItem;
use App\Models\User;
use App\Services\Orders\PfandCalculator;
use App\Services\Pricing\PriceResolverService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * PROJ-3 -- Shopping cart service with dual storage strategy.
 *
 * Guest users:         Session-backed cart (session key 'shop_cart').
 * Authenticated users: DB-backed cart (carts + cart_items tables).
 *
 * Prices are NEVER stored permanently -- they are resolved fresh on every
 * display via PriceResolverService + PfandCalculator.
 */
class CartService
{
    private const SESSION_KEY = 'shop_cart';

    public function __construct(
        private readonly PriceResolverService $priceResolver,
        private readonly PfandCalculator      $pfandCalculator,
    ) {}

    // =========================================================================
    // Mutations
    // =========================================================================

    /**
     * Add qty units of a product to the cart.
     * If the product is already in the cart the quantities are summed.
     *
     * @return array{count: int, success: bool}
     */
    public function add(int $productId, int $qty = 1, ?User $user = null): array
    {
        $qty = max(1, $qty);

        if ($user) {
            $cart = $this->getOrCreateDbCart($user);
            $item = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $productId)
                ->first();

            if ($item) {
                $item->increment('quantity', $qty);
            } else {
                // Snapshot the price at time of adding
                $snapshot = $this->snapshotPrice($productId, $user);

                CartItem::create([
                    'cart_id'                => $cart->id,
                    'product_id'             => $productId,
                    'quantity'               => $qty,
                    'unit_price_gross_milli' => $snapshot['gross_milli'],
                    'pfand_milli'            => $snapshot['pfand_milli'],
                    'company_id'             => $user->company_id,
                ]);
            }

            return ['count' => $this->totalItems($user), 'success' => true];
        }

        // Guest: session-based
        $cart = $this->rawSessionCart();
        $cart[$productId] = ($cart[$productId] ?? 0) + $qty;
        Session::put(self::SESSION_KEY, $cart);

        return ['count' => $this->totalItems(null), 'success' => true];
    }

    /**
     * Set the quantity for a product explicitly.
     * Passing qty <= 0 removes the item.
     *
     * For stock_based products: caps qty at available stock and returns warning.
     *
     * @return array{count: int, capped: bool, max: int|null, warning: string|null}
     */
    public function update(int $productId, int $qty, ?User $user = null): array
    {
        $capped  = false;
        $max     = null;
        $warning = null;

        if ($qty <= 0) {
            $this->remove($productId, $user);
            return ['count' => $this->totalItems($user), 'capped' => false, 'max' => null, 'warning' => null];
        }

        // Stock enforcement for stock_based products
        $product = Product::with('stocks')->find($productId);
        if ($product && $product->isStockBased()) {
            $available = $product->currentStock();
            if ($qty > $available) {
                $qty     = max(0, $available);
                $capped  = true;
                $max     = $available;
                $warning = $available > 0
                    ? "Nur noch {$available} Stück verfügbar. Die Menge wurde angepasst."
                    : 'Dieses Produkt ist derzeit nicht auf Lager.';

                if ($qty <= 0) {
                    $this->remove($productId, $user);
                    return ['count' => $this->totalItems($user), 'capped' => $capped, 'max' => $max, 'warning' => $warning];
                }
            }
        }

        if ($user) {
            $cart = $this->getOrCreateDbCart($user);
            $item = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $productId)
                ->first();

            if ($item) {
                $item->update(['quantity' => $qty]);
            }
        } else {
            $cart = $this->rawSessionCart();
            $cart[$productId] = $qty;
            Session::put(self::SESSION_KEY, $cart);
        }

        return [
            'count'   => $this->totalItems($user),
            'capped'  => $capped,
            'max'     => $max,
            'warning' => $warning,
        ];
    }

    /**
     * Remove a product from the cart entirely.
     *
     * @return int New total item count
     */
    public function remove(int $productId, ?User $user = null): int
    {
        if ($user) {
            $cart = $this->findActiveDbCart($user);
            if ($cart) {
                CartItem::where('cart_id', $cart->id)
                    ->where('product_id', $productId)
                    ->delete();
            }
        } else {
            $cart = $this->rawSessionCart();
            unset($cart[$productId]);
            Session::put(self::SESSION_KEY, $cart);
        }

        return $this->totalItems($user);
    }

    /**
     * Empty the cart completely.
     */
    public function clear(?User $user = null): void
    {
        if ($user) {
            $cart = $this->findActiveDbCart($user);
            if ($cart) {
                $cart->items()->delete();
            }
        } else {
            Session::forget(self::SESSION_KEY);
        }
    }

    // =========================================================================
    // Reads
    // =========================================================================

    /**
     * Total number of items (sum of quantities) in the cart.
     */
    public function totalItems(?User $user = null): int
    {
        if ($user) {
            $cart = $this->findActiveDbCart($user);
            return $cart ? (int) $cart->items()->sum('quantity') : 0;
        }

        return array_sum($this->rawSessionCart());
    }

    /**
     * Whether the cart is empty.
     */
    public function isEmpty(?User $user = null): bool
    {
        return $this->totalItems($user) === 0;
    }

    /**
     * Returns cart lines with product data.
     * Products that no longer exist or are inactive are still returned
     * but flagged so the view can warn the user.
     *
     * @return array<int, array{product: Product, qty: int}>  Keyed by product_id
     */
    public function items(?User $user = null): array
    {
        if ($user) {
            return $this->itemsFromDb($user);
        }

        return $this->itemsFromSession();
    }

    /**
     * Calculate full cart data with live prices, pfand, tax breakdown.
     *
     * @return array{
     *     items: array,
     *     subtotal_net_milli: int,
     *     subtotal_gross_milli: int,
     *     pfand_total_milli: int,
     *     tax_breakdown: array,
     *     total_milli: int,
     *     has_unavailable: bool,
     * }
     */
    public function calculate(?User $user = null): array
    {
        $lines    = $this->items($user);
        $customer = $this->resolveCustomer($user);

        $subtotalNet   = 0;
        $subtotalGross = 0;
        $pfandTotal    = 0;
        $taxBuckets    = []; // keyed by tax_rate_id
        $itemsData     = [];
        $hasUnavailable = false;

        foreach ($lines as $productId => $line) {
            /** @var Product $product */
            $product = $line['product'];
            $qty     = $line['qty'];

            // Detect unavailable products
            $unavailable = false;
            if (!$product->active || !$product->show_in_shop) {
                $unavailable = true;
            }
            if ($product->availability_mode === Product::AVAILABILITY_DISCONTINUED) {
                $unavailable = true;
            }
            if ($product->availability_mode === Product::AVAILABILITY_OUT_OF_STOCK) {
                $unavailable = true;
            }
            if ($unavailable) {
                $hasUnavailable = true;
            }

            // Resolve live price
            try {
                $price = $customer
                    ? $this->priceResolver->resolveForCustomer($product, $customer)
                    : $this->priceResolver->resolveForGuest($product);
            } catch (\Throwable) {
                $price = null;
            }

            // Pfand
            $pfandPerUnit = $product->gebinde
                ? $this->pfandCalculator->totalForGebinde($product->gebinde)
                : 0;

            $lineNet   = $price ? $price->netMilli * $qty : 0;
            $lineGross = $price ? $price->grossMilli * $qty : 0;
            $linePfand = $pfandPerUnit * $qty;

            $subtotalNet   += $lineNet;
            $subtotalGross += $lineGross;
            $pfandTotal    += $linePfand;

            // Tax breakdown: group by tax_rate_id
            if ($price && $product->tax_rate_id) {
                $taxRateId = $product->tax_rate_id;
                if (!isset($taxBuckets[$taxRateId])) {
                    // Derive the percentage from basis points
                    $rateBp = $product->taxRate?->rate_basis_points ?? 0;
                    $taxBuckets[$taxRateId] = [
                        'rate'      => $rateBp > 0 ? $rateBp / 10_000 : 0, // e.g. 19.0
                        'net_milli' => 0,
                        'tax_milli' => 0,
                    ];
                }
                $taxBuckets[$taxRateId]['net_milli'] += $lineNet;
                $taxBuckets[$taxRateId]['tax_milli'] += ($lineGross - $lineNet);
            }

            $itemsData[$productId] = [
                'product'        => $product,
                'qty'            => $qty,
                'price'          => $price,
                'pfand_per_unit' => $pfandPerUnit,
                'line_net'       => $lineNet,
                'line_gross'     => $lineGross,
                'line_pfand'     => $linePfand,
                'unavailable'    => $unavailable,
            ];
        }

        return [
            'items'               => $itemsData,
            'subtotal_net_milli'  => $subtotalNet,
            'subtotal_gross_milli' => $subtotalGross,
            'pfand_total_milli'   => $pfandTotal,
            'tax_breakdown'       => array_values($taxBuckets),
            'total_milli'         => $subtotalGross + $pfandTotal,
            'has_unavailable'     => $hasUnavailable,
        ];
    }

    /**
     * Merge guest session cart into an authenticated user's DB cart.
     * Called after login. Guest items are added to the user's DB cart;
     * duplicate products have their quantities summed.
     *
     * @param string $sessionId  The guest session ID (before regeneration)
     * @param User   $user       The newly authenticated user
     */
    public function mergeGuestCart(string $sessionId, User $user): void
    {
        // Read guest cart from the old session store
        $guestCart = $this->readGuestCartFromStore($sessionId);

        if (empty($guestCart)) {
            return;
        }

        $dbCart = $this->getOrCreateDbCart($user);

        foreach ($guestCart as $productId => $qty) {
            $productId = (int) $productId;
            $qty = max(1, (int) $qty);

            $existing = CartItem::where('cart_id', $dbCart->id)
                ->where('product_id', $productId)
                ->first();

            if ($existing) {
                $existing->increment('quantity', $qty);
            } else {
                $snapshot = $this->snapshotPrice($productId, $user);

                CartItem::create([
                    'cart_id'                => $dbCart->id,
                    'product_id'             => $productId,
                    'quantity'               => $qty,
                    'unit_price_gross_milli' => $snapshot['gross_milli'],
                    'pfand_milli'            => $snapshot['pfand_milli'],
                    'company_id'             => $user->company_id,
                ]);
            }
        }

        // Clean up the old guest session record
        \Illuminate\Support\Facades\DB::table('sessions')
            ->where('id', $sessionId)
            ->delete();
    }

    /**
     * Raw session data: [ product_id => qty ]
     *
     * @return array<int, int>
     */
    public function rawSessionCart(): array
    {
        /** @var array<int, int> $cart */
        $cart = Session::get(self::SESSION_KEY, []);
        return is_array($cart) ? $cart : [];
    }

    // =========================================================================
    // Internal: DB cart helpers
    // =========================================================================

    /**
     * Get or create the active DB cart for an authenticated user.
     */
    private function getOrCreateDbCart(User $user): Cart
    {
        // BUG-4 fix: scope by company_id for multi-tenant isolation.
        $cart = Cart::where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->first();

        if (!$cart) {
            $cart = Cart::create([
                'user_id'    => $user->id,
                'company_id' => $user->company_id,
                'status'     => 'active',
            ]);
        }

        return $cart;
    }

    /**
     * Find the active DB cart for a user (without creating one).
     */
    private function findActiveDbCart(User $user): ?Cart
    {
        // BUG-4 fix: scope by company_id for multi-tenant isolation.
        return Cart::where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Load items from DB cart with eager-loaded products.
     *
     * @return array<int, array{product: Product, qty: int}>
     */
    private function itemsFromDb(User $user): array
    {
        $cart = $this->findActiveDbCart($user);
        if (!$cart) {
            return [];
        }

        $cartItems = CartItem::where('cart_id', $cart->id)
            ->with([
                'product.mainImage',
                'product.gebinde.pfandSet.components.pfandItem',
                'product.gebinde.pfandSet.components.childPfandSet',
                'product.taxRate',
            ])
            ->get();

        $lines = [];
        foreach ($cartItems as $item) {
            if ($item->product) {
                $lines[$item->product_id] = [
                    'product' => $item->product,
                    'qty'     => $item->quantity,
                ];
            }
        }

        return $lines;
    }

    /**
     * Load items from session cart (guest users).
     *
     * @return array<int, array{product: Product, qty: int}>
     */
    private function itemsFromSession(): array
    {
        $raw = $this->rawSessionCart();
        if (empty($raw)) {
            return [];
        }

        /** @var Collection<int, Product> $products */
        $products = Product::with([
            'mainImage',
            'gebinde.pfandSet.components.pfandItem',
            'gebinde.pfandSet.components.childPfandSet',
            'taxRate',
        ])
        ->whereIn('id', array_keys($raw))
        ->get()
        ->keyBy('id');

        $lines = [];
        foreach ($raw as $productId => $qty) {
            if ($products->has($productId)) {
                $lines[$productId] = [
                    'product' => $products->get($productId),
                    'qty'     => $qty,
                ];
            }
        }

        return $lines;
    }

    /**
     * Snapshot the current price and pfand for a product (used when adding to DB cart).
     *
     * @return array{gross_milli: int, pfand_milli: int}
     */
    private function snapshotPrice(int $productId, User $user): array
    {
        $product = Product::with(['gebinde', 'taxRate'])->find($productId);
        if (!$product) {
            return ['gross_milli' => 0, 'pfand_milli' => 0];
        }

        $customer = $user->customer;
        try {
            $price = $customer
                ? $this->priceResolver->resolveForCustomer($product, $customer)
                : $this->priceResolver->resolveForGuest($product);
            $grossMilli = $price->grossMilli;
        } catch (\Throwable) {
            $grossMilli = $product->base_price_gross_milli;
        }

        $pfandMilli = $product->gebinde
            ? $this->pfandCalculator->totalForGebinde($product->gebinde)
            : 0;

        return ['gross_milli' => $grossMilli, 'pfand_milli' => $pfandMilli];
    }

    /**
     * Read guest cart data from the session store (before session regeneration).
     *
     * @return array<int, int>  product_id => qty
     */
    private function readGuestCartFromStore(string $guestSessionId): array
    {
        $record = \Illuminate\Support\Facades\DB::table('sessions')
            ->where('id', $guestSessionId)
            ->value('payload');

        if (!$record) {
            return [];
        }

        // BUG-8 fix: restrict unserialize to scalars/arrays only — prevents PHP object injection.
        $data = @unserialize(base64_decode($record), ['allowed_classes' => false]);
        if (!is_array($data)) {
            return [];
        }

        return isset($data[self::SESSION_KEY]) && is_array($data[self::SESSION_KEY])
            ? $data[self::SESSION_KEY]
            : [];
    }

    /**
     * Resolve the Customer model for the given user, or null for guests.
     */
    private function resolveCustomer(?User $user): ?Customer
    {
        if (!$user) {
            return null;
        }
        return $user->customer;
    }
}
