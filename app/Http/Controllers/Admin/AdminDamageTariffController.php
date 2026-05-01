<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental\DamageTariff;
use App\Models\Rental\RentalItem;
use App\Models\Rental\RentalItemCategory;
use App\Models\Rental\RentalPackagingUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDamageTariffController extends Controller
{
    public function index(): View
    {
        $tariffs = DamageTariff::orderBy('applies_to_type')->orderBy('name')->paginate(30);
        return view('admin.rental.damage-tariffs.index', compact('tariffs'));
    }

    public function create(): View
    {
        $items          = RentalItem::where('active', true)->orderBy('name')->get();
        $categories     = RentalItemCategory::where('active', true)->orderBy('name')->get();
        $packagingUnits = RentalPackagingUnit::where('active', true)->with('rentalItem')->get();
        return view('admin.rental.damage-tariffs.create', compact('items', 'categories', 'packagingUnits'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'applies_to_type' => 'required|in:rental_item,category,packaging_unit',
            'applies_to_id'   => 'required|integer|min:1',
            'name'            => 'required|string|max:150',
            'amount_net_eur'  => 'required|numeric|min:0',
            'active'          => 'boolean',
            'notes'           => 'nullable|string',
        ]);
        $data['amount_net_milli'] = (int) round((float) $data['amount_net_eur'] * 1_000_000);
        $data['active']           = $request->boolean('active', true);
        unset($data['amount_net_eur']);
        DamageTariff::create($data);
        return redirect()->route('admin.rental.damage-tariffs.index')->with('success', 'Schadenstarif erstellt.');
    }

    public function edit(DamageTariff $damageTariff): View
    {
        $items          = RentalItem::where('active', true)->orderBy('name')->get();
        $categories     = RentalItemCategory::where('active', true)->orderBy('name')->get();
        $packagingUnits = RentalPackagingUnit::where('active', true)->with('rentalItem')->get();
        return view('admin.rental.damage-tariffs.edit', compact('damageTariff', 'items', 'categories', 'packagingUnits'));
    }

    public function update(Request $request, DamageTariff $damageTariff): RedirectResponse
    {
        $data = $request->validate([
            'applies_to_type' => 'required|in:rental_item,category,packaging_unit',
            'applies_to_id'   => 'required|integer|min:1',
            'name'            => 'required|string|max:150',
            'amount_net_eur'  => 'required|numeric|min:0',
            'active'          => 'boolean',
            'notes'           => 'nullable|string',
        ]);
        $data['amount_net_milli'] = (int) round((float) $data['amount_net_eur'] * 1_000_000);
        $data['active']           = $request->boolean('active');
        unset($data['amount_net_eur']);
        $damageTariff->update($data);
        return redirect()->route('admin.rental.damage-tariffs.index')->with('success', 'Schadenstarif aktualisiert.');
    }

    public function destroy(DamageTariff $damageTariff): RedirectResponse
    {
        $damageTariff->delete();
        return redirect()->route('admin.rental.damage-tariffs.index')->with('success', 'Schadenstarif gelöscht.');
    }
}
