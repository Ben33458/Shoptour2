<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PROJ-23 – Lagerbewegungen (stock_movements) Admin-Übersicht.
 *
 * Routes:
 *   GET /admin/stock-movements     index()
 */
class AdminStockMovementController extends Controller
{
    public function index(Request $request): View
    {
        $warehouseId  = $request->integer('warehouse_id') ?: null;
        $movementType = $request->string('type')->toString() ?: null;
        $date         = $request->string('date')->toString() ?: null;

        $query = StockMovement::with([
            'product:id,artikelnummer,produktname',
            'warehouse:id,name',
            'createdBy:id,first_name,last_name',
        ])->orderByDesc('created_at');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($movementType) {
            $query->where('movement_type', $movementType);
        }

        if ($date) {
            $query->whereDate('created_at', $date);
        }

        $movements  = $query->paginate(50)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->pluck('name', 'id');
        $types      = StockMovement::TYPES;

        return view('admin.stock-movements.index', compact(
            'movements', 'warehouses', 'types', 'warehouseId', 'movementType', 'date'
        ));
    }
}
