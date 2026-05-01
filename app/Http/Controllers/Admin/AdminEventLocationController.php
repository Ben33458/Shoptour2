<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event\EventLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminEventLocationController extends Controller
{
    public function index(): View
    {
        $locations = EventLocation::orderBy('name')->paginate(30);
        return view('admin.event.locations.index', compact('locations'));
    }

    public function create(): View
    {
        return view('admin.event.locations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'street'  => 'nullable|string|max:255',
            'zip'     => 'nullable|string|max:20',
            'city'    => 'nullable|string|max:100',
            'country' => 'nullable|string|max:10',
            'geo_lat' => 'nullable|numeric',
            'geo_lng' => 'nullable|numeric',
            'notes'   => 'nullable|string',
            'active'  => 'boolean',
        ]);
        $data['active']      = $request->boolean('active', true);
        $data['source_type'] = 'manual';
        if (isset($data['geo_lat'])) $data['geo_lat'] = round((float) $data['geo_lat'], 5);
        if (isset($data['geo_lng'])) $data['geo_lng'] = round((float) $data['geo_lng'], 5);
        EventLocation::create($data);
        return redirect()->route('admin.event.locations.index')->with('success', 'Eventort erstellt.');
    }

    public function edit(EventLocation $location): View
    {
        return view('admin.event.locations.edit', compact('location'));
    }

    public function update(Request $request, EventLocation $location): RedirectResponse
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'street'  => 'nullable|string|max:255',
            'zip'     => 'nullable|string|max:20',
            'city'    => 'nullable|string|max:100',
            'country' => 'nullable|string|max:10',
            'geo_lat' => 'nullable|numeric',
            'geo_lng' => 'nullable|numeric',
            'notes'   => 'nullable|string',
            'active'  => 'boolean',
        ]);
        $data['active'] = $request->boolean('active');
        if (isset($data['geo_lat'])) $data['geo_lat'] = round((float) $data['geo_lat'], 5);
        if (isset($data['geo_lng'])) $data['geo_lng'] = round((float) $data['geo_lng'], 5);
        $location->update($data);
        return redirect()->route('admin.event.locations.index')->with('success', 'Eventort aktualisiert.');
    }

    public function destroy(EventLocation $location): RedirectResponse
    {
        $location->delete();
        return redirect()->route('admin.event.locations.index')->with('success', 'Eventort gelöscht.');
    }
}
