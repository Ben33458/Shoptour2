<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\System\SyncRun;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReconcileHubController extends Controller
{
    public function index(): View
    {
        $stats = [
            'customers_unmatched' => DB::table('customers')
                ->whereNotExists(fn ($q) => $q->from('source_matches')
                    ->whereColumn('local_id', 'customers.id')
                    ->where('entity_type', 'customer')
                    ->where('status', 'matched'))
                ->count(),
            'suppliers_unmatched' => DB::table('suppliers')
                ->whereNotExists(fn ($q) => $q->from('source_matches')
                    ->whereColumn('local_id', 'suppliers.id')
                    ->where('entity_type', 'supplier')
                    ->where('status', 'matched'))
                ->count(),
            'products_unmatched'  => DB::table('products')
                ->whereNotExists(fn ($q) => $q->from('source_matches')
                    ->whereColumn('local_id', 'products.id')
                    ->where('entity_type', 'product')
                    ->where('status', 'matched'))
                ->count(),
            'employees_unmatched' => DB::table('employees')
                ->whereNull('ninox_source_id')
                ->where('is_active', true)
                ->count(),
        ];

        $ninoxLastRun = SyncRun::lastSuccessfulFor('ninox');

        return view('admin.reconcile.hub', compact('stats', 'ninoxLastRun'));
    }
}
