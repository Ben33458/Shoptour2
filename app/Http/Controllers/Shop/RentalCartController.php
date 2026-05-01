<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Rental\RentalCartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RentalCartController extends Controller
{
    public function __construct(
        private readonly RentalCartService $cart,
    ) {}

    public function show(): RedirectResponse
    {
        return redirect()->route('cart.index');
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rental_item_id'    => 'required|exists:rental_items,id',
            'qty'               => 'required|integer|min:1|max:9999',
            'packaging_unit_id' => 'nullable|exists:rental_packaging_units,id',
        ]);

        $this->cart->addItem(
            (int) $data['rental_item_id'],
            (int) $data['qty'],
            isset($data['packaging_unit_id']) ? (int) $data['packaging_unit_id'] : null,
        );

        return redirect()->back()
            ->with('success', 'Artikel in den Warenkorb gelegt.');
    }

    public function update(Request $request, int $itemId): RedirectResponse
    {
        $data = $request->validate([
            'qty' => 'required|integer|min:0|max:9999',
        ]);

        if ((int) $data['qty'] === 0) {
            $this->cart->removeItem($itemId);
            return back()->with('success', 'Artikel entfernt.');
        }

        $this->cart->updateItemQty($itemId, (int) $data['qty']);
        return back()->with('success', 'Menge aktualisiert.');
    }

    public function remove(int $itemId): RedirectResponse
    {
        $this->cart->removeItem($itemId);
        return back()->with('success', 'Artikel entfernt.');
    }

    public function clear(): RedirectResponse
    {
        $this->cart->clear();
        return redirect()->route('rental.landing')
            ->with('success', 'Leih-Warenkorb geleert.');
    }
}
