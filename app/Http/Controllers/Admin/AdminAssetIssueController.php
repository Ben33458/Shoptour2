<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assets\AssetIssue;
use App\Models\Rental\RentalInventoryUnit;
use App\Models\Vehicles\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminAssetIssueController extends Controller
{
    public function index(Request $request): View
    {
        $query = AssetIssue::with('createdBy', 'assignedTo')->orderBy('created_at', 'desc');
        if ($request->filled('asset_type')) {
            $query->where('asset_type', $request->asset_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Default: nur offene Mängel anzeigen
            $query->whereIn('status', ['open', 'scheduled', 'in_progress']);
        }
        $issues   = $query->paginate(30);
        $vehicles = Vehicle::where('active', true)->orderBy('internal_name')->get();
        $units    = RentalInventoryUnit::orderBy('inventory_number')->get();
        return view('admin.assets.issues.index', compact('issues', 'vehicles', 'units'));
    }

    public function create(Request $request): View
    {
        $vehicles = Vehicle::where('active', true)->orderBy('internal_name')->get();
        $units    = RentalInventoryUnit::orderBy('inventory_number')->get();
        $users    = \App\Models\User::orderBy('name')->get();
        return view('admin.assets.issues.create', compact('vehicles', 'units', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asset_type'         => 'required|in:vehicle,rental_inventory_unit',
            'asset_id'           => 'required|integer|min:1',
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'priority'           => 'required|in:low,medium,high,critical',
            'severity'           => 'required|in:minor,moderate,major',
            'blocks_usage'       => 'boolean',
            'blocks_rental'      => 'boolean',
            'estimated_cost_eur' => 'nullable|numeric|min:0',
            'workshop_name'      => 'nullable|string|max:255',
            'due_date'           => 'nullable|date',
            'assigned_to'        => 'nullable|exists:users,id',
        ]);
        $data['created_by']    = Auth::id();
        $data['blocks_usage']  = $request->boolean('blocks_usage');
        $data['blocks_rental'] = $request->boolean('blocks_rental');
        if (!empty($data['estimated_cost_eur'])) {
            $data['estimated_cost_milli'] = (int) round((float) $data['estimated_cost_eur'] * 1_000_000);
        }
        unset($data['estimated_cost_eur']);

        // If blocks_rental: mark inventory unit as defective
        if ($data['blocks_rental'] && $data['asset_type'] === 'rental_inventory_unit') {
            RentalInventoryUnit::find($data['asset_id'])?->update(['status' => 'defective']);
        }
        // If blocks_usage for vehicle: mark vehicle note
        if ($data['blocks_usage'] && $data['asset_type'] === 'vehicle') {
            $vehicle = Vehicle::find($data['asset_id']);
            $vehicle?->update(['active' => false]);
        }

        AssetIssue::create($data);
        return redirect()->route('admin.assets.issues.index')->with('success', 'Mangel erfasst.');
    }

    public function edit(AssetIssue $issue): View
    {
        $vehicles = Vehicle::where('active', true)->orderBy('internal_name')->get();
        $units    = RentalInventoryUnit::orderBy('inventory_number')->get();
        $users    = \App\Models\User::orderBy('name')->get();
        return view('admin.assets.issues.edit', compact('issue', 'vehicles', 'units', 'users'));
    }

    public function update(Request $request, AssetIssue $issue): RedirectResponse
    {
        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'priority'           => 'required|in:low,medium,high,critical',
            'status'             => 'required|in:open,scheduled,in_progress,resolved,closed',
            'severity'           => 'required|in:minor,moderate,major',
            'blocks_usage'       => 'boolean',
            'blocks_rental'      => 'boolean',
            'estimated_cost_eur' => 'nullable|numeric|min:0',
            'workshop_name'      => 'nullable|string|max:255',
            'due_date'           => 'nullable|date',
            'assigned_to'        => 'nullable|exists:users,id',
            'resolution_notes'   => 'nullable|string',
        ]);
        $data['blocks_usage']  = $request->boolean('blocks_usage');
        $data['blocks_rental'] = $request->boolean('blocks_rental');
        if (!empty($data['estimated_cost_eur'])) {
            $data['estimated_cost_milli'] = (int) round((float) $data['estimated_cost_eur'] * 1_000_000);
        }
        unset($data['estimated_cost_eur']);

        if (in_array($data['status'], ['resolved', 'closed']) && !$issue->resolved_at) {
            $data['resolved_at'] = now();
        }

        // Restore inventory unit status when resolved/closed
        if (in_array($data['status'], ['resolved', 'closed']) && !$data['blocks_rental']
            && $issue->asset_type === 'rental_inventory_unit') {
            RentalInventoryUnit::find($issue->asset_id)?->update(['status' => 'available']);
        }

        $issue->update($data);
        return redirect()->route('admin.assets.issues.index')->with('success', 'Mangel aktualisiert.');
    }
}
