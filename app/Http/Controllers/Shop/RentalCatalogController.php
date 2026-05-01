<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Rental\RentalItem;
use App\Models\Rental\RentalItemCategory;
use App\Models\Rental\RentalTimeModel;
use App\Services\Rental\RentalAvailabilityService;
use App\Services\Rental\RentalCartService;
use App\Services\Rental\RentalPricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RentalCatalogController extends Controller
{
    public function __construct(
        private readonly RentalCartService $cart,
        private readonly RentalAvailabilityService $availability,
        private readonly RentalPricingService $pricing,
    ) {}

    public function landing(): View
    {
        $timeModels = RentalTimeModel::where('active', true)->orderBy('sort_order')->get();
        $cart       = $this->cart->get();
        return view('shop.rental.landing', compact('timeModels', 'cart'));
    }

    public function setDates(Request $request)
    {
        $data = $request->validate([
            'date_from'     => 'required|date|after_or_equal:today',
            'date_until'    => 'required|date|after_or_equal:date_from',
            'time_model_id' => 'nullable|exists:rental_time_models,id',
        ]);

        $this->cart->setDates($data['date_from'], $data['date_until']);
        if (! empty($data['time_model_id'])) {
            $this->cart->setTimeModel((int) $data['time_model_id']);
        }

        return redirect()->route('rental.catalog');
    }

    public function catalog(): View
    {
        $from   = $this->cart->getDateFrom();
        $until  = $this->cart->getDateUntil();
        $search = trim(request()->input('q', ''));

        $categories = RentalItemCategory::where('active', true)
            ->with(['items' => function ($q) use ($search) {
                $q->where('active', true);
                if ($search !== '') {
                    $q->where(function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                           ->orWhere('description', 'like', "%{$search}%")
                           ->orWhere('article_number', 'like', "%{$search}%");
                    });
                }
                $q->orderBy('name');
            }, 'items.packagingUnits' => fn($q) => $q->where('active', true)])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn($cat) => $cat->items->isNotEmpty());

        $timeModel  = $this->cart->getTimeModel();
        $cartItems  = $this->cart->get()['items'] ?? [];

        // Availability map: item_id => available_qty
        $availMap = [];
        if ($from && $until) {
            foreach ($categories->flatMap->items as $item) {
                $availMap[$item->id] = $this->availability->getAvailable($item, $from, $until);
            }
        }

        // Always use the default event time model for pricing display
        // (session may contain a different model from before the fix)
        $priceTimeModel = RentalTimeModel::where('default_for_events', true)->where('active', true)->first()
            ?: ($timeModel ?? RentalTimeModel::where('active', true)->orderBy('sort_order')->first());

        // Price map: item_id => [milli, label]
        $priceMap = [];
        if ($priceTimeModel) {
            foreach ($categories->flatMap->items as $item) {
                if ($item->price_on_request) {
                    $priceMap[$item->id] = ['milli' => null, 'label' => null, 'on_request' => true];
                    continue;
                }
                // For packaging_based: show per-piece price (packaging_unit_id=null rule)
                // For others: per-item price
                $price = $this->pricing->resolveUnitPriceMilli($item, $priceTimeModel, 1, null);
                $label = $item->unit_label ?? 'Stück';
                $priceMap[$item->id] = ['milli' => $price, 'label' => $label, 'on_request' => false];
            }
        }

        return view('shop.rental.catalog', compact(
            'categories', 'from', 'until', 'timeModel', 'availMap', 'priceMap', 'cartItems', 'search'
        ));
    }

    public function item(RentalItem $item): View
    {
        $item->load(['category', 'packagingUnits', 'priceRules.timeModel']);

        $from       = $this->cart->getDateFrom();
        $until      = $this->cart->getDateUntil();
        $timeModel  = $this->cart->getTimeModel();
        $cartItems  = $this->cart->get()['items'] ?? [];

        $available  = null;
        if ($from && $until) {
            $available = $this->availability->getAvailable($item, $from, $until);
        }

        // Always use the default event time model for pricing
        $priceTimeModel = RentalTimeModel::where('default_for_events', true)->where('active', true)->first()
            ?: ($timeModel ?? RentalTimeModel::where('active', true)->orderBy('sort_order')->first());

        $priceMap = [];
        if ($priceTimeModel) {
            // Price per packaging unit option
            if ($item->packagingUnits->isNotEmpty()) {
                foreach ($item->packagingUnits->where('active', true) as $pu) {
                    $priceMap[$pu->id] = $this->pricing->resolveUnitPriceMilli(
                        $item, $priceTimeModel, 1, $pu->id
                    );
                }
            }
            $priceMap['base'] = $this->pricing->resolveUnitPriceMilli($item, $priceTimeModel, 1);
        }

        $cartEntry = $cartItems[(string) $item->id] ?? null;

        return view('shop.rental.item', compact(
            'item', 'from', 'until', 'timeModel', 'available', 'priceMap', 'cartEntry'
        ));
    }
}
