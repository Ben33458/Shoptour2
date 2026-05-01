<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental\RentalItem;
use App\Models\Rental\RentalPackagingUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRentalPackagingUnitController extends Controller
{
    public function index(Request $request): View
    {
        $query = RentalPackagingUnit::with('rentalItem');
        if ($request->filled('item_id')) {
            $query->where('rental_item_id', $request->item_id);
        }
        $units = $query->orderBy('sort_order')->paginate(30);
        $items = RentalItem::where('inventory_mode', 'packaging_based')->orderBy('name')->get();
        return view('admin.rental.packaging-units.index', compact('units', 'items'));
    }

    public function create(Request $request): View
    {
        $items = RentalItem::where('inventory_mode', 'packaging_based')->where('active', true)->orderBy('name')->get();
        return view('admin.rental.packaging-units.create', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rental_item_id'  => 'required|exists:rental_items,id',
            'label'           => 'required|string|max:100',
            'pieces_per_pack' => 'required|integer|min:1',
            'sort_order'      => 'nullable|integer|min:0',
            'active'          => 'boolean',
            'available_packs' => 'required|integer|min:0',
        ]);
        $data['active'] = $request->boolean('active', true);
        RentalPackagingUnit::create($data);
        return redirect()->route('admin.rental.packaging-units.index')->with('success', 'VPE erstellt.');
    }

    public function edit(RentalPackagingUnit $packagingUnit): View
    {
        $items = RentalItem::where('active', true)->orderBy('name')->get();
        return view('admin.rental.packaging-units.edit', compact('packagingUnit', 'items'));
    }

    public function update(Request $request, RentalPackagingUnit $packagingUnit): RedirectResponse
    {
        $data = $request->validate([
            'label'           => 'required|string|max:100',
            'pieces_per_pack' => 'required|integer|min:1',
            'sort_order'      => 'nullable|integer|min:0',
            'active'          => 'boolean',
            'available_packs' => 'required|integer|min:0',
        ]);
        $data['active'] = $request->boolean('active');
        $packagingUnit->update($data);
        return redirect()->route('admin.rental.packaging-units.index')->with('success', 'VPE aktualisiert.');
    }

    public function destroy(RentalPackagingUnit $packagingUnit): RedirectResponse
    {
        $packagingUnit->delete();
        return redirect()->route('admin.rental.packaging-units.index')->with('success', 'VPE gelöscht.');
    }
}
