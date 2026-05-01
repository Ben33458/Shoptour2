<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\CustomerFavorite;
use App\Models\Pricing\Customer;
use App\Models\User;
use App\Services\Shop\CartService;
use App\Services\Pricing\PriceResolverService;
use App\Support\CustomerPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * PROJ-20 — Customer Stammsortiment (favorites/assortment) management.
 */
class FavoriteController extends Controller
{
    public function __construct(
        private readonly CartService          $cart,
        private readonly PriceResolverService $priceResolver,
    ) {}

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function index(): View
    {
        /** @var User $user */
        $user     = Auth::user();
        $perms    = new CustomerPermissions($user);
        $customer = $this->requireCustomer();

        if (! $perms->canViewAssortment()) {
            abort(403, 'Sie haben keine Berechtigung, das Stammsortiment zu sehen.');
        }

        $favorites = $customer->favorites()
            ->with(['product.gebinde', 'product.mainImage'])
            ->get();

        // Resolve prices (only if permitted)
        $prices = [];
        if ($perms->canSeePrices()) {
            foreach ($favorites as $fav) {
                if ($fav->product) {
                    try {
                        $result = $this->priceResolver->resolveForCustomer($fav->product, $customer);
                        $prices[$fav->product_id] = $result;
                    } catch (\Throwable) {
                        // no price available
                    }
                }
            }
        }

        return view('shop.account.favorites.index', compact('customer', 'favorites', 'prices', 'perms'));
    }

    // -------------------------------------------------------------------------
    // Add product to favorites
    // -------------------------------------------------------------------------

    public function store(Request $request): RedirectResponse
    {
        $customer = $this->requireCustomer();
        $user     = Auth::user();
        $perms    = new CustomerPermissions($user);

        if (! $perms->canViewAssortment()) {
            abort(403);
        }

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        // Avoid duplicates
        $exists = CustomerFavorite::where('customer_id', $customer->id)
            ->where('product_id', $validated['product_id'])
            ->exists();

        if (! $exists) {
            $maxOrder = CustomerFavorite::where('customer_id', $customer->id)->max('sort_order') ?? 0;

            CustomerFavorite::create([
                'customer_id'        => $customer->id,
                'product_id'         => $validated['product_id'],
                'sort_order'         => $maxOrder + 1,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ]);
        }

        return redirect()->route('account.favorites')->with('success', 'Produkt zum Stammsortiment hinzugefügt.');
    }

    // -------------------------------------------------------------------------
    // Remove from favorites
    // -------------------------------------------------------------------------

    public function destroy(CustomerFavorite $favorite): RedirectResponse
    {
        $customer = $this->requireCustomer();
        if ($favorite->customer_id !== $customer->id) {
            abort(403);
        }

        $favorite->delete();

        return redirect()->route('account.favorites')->with('success', 'Produkt aus dem Stammsortiment entfernt.');
    }

    // -------------------------------------------------------------------------
    // Update Istbestand (actual stock) — AJAX PATCH
    // -------------------------------------------------------------------------

    public function updateActualStock(Request $request, CustomerFavorite $favorite): JsonResponse
    {
        $customer = $this->requireCustomer();
        if ($favorite->customer_id !== $customer->id) {
            abort(403);
        }

        $validated = $request->validate([
            'actual_stock_units' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $favorite->update([
            'actual_stock_units' => $validated['actual_stock_units'],
            'updated_by_user_id' => Auth::id(),
        ]);

        return response()->json([
            'actual_stock_units' => $favorite->actual_stock_units,
            'order_qty'          => $favorite->orderQty(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Update Sollbestand (target stock) — AJAX PATCH
    // -------------------------------------------------------------------------

    public function updateTargetStock(Request $request, CustomerFavorite $favorite): JsonResponse
    {
        $customer = $this->requireCustomer();
        if ($favorite->customer_id !== $customer->id) {
            abort(403);
        }

        $perms = new CustomerPermissions(Auth::user());
        if (! $perms->canEditTargetStock()) {
            abort(403, 'Keine Berechtigung, Sollbestände zu bearbeiten.');
        }

        $validated = $request->validate([
            'target_stock_units' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $favorite->update([
            'target_stock_units' => $validated['target_stock_units'],
            'updated_by_user_id' => Auth::id(),
        ]);

        return response()->json([
            'target_stock_units' => $favorite->target_stock_units,
            'order_qty'          => $favorite->orderQty(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Reorder (drag-and-drop) — AJAX POST
    // -------------------------------------------------------------------------

    public function reorder(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer();

        $validated = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $ids = $validated['ids'];

        // Verify all IDs belong to this customer
        $count = CustomerFavorite::where('customer_id', $customer->id)
            ->whereIn('id', $ids)
            ->count();

        if ($count !== count($ids)) {
            abort(403);
        }

        foreach ($ids as $position => $id) {
            CustomerFavorite::where('id', $id)->update(['sort_order' => $position]);
        }

        return response()->json(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // Add all orderable items to cart
    // -------------------------------------------------------------------------

    public function addAllToCart(Request $request): RedirectResponse
    {
        $user     = Auth::user();
        $customer = $this->requireCustomer();
        $perms    = new CustomerPermissions($user);

        if (! $perms->canOrderFromFavorites() && ! $perms->canOrderAll()) {
            abort(403, 'Keine Bestellberechtigung.');
        }

        $favorites = $customer->favorites()->with('product')->get();
        $added     = 0;

        foreach ($favorites as $fav) {
            $qty = $fav->orderQty();
            if ($qty > 0 && $fav->isOrderable()) {
                $this->cart->add($fav->product_id, $qty, $user);
                $added++;
            }
        }

        if ($added === 0) {
            return redirect()->route('account.favorites')->with('info', 'Keine bestellbaren Artikel (Sollbestand gedeckt oder kein Sollbestand gesetzt).');
        }

        return redirect()->route('account.favorites')
            ->with('success', "{$added} Artikel in den Warenkorb gelegt.");
    }

    // -------------------------------------------------------------------------
    // Add all to cart + redirect to checkout ("Direkt bestellen")
    // -------------------------------------------------------------------------

    public function orderAll(Request $request): RedirectResponse
    {
        $user     = Auth::user();
        $customer = $this->requireCustomer();
        $perms    = new CustomerPermissions($user);

        if (! $perms->canOrderFromFavorites() && ! $perms->canOrderAll()) {
            abort(403, 'Keine Bestellberechtigung.');
        }

        $favorites = $customer->favorites()->with('product')->get();
        $added     = 0;

        foreach ($favorites as $fav) {
            $qty = $fav->orderQty();
            if ($qty > 0 && $fav->isOrderable()) {
                $this->cart->add($fav->product_id, $qty, $user);
                $added++;
            }
        }

        if ($added === 0) {
            return redirect()->route('account.favorites')->with('info', 'Keine bestellbaren Artikel.');
        }

        return redirect()->route('checkout');
    }

    // -------------------------------------------------------------------------
    // Product search (for adding products) — JSON
    // -------------------------------------------------------------------------

    public function search(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer();

        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        // IDs already in favorites
        $existing = CustomerFavorite::where('customer_id', $customer->id)
            ->pluck('product_id')
            ->toArray();

        $products = Product::where('active', true)
            ->where('show_in_shop', true)
            ->whereNotIn('id', $existing)
            ->where(function ($query) use ($q) {
                $query->where('produktname', 'like', "%{$q}%")
                    ->orWhere('artikelnummer', 'like', "%{$q}%");
            })
            ->with('gebinde')
            ->limit(10)
            ->get(['id', 'produktname', 'artikelnummer', 'gebinde_id']);

        return response()->json($products->map(fn (Product $p) => [
            'id'      => $p->id,
            'name'    => $p->produktname,
            'sku'     => $p->artikelnummer,
            'gebinde' => $p->gebinde?->name,
        ]));
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function requireCustomer(): Customer
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->isSubUser()) {
            $subUser = $user->subUser;
            if (! $subUser?->active) {
                abort(403, 'Ihr Zugang wurde deaktiviert.');
            }
            $customer = $subUser->parentCustomer;
            if (! $customer) {
                abort(403, 'Kein Kundenkonto vorhanden.');
            }
            return $customer;
        }

        $customer = $user->customer;
        if ($customer === null) {
            abort(403, 'Kein Kundenkonto vorhanden.');
        }

        return $customer;
    }
}
