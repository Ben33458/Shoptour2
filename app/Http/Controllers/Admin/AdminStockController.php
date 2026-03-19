<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductStock;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PROJ-23 – Produktbestände (product_stocks) Admin-Übersicht.
 *
 * Routes:
 *   GET /admin/stock     index()
 */
class AdminStockController extends Controller
{
    public function index(Request $request): View
    {
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $query = ProductStock::with([
            'product:id,artikelnummer,produktname',
            'warehouse:id,name',
        ]);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $stocks     = $query->orderByDesc('quantity')->paginate(50)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->pluck('name', 'id');

        return view('admin.stock.index', compact('stocks', 'warehouses', 'warehouseId'));
    }
}
