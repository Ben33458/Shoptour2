<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental\RentalInventoryUnit;
use App\Models\Rental\RentalItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRentalInventoryUnitController extends Controller
{
    public function index(Request $request): View
    {
        $query = RentalInventoryUnit::with('rentalItem');
        if ($request->filled('item_id')) {
            $query->where('rental_item_id', $request->item_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $units    = $query->orderBy('inventory_number')->paginate(30);
        $items    = RentalItem::where('inventory_mode', 'unit_based')->orderBy('name')->get();
        $statuses = ['available', 'reserved', 'in_use', 'maintenance', 'defective', 'retired'];
        return view('admin.rental.inventory-units.index', compact('units', 'items', 'statuses'));
    }

    public function create(Request $request): View
    {
        $items        = RentalItem::where('active', true)->orderBy('name')->get();
        $selectedItem = $request->filled('item_id') ? RentalItem::find($request->item_id) : null;
        return view('admin.rental.inventory-units.create', compact('items', 'selectedItem'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rental_item_id'        => 'required|exists:rental_items,id',
            'inventory_number'      => 'required|string|max:50|unique:rental_inventory_units',
            'serial_number'         => 'nullable|string|max:100',
            'title'                 => 'required|string|max:255',
            'status'                => 'required|in:available,reserved,in_use,maintenance,defective,retired',
            'condition_notes'       => 'nullable|string',
            'location'              => 'nullable|string|max:255',
            'preferred_for_booking' => 'boolean',
        ]);
        $data['preferred_for_booking'] = $request->boolean('preferred_for_booking');
        RentalInventoryUnit::create($data);
        return redirect()->route('admin.rental.inventory-units.index')->with('success', 'Inventareinheit erstellt.');
    }

    public function show(RentalInventoryUnit $inventoryUnit): View
    {
        $inventoryUnit->load('rentalItem', 'assetIssues.reportedBy');
        return view('admin.rental.inventory-units.show', compact('inventoryUnit'));
    }

    public function edit(RentalInventoryUnit $inventoryUnit): View
    {
        $items    = RentalItem::where('active', true)->orderBy('name')->get();
        $statuses = ['available', 'reserved', 'in_use', 'maintenance', 'defective', 'retired'];
        $issues   = $inventoryUnit->assetIssues()->whereIn('status', ['open', 'scheduled', 'in_progress'])->get();
        return view('admin.rental.inventory-units.edit', compact('inventoryUnit', 'items', 'statuses', 'issues'));
    }

    public function update(Request $request, RentalInventoryUnit $inventoryUnit): RedirectResponse
    {
        $data = $request->validate([
            'rental_item_id'        => 'required|exists:rental_items,id',
            'inventory_number'      => 'required|string|max:50|unique:rental_inventory_units,inventory_number,' . $inventoryUnit->id,
            'serial_number'         => 'nullable|string|max:100',
            'title'                 => 'required|string|max:255',
            'status'                => 'required|in:available,reserved,in_use,maintenance,defective,retired',
            'condition_notes'       => 'nullable|string',
            'location'              => 'nullable|string|max:255',
            'preferred_for_booking' => 'boolean',
        ]);
        $data['preferred_for_booking'] = $request->boolean('preferred_for_booking');
        $inventoryUnit->update($data);
        return redirect()->route('admin.rental.inventory-units.index')->with('success', 'Inventareinheit aktualisiert.');
    }

    public function destroy(RentalInventoryUnit $inventoryUnit): RedirectResponse
    {
        if ($inventoryUnit->allocations()->whereNotIn('status', ['cancelled', 'returned'])->exists()) {
            return back()->with('error', 'Einheit hat aktive Buchungen und kann nicht gelöscht werden.');
        }
        $inventoryUnit->delete();
        return redirect()->route('admin.rental.inventory-units.index')->with('success', 'Inventareinheit gelöscht.');
    }
}
