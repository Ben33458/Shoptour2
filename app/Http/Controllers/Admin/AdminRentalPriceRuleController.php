<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental\RentalItem;
use App\Models\Rental\RentalPriceRule;
use App\Models\Rental\RentalTimeModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRentalPriceRuleController extends Controller
{
    public function index(Request $request): View
    {
        $query = RentalPriceRule::with(['rentalItem', 'timeModel', 'packagingUnit']);
        if ($request->filled('item_id')) {
            $query->where('rental_item_id', $request->item_id);
        }
        $rules = $query->orderBy('rental_item_id')->orderBy('rental_time_model_id')->paginate(30);
        $items = RentalItem::where('active', true)->orderBy('name')->get();
        return view('admin.rental.price-rules.index', compact('rules', 'items'));
    }

    public function create(Request $request): View
    {
        $items        = RentalItem::where('active', true)->orderBy('name')->get();
        $timeModels   = RentalTimeModel::where('active', true)->orderBy('sort_order')->get();
        $selectedItem = $request->filled('item_id')
            ? RentalItem::with('packagingUnits')->find($request->item_id)
            : null;
        return view('admin.rental.price-rules.create', compact('items', 'timeModels', 'selectedItem'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rental_item_id'       => 'required|exists:rental_items,id',
            'rental_time_model_id' => 'required|exists:rental_time_models,id',
            'packaging_unit_id'    => 'nullable|exists:rental_packaging_units,id',
            'min_quantity'         => 'required|integer|min:1',
            'max_quantity'         => 'nullable|integer|min:1',
            'price_type'           => 'required|in:per_item,per_pack,per_set,flat',
            'price_net_eur'        => 'required|numeric|min:0',
            'valid_from'           => 'nullable|date',
            'valid_until'          => 'nullable|date|after_or_equal:valid_from',
            'customer_group_id'    => 'nullable|exists:customer_groups,id',
            'requires_drink_order' => 'boolean',
        ]);
        $data['price_net_milli']      = (int) round((float) $data['price_net_eur'] * 1_000_000);
        $data['requires_drink_order'] = $request->boolean('requires_drink_order');
        unset($data['price_net_eur']);
        RentalPriceRule::create($data);
        return redirect()->route('admin.rental.price-rules.index')->with('success', 'Preisregel erstellt.');
    }

    public function edit(RentalPriceRule $priceRule): View
    {
        $items      = RentalItem::where('active', true)->orderBy('name')->get();
        $timeModels = RentalTimeModel::where('active', true)->orderBy('sort_order')->get();
        $priceRule->load('rentalItem.packagingUnits');
        return view('admin.rental.price-rules.edit', compact('priceRule', 'items', 'timeModels'));
    }

    public function update(Request $request, RentalPriceRule $priceRule): RedirectResponse
    {
        $data = $request->validate([
            'packaging_unit_id'    => 'nullable|exists:rental_packaging_units,id',
            'min_quantity'         => 'required|integer|min:1',
            'max_quantity'         => 'nullable|integer|min:1',
            'price_type'           => 'required|in:per_item,per_pack,per_set,flat',
            'price_net_eur'        => 'required|numeric|min:0',
            'valid_from'           => 'nullable|date',
            'valid_until'          => 'nullable|date|after_or_equal:valid_from',
            'customer_group_id'    => 'nullable|exists:customer_groups,id',
            'requires_drink_order' => 'boolean',
        ]);
        $data['price_net_milli']      = (int) round((float) $data['price_net_eur'] * 1_000_000);
        $data['requires_drink_order'] = $request->boolean('requires_drink_order');
        unset($data['price_net_eur']);
        $priceRule->update($data);
        return redirect()->route('admin.rental.price-rules.index')->with('success', 'Preisregel aktualisiert.');
    }

    public function destroy(RentalPriceRule $priceRule): RedirectResponse
    {
        $priceRule->delete();
        return redirect()->route('admin.rental.price-rules.index')->with('success', 'Preisregel gelöscht.');
    }
}
