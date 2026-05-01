<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental\CleaningFeeRule;
use App\Models\Rental\DepositRule;
use App\Models\Rental\RentalBookingItem;
use App\Models\Rental\RentalItem;
use App\Models\Rental\RentalItemCategory;
use App\Models\Rental\RentalTimeModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminRentalItemController extends Controller
{
    public function index(): View
    {
        $items = RentalItem::with('category')
            ->addSelect([
                'last_ordered_at' => RentalBookingItem::selectRaw('MAX(orders.created_at)')
                    ->join('orders', 'rental_booking_items.order_id', '=', 'orders.id')
                    ->whereColumn('rental_booking_items.rental_item_id', 'rental_items.id'),
            ])
            ->orderByDesc('active')       // Aktive zuerst, inaktive zuletzt
            ->orderByDesc('last_ordered_at') // Zuletzt bestellt zuerst (NULL = nie bestellt, landet ganz unten)
            ->orderBy('name')             // Alphabetisch als letztes Tiebreaker-Kriterium
            ->paginate(30);
        return view('admin.rental.items.index', compact('items'));
    }

    public function create(): View
    {
        $categories    = RentalItemCategory::where('active', true)->orderBy('sort_order')->get();
        $timeModels    = RentalTimeModel::where('active', true)->orderBy('sort_order')->get();
        $depositRules  = DepositRule::where('active', true)->get();
        $cleaningFeeRules = CleaningFeeRule::where('active', true)->get();
        return view('admin.rental.items.create', compact('categories', 'timeModels', 'depositRules', 'cleaningFeeRules'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'article_number'          => 'nullable|string|max:50',
            'name'                    => 'required|string|max:255',
            'description'             => 'nullable|string',
            'category_id'             => 'nullable|exists:rental_item_categories,id',
            'active'                  => 'boolean',
            'visible_in_shop'         => 'boolean',
            'billing_mode'            => 'required|in:per_rental_period',
            'inventory_mode'          => 'required|in:unit_based,quantity_based,component_based,packaging_based',
            'transport_class'         => 'required|in:small,normal,truck',
            'allow_overbooking'       => 'boolean',
            'price_on_request'        => 'boolean',
            'total_quantity'          => 'nullable|integer|min:0',
            'unit_label'              => 'nullable|string|max:50',
            'deposit_rule_id'         => 'nullable|exists:deposit_rules,id',
            'cleaning_fee_rule_id'    => 'nullable|exists:cleaning_fee_rules,id',
            'preferred_time_model_id' => 'nullable|exists:rental_time_models,id',
            'internal_notes'          => 'nullable|string',
        ]);
        $data['slug']               = Str::slug($data['name']);
        $data['active']             = $request->boolean('active', true);
        $data['visible_in_shop']    = $request->boolean('visible_in_shop');
        $data['allow_overbooking']  = $request->boolean('allow_overbooking');
        $data['price_on_request']   = $request->boolean('price_on_request');
        $data['requires_event_order'] = true;
        RentalItem::create($data);
        return redirect()->route('admin.rental.items.index')->with('success', 'Mietartikel erstellt.');
    }

    public function show(RentalItem $item): View
    {
        $item->load(['category', 'inventoryUnits', 'packagingUnits', 'priceRules.timeModel', 'priceRules.customerGroup', 'components.component']);
        return view('admin.rental.items.show', compact('item'));
    }

    public function edit(RentalItem $item): View
    {
        $categories    = RentalItemCategory::where('active', true)->orderBy('sort_order')->get();
        $timeModels    = RentalTimeModel::where('active', true)->orderBy('sort_order')->get();
        $depositRules  = DepositRule::where('active', true)->get();
        $cleaningFeeRules = CleaningFeeRule::where('active', true)->get();
        return view('admin.rental.items.edit', compact('item', 'categories', 'timeModels', 'depositRules', 'cleaningFeeRules'));
    }

    public function update(Request $request, RentalItem $item): RedirectResponse
    {
        $data = $request->validate([
            'article_number'          => 'nullable|string|max:50',
            'name'                    => 'required|string|max:255',
            'description'             => 'nullable|string',
            'category_id'             => 'nullable|exists:rental_item_categories,id',
            'active'                  => 'boolean',
            'visible_in_shop'         => 'boolean',
            'inventory_mode'          => 'required|in:unit_based,quantity_based,component_based,packaging_based',
            'transport_class'         => 'required|in:small,normal,truck',
            'allow_overbooking'       => 'boolean',
            'price_on_request'        => 'boolean',
            'total_quantity'          => 'nullable|integer|min:0',
            'unit_label'              => 'nullable|string|max:50',
            'deposit_rule_id'         => 'nullable|exists:deposit_rules,id',
            'cleaning_fee_rule_id'    => 'nullable|exists:cleaning_fee_rules,id',
            'preferred_time_model_id' => 'nullable|exists:rental_time_models,id',
            'internal_notes'          => 'nullable|string',
        ]);
        $data['active']            = $request->boolean('active');
        $data['visible_in_shop']   = $request->boolean('visible_in_shop');
        $data['allow_overbooking'] = $request->boolean('allow_overbooking');
        $data['price_on_request']  = $request->boolean('price_on_request');
        $item->update($data);
        return redirect()->route('admin.rental.items.show', $item)->with('success', 'Mietartikel aktualisiert.');
    }

    public function destroy(RentalItem $item): RedirectResponse
    {
        if ($item->inventoryUnits()->exists() || $item->packagingUnits()->exists()) {
            return back()->with('error', 'Mietartikel hat noch Inventareinheiten/VPE und kann nicht gelöscht werden.');
        }
        $item->delete();
        return redirect()->route('admin.rental.items.index')->with('success', 'Mietartikel gelöscht.');
    }
}
