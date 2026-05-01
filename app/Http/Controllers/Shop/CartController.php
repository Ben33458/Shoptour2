<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\User;
use App\Services\Catalog\JugendschutzService;
use App\Services\Rental\RentalCartService;
use App\Services\Shop\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * PROJ-3 -- Shopping cart management.
 *
 * Routes:
 *   GET    /warenkorb              -> index()    show cart page
 *   POST   /warenkorb              -> add()      add product
 *   PATCH  /warenkorb/{productId}  -> update()   change qty
 *   DELETE /warenkorb/{productId}  -> remove()   remove product
 *   DELETE /warenkorb              -> clear()     empty cart
 *   GET    /warenkorb/mini         -> miniCart()  JSON for Alpine.js header dropdown
 */
class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly RentalCartService $rentalCart,
    ) {}

    /**
     * GET /warenkorb -- show cart contents with prices, pfand, and tax breakdown.
     */
    public function index(): View
    {
        $user     = $this->authUser();
        $cartData = $this->cart->calculate($user);

        $rentalSummary = $this->rentalCart->getItemsSummary();
        $rentalFrom    = $this->rentalCart->getDateFrom();
        $rentalUntil   = $this->rentalCart->getDateUntil();
        $rentalTotal   = $this->rentalCart->totalNetMilli();
        $drinksTotal   = $cartData['total_milli'];
        $grandTotal    = $drinksTotal + $rentalTotal;
        $minAge        = JugendschutzService::cartMinAge($cartData['items']);

        return view('shop.cart', [
            'items'             => $cartData['items'],
            'minAge'            => $minAge,
            'subtotalNet'       => $cartData['subtotal_net_milli'],
            'subtotalGross'     => $cartData['subtotal_gross_milli'],
            'pfandTotal'        => $cartData['pfand_total_milli'],
            'taxBreakdown'      => $cartData['tax_breakdown'],
            'drinksTotal'       => $drinksTotal,
            'grandTotal'        => $grandTotal,
            'hasUnavailable'    => $cartData['has_unavailable'],
            'rentalSummary'     => $rentalSummary,
            'rentalFrom'        => $rentalFrom,
            'rentalUntil'       => $rentalUntil,
            'rentalTotal'       => $rentalTotal,
            'isEmpty'           => empty($cartData['items']) && $rentalSummary->isEmpty(),
        ]);
    }

    /**
     * POST /warenkorb -- add a product to the cart.
     * Accepts JSON (AJAX) or form POST.
     */
    public function add(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'qty'        => ['sometimes', 'integer', 'min:1', 'max:999'],
        ]);

        $product = Product::with('stocks')->findOrFail($validated['product_id']);

        // Block hidden products (not shown in shop)
        if (!$product->show_in_shop || !$product->active) {
            abort(404);
        }

        // Block discontinued products
        if ($product->availability_mode === Product::AVAILABILITY_DISCONTINUED) {
            $msg = 'Dieses Produkt ist nicht mehr erhaeltlich.';
            return $request->wantsJson()
                ? response()->json(['error' => $msg], 422)
                : back()->with('error', $msg);
        }

        // Block out-of-stock products
        if ($product->availability_mode === Product::AVAILABILITY_OUT_OF_STOCK) {
            $msg = 'Dieses Produkt ist derzeit nicht verfuegbar.';
            return $request->wantsJson()
                ? response()->json(['error' => $msg], 422)
                : back()->with('error', $msg);
        }

        // Block stock-based products with zero or negative stock
        if ($product->isStockBased() && $product->currentStock() <= 0) {
            $msg = 'Dieses Produkt ist leider nicht mehr auf Lager.';
            return $request->wantsJson()
                ? response()->json(['error' => $msg], 422)
                : back()->with('error', $msg);
        }

        $user   = $this->authUser();
        $result = $this->cart->add(
            (int) $validated['product_id'],
            (int) ($validated['qty'] ?? 1),
            $user,
        );

        if ($request->wantsJson()) {
            $cartData = $this->cart->calculate($user);

            // Build a small items preview (max 3)
            $preview = [];
            $i = 0;
            foreach ($cartData['items'] as $item) {
                if ($i >= 3) break;
                $price = $item['price'];
                $preview[] = [
                    'name'  => $item['product']->produktname,
                    'qty'   => $item['qty'],
                    'price' => $price ? $this->formatMilli($price->grossMilli) : '--',
                ];
                $i++;
            }

            return response()->json([
                'count'         => $result['count'],
                'items_preview' => $preview,
                'subtotal'      => $this->formatMilli($cartData['subtotal_gross_milli']),
                'total'         => $this->formatMilli($cartData['total_milli']),
            ]);
        }

        return back()->with('success', 'Produkt zum Warenkorb hinzugefuegt.');
    }

    /**
     * PATCH /warenkorb/{productId} -- update quantity.
     */
    public function update(Request $request, int $productId): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $user   = $this->authUser();
        $result = $this->cart->update($productId, (int) $validated['qty'], $user);

        if ($request->wantsJson()) {
            // Calculate the item total for the updated product
            $cartData = $this->cart->calculate($user);
            $itemTotalMilli = 0;
            if (isset($cartData['items'][$productId])) {
                $itemData = $cartData['items'][$productId];
                $itemTotalMilli = $itemData['line_gross'] + $itemData['line_pfand'];
            }

            return response()->json([
                'count'           => $result['count'],
                'capped'          => $result['capped'],
                'warning'         => $result['warning'],
                'item_total_milli' => $itemTotalMilli,
                'subtotal'        => $this->formatMilli($cartData['subtotal_gross_milli']),
                'total'           => $this->formatMilli($cartData['total_milli']),
            ]);
        }

        if ($result['warning']) {
            return redirect()->route('cart.index')->with('warning', $result['warning']);
        }

        return redirect()->route('cart.index');
    }

    /**
     * DELETE /warenkorb/{productId} -- remove a product from the cart.
     */
    public function remove(Request $request, int $productId): RedirectResponse|JsonResponse
    {
        $user  = $this->authUser();
        $count = $this->cart->remove($productId, $user);

        if ($request->wantsJson()) {
            $cartData = $this->cart->calculate($user);
            return response()->json([
                'count'    => $count,
                'subtotal' => $this->formatMilli($cartData['subtotal_gross_milli']),
                'total'    => $this->formatMilli($cartData['total_milli']),
            ]);
        }

        return redirect()->route('cart.index');
    }

    /**
     * DELETE /warenkorb -- empty the cart completely.
     */
    public function clear(Request $request): RedirectResponse
    {
        $user = $this->authUser();
        $this->cart->clear($user);

        return redirect()->route('cart.index')->with('success', 'Warenkorb wurde geleert.');
    }

    /**
     * GET /warenkorb/mini -- JSON response for Alpine.js header dropdown.
     */
    public function miniCart(Request $request): JsonResponse
    {
        $user     = $this->authUser();
        $cartData = $this->cart->calculate($user);

        $items = [];
        foreach ($cartData['items'] as $item) {
            $price = $item['price'];
            $items[] = [
                'name'          => $item['product']->produktname,
                'qty'           => $item['qty'],
                'price_display' => $price ? $this->formatMilli($price->grossMilli) : '--',
            ];
        }

        return response()->json([
            'count'         => array_sum(array_column($items, 'qty')),
            'items'         => $items,
            'total_display' => $this->formatMilli($cartData['total_milli']),
        ]);
    }

    // =========================================================================
    // Internal
    // =========================================================================

    /**
     * Get the authenticated user or null for guests.
     */
    private function authUser(): ?User
    {
        /** @var User|null */
        return Auth::user();
    }

    /**
     * Format a milli-cent amount as a German currency string.
     * E.g. 12500000 -> "12,50 EUR"
     */
    private function formatMilli(int $milli): string
    {
        $eur = $milli / 1_000_000;
        return number_format($eur, 2, ',', '.') . ' ' . "\u{20AC}";
    }
}
