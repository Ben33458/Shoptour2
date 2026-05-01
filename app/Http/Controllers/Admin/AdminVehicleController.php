<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicles\Vehicle;
use App\Models\Vehicles\VehicleDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminVehicleController extends Controller
{
    public function index(): View
    {
        $vehicles = Vehicle::orderBy('internal_name')->paginate(20);
        return view('admin.vehicles.index', compact('vehicles'));
    }

    public function create(): View
    {
        return view('admin.vehicles.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'internal_name'              => 'required|string|max:100',
            'plate_number'               => 'required|string|max:20|unique:vehicles',
            'manufacturer'               => 'nullable|string|max:100',
            'model'                      => 'nullable|string|max:100',
            'vehicle_type'               => 'nullable|string|max:50',
            'vin'                        => 'nullable|string|max:50',
            'first_registration'         => 'nullable|date',
            'year'                       => 'nullable|integer|min:1900|max:2100',
            'active'                     => 'boolean',
            'location'                   => 'nullable|string|max:255',
            'notes'                      => 'nullable|string',
            'gross_vehicle_weight'       => 'nullable|integer|min:0',
            'empty_weight'               => 'nullable|integer|min:0',
            'payload_weight'             => 'nullable|integer|min:0',
            'load_volume'                => 'nullable|integer|min:0',
            'max_vpe_without_hand_truck' => 'nullable|integer|min:0',
            'max_vpe_with_hand_truck'    => 'nullable|integer|min:0',
            'load_length'                => 'nullable|integer|min:0',
            'load_width'                 => 'nullable|integer|min:0',
            'load_height'                => 'nullable|integer|min:0',
            'seats'                      => 'nullable|integer|min:0',
            'trailer_hitch'              => 'boolean',
            'max_trailer_load'           => 'nullable|integer|min:0',
            'cooling_unit'               => 'boolean',
            'required_license_class'     => 'nullable|string|max:10',
            'tuev_due_date'              => 'nullable|date',
            'inspection_due_date'        => 'nullable|date',
            'oil_service_due_date'       => 'nullable|date',
            'next_service_km'            => 'nullable|integer|min:0',
            'current_mileage'            => 'nullable|integer|min:0',
        ]);
        $data['active']        = $request->boolean('active', true);
        $data['trailer_hitch'] = $request->boolean('trailer_hitch');
        $data['cooling_unit']  = $request->boolean('cooling_unit');
        Vehicle::create($data);
        return redirect()->route('admin.vehicles.index')->with('success', 'Fahrzeug erstellt.');
    }

    public function show(Vehicle $vehicle): View
    {
        $vehicle->load([
            'documents',
            'assetIssues' => fn($q) => $q->whereIn('status', ['open', 'scheduled', 'in_progress']),
        ]);
        return view('admin.vehicles.show', compact('vehicle'));
    }

    public function edit(Vehicle $vehicle): View
    {
        return view('admin.vehicles.edit', compact('vehicle'));
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'internal_name'              => 'required|string|max:100',
            'plate_number'               => 'required|string|max:20|unique:vehicles,plate_number,' . $vehicle->id,
            'manufacturer'               => 'nullable|string|max:100',
            'model'                      => 'nullable|string|max:100',
            'vehicle_type'               => 'nullable|string|max:50',
            'vin'                        => 'nullable|string|max:50',
            'first_registration'         => 'nullable|date',
            'year'                       => 'nullable|integer|min:1900|max:2100',
            'active'                     => 'boolean',
            'location'                   => 'nullable|string|max:255',
            'notes'                      => 'nullable|string',
            'gross_vehicle_weight'       => 'nullable|integer|min:0',
            'empty_weight'               => 'nullable|integer|min:0',
            'payload_weight'             => 'nullable|integer|min:0',
            'load_volume'                => 'nullable|integer|min:0',
            'max_vpe_without_hand_truck' => 'nullable|integer|min:0',
            'max_vpe_with_hand_truck'    => 'nullable|integer|min:0',
            'load_length'                => 'nullable|integer|min:0',
            'load_width'                 => 'nullable|integer|min:0',
            'load_height'                => 'nullable|integer|min:0',
            'seats'                      => 'nullable|integer|min:0',
            'trailer_hitch'              => 'boolean',
            'max_trailer_load'           => 'nullable|integer|min:0',
            'cooling_unit'               => 'boolean',
            'required_license_class'     => 'nullable|string|max:10',
            'tuev_due_date'              => 'nullable|date',
            'inspection_due_date'        => 'nullable|date',
            'oil_service_due_date'       => 'nullable|date',
            'next_service_km'            => 'nullable|integer|min:0',
            'current_mileage'            => 'nullable|integer|min:0',
        ]);
        $data['active']        = $request->boolean('active');
        $data['trailer_hitch'] = $request->boolean('trailer_hitch');
        $data['cooling_unit']  = $request->boolean('cooling_unit');
        $vehicle->update($data);
        return redirect()->route('admin.vehicles.show', $vehicle)->with('success', 'Fahrzeug aktualisiert.');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $vehicle->delete();
        return redirect()->route('admin.vehicles.index')->with('success', 'Fahrzeug gelöscht.');
    }

    public function storeDocument(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => 'required|string|in:fahrzeugschein,pruefbericht,versicherung,hauptuntersuchung,sonstiges',
            'title'         => 'required|string|max:255',
            'file'          => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'valid_until'   => 'nullable|date',
            'notes'         => 'nullable|string',
        ]);
        $path = $request->file('file')->store('vehicles/documents', 'local');
        VehicleDocument::create([
            'vehicle_id'    => $vehicle->id,
            'document_type' => $data['document_type'],
            'title'         => $data['title'],
            'file_path'     => $path,
            'valid_until'   => $data['valid_until'] ?? null,
            'notes'         => $data['notes'] ?? null,
        ]);
        return back()->with('success', 'Dokument hochgeladen.');
    }

    public function destroyDocument(Vehicle $vehicle, VehicleDocument $document): RedirectResponse
    {
        Storage::disk('local')->delete($document->file_path);
        $document->delete();
        return back()->with('success', 'Dokument gelöscht.');
    }
}
