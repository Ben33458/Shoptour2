<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental\RentalTimeModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRentalTimeModelController extends Controller
{
    public function index(): View
    {
        $models = RentalTimeModel::orderBy('sort_order')->orderBy('name')->get();
        return view('admin.rental.time-models.index', compact('models'));
    }

    public function create(): View
    {
        return view('admin.rental.time-models.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:100',
            'description'        => 'nullable|string',
            'active'             => 'boolean',
            'sort_order'         => 'nullable|integer|min:0',
            'rule_type'          => 'required|string|max:50',
            'min_duration_hours' => 'nullable|integer|min:1',
            'default_for_events' => 'boolean',
        ]);
        $data['active']             = $request->boolean('active', true);
        $data['default_for_events'] = $request->boolean('default_for_events');
        RentalTimeModel::create($data);
        return redirect()->route('admin.rental.time-models.index')->with('success', 'Mietzeitmodell erstellt.');
    }

    public function edit(RentalTimeModel $timeModel): View
    {
        return view('admin.rental.time-models.edit', compact('timeModel'));
    }

    public function update(Request $request, RentalTimeModel $timeModel): RedirectResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:100',
            'description'        => 'nullable|string',
            'active'             => 'boolean',
            'sort_order'         => 'nullable|integer|min:0',
            'rule_type'          => 'required|string|max:50',
            'min_duration_hours' => 'nullable|integer|min:1',
            'default_for_events' => 'boolean',
        ]);
        $data['active']             = $request->boolean('active');
        $data['default_for_events'] = $request->boolean('default_for_events');
        $timeModel->update($data);
        return redirect()->route('admin.rental.time-models.index')->with('success', 'Mietzeitmodell aktualisiert.');
    }

    public function destroy(RentalTimeModel $timeModel): RedirectResponse
    {
        if ($timeModel->priceRules()->exists()) {
            return back()->with('error', 'Mietzeitmodell wird noch in Preisregeln verwendet.');
        }
        $timeModel->delete();
        return redirect()->route('admin.rental.time-models.index')->with('success', 'Mietzeitmodell gelöscht.');
    }
}
