<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental\RentalInventoryUnit;
use App\Models\Rental\RentalItem;
use App\Models\Rental\RentalItemCategory;
use App\Models\Rental\RentalPackagingUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRentalInventoryController extends Controller
{
    public function index(): View
    {
        $categories = RentalItemCategory::where('active', true)
            ->with(['items' => function ($q) {
                $q->with(['inventoryUnits', 'packagingUnits'])
                  ->orderBy('name');
            }])
            ->orderBy('sort_order')
            ->get();

        return view('admin.rental.inventory.index', compact('categories'));
    }

    /**
     * Update total_quantity. For packaging_based items, also recalculates available_packs per VPE.
     */
    public function updateQty(Request $request, RentalItem $item): RedirectResponse
    {
        $data = $request->validate([
            'total_quantity' => 'required|integer|min:0',
        ]);

        $item->update(['total_quantity' => $data['total_quantity']]);

        if ($item->inventory_mode === 'packaging_based') {
            foreach ($item->packagingUnits()->where('active', true)->get() as $pu) {
                $pu->update(['available_packs' => (int) floor($data['total_quantity'] / $pu->pieces_per_pack)]);
            }
        }

        return back()->with('success', "Bestand für \"{$item->name}\" aktualisiert.");
    }

    /**
     * Update available_packs for a packaging unit.
     */
    public function updatePacks(Request $request, RentalPackagingUnit $packagingUnit): RedirectResponse
    {
        $data = $request->validate([
            'available_packs' => 'required|integer|min:0',
        ]);

        $packagingUnit->update(['available_packs' => $data['available_packs']]);

        return back()->with('success', "Bestand für VPE \"{$packagingUnit->label}\" aktualisiert.");
    }

    /**
     * Store a new inventory unit for a unit_based item.
     */
    public function storeUnit(Request $request, RentalItem $item): RedirectResponse
    {
        $data = $request->validate([
            'inventory_number' => 'required|string|max:50|unique:rental_inventory_units,inventory_number',
            'title'            => 'required|string|max:255',
            'status'           => 'required|in:available,reserved,maintenance,defective,retired',
            'condition_notes'  => 'nullable|string',
            'location'         => 'nullable|string|max:255',
        ]);

        $item->inventoryUnits()->create(array_merge($data, [
            'company_id' => $item->company_id,
        ]));

        return back()->with('success', "Inventareinheit \"{$data['title']}\" angelegt.");
    }

    /**
     * Update an existing inventory unit's status/condition.
     */
    public function updateUnit(Request $request, RentalInventoryUnit $unit): RedirectResponse
    {
        $data = $request->validate([
            'status'          => 'required|in:available,reserved,maintenance,defective,retired',
            'condition_notes' => 'nullable|string',
            'location'        => 'nullable|string|max:255',
        ]);

        $unit->update($data);

        return back()->with('success', "Inventareinheit \"{$unit->title}\" aktualisiert.");
    }

    /**
     * Delete an inventory unit.
     */
    public function destroyUnit(RentalInventoryUnit $unit): RedirectResponse
    {
        if ($unit->allocations()->whereNotIn('status', ['cancelled', 'returned'])->exists()) {
            return back()->with('error', 'Diese Einheit hat aktive Buchungen und kann nicht gelöscht werden.');
        }

        $title = $unit->title;
        $unit->delete();

        return back()->with('success', "Inventareinheit \"{$title}\" gelöscht.");
    }
}
