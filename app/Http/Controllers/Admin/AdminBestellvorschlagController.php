<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Warehouse;
use App\Services\Procurement\BestellvorschlagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * PROJ-32: Purchase order suggestions based on stock levels.
 */
class AdminBestellvorschlagController extends Controller
{
    public function __construct(
        private readonly BestellvorschlagService $service,
    ) {}

    /**
     * GET /admin/einkauf/bestellvorschlaege — Show products below reorder point.
     */
    public function index(Request $request): View
    {
        $company     = App::make('current_company');
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null;

        $proposals = $this->service->getProposals($warehouseId, $company?->id);

        $warehouses = Warehouse::where('company_id', $company?->id)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return view('admin.einkauf.bestellvorschlaege', compact('proposals', 'warehouses', 'warehouseId'));
    }
}
