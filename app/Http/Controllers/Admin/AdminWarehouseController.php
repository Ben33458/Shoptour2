<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PROJ-23 – Lagerorte (Warehouses) Admin-CRUD.
 *
 * Routes:
 *   GET    /admin/warehouses              index()
 *   GET    /admin/warehouses/create       create()
 *   POST   /admin/warehouses              store()
 *   GET    /admin/warehouses/{id}         show()
 *   GET    /admin/warehouses/{id}/edit    edit()
 *   PUT    /admin/warehouses/{id}         update()
 *   DELETE /admin/warehouses/{id}         destroy()
 */
class AdminWarehouseController extends Controller
{
    public function index(): View
    {
        $warehouses = Warehouse::withCount('stocks')
            ->orderBy('name')
            ->paginate(25);

        return view('admin.warehouses.index', compact('warehouses'));
    }

    public function create(): View
    {
        return view('admin.warehouses.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:255', 'unique:warehouses,name'],
            'location'           => ['nullable', 'string', 'max:255'],
            'active'             => ['boolean'],
            'is_pickup_location' => ['boolean'],
        ]);

        $validated['active']             = $request->boolean('active', true);
        $validated['is_pickup_location'] = $request->boolean('is_pickup_location', false);

        $warehouse = Warehouse::create($validated);

        return redirect()->route('admin.warehouses.index')
            ->with('success', "Lagerort \"{$warehouse->name}\" angelegt.");
    }

    public function show(Warehouse $warehouse): View
    {
        $stocks = $warehouse->stocks()
            ->with('product:id,artikelnummer,produktname')
            ->orderByDesc('quantity')
            ->paginate(50);

        return view('admin.warehouses.show', compact('warehouse', 'stocks'));
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('admin.warehouses.edit', compact('warehouse'));
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:255', 'unique:warehouses,name,' . $warehouse->id],
            'location'           => ['nullable', 'string', 'max:255'],
            'active'             => ['boolean'],
            'is_pickup_location' => ['boolean'],
        ]);

        $validated['active']             = $request->boolean('active', true);
        $validated['is_pickup_location'] = $request->boolean('is_pickup_location', false);

        $warehouse->update($validated);

        return redirect()->route('admin.warehouses.index')
            ->with('success', "Lagerort \"{$warehouse->name}\" gespeichert.");
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if ($warehouse->stocks()->exists()) {
            return redirect()->route('admin.warehouses.index')
                ->with('error', "Lagerort \"{$warehouse->name}\" kann nicht gelöscht werden, da noch Bestände vorhanden sind.");
        }

        $name = $warehouse->name;
        $warehouse->delete();

        return redirect()->route('admin.warehouses.index')
            ->with('success', "Lagerort \"{$name}\" gelöscht.");
    }
}
