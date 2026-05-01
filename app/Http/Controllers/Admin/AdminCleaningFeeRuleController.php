<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental\CleaningFeeRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCleaningFeeRuleController extends Controller
{
    public function index(): View
    {
        $rules = CleaningFeeRule::where('active', true)->orderBy('name')->paginate(30);
        return view('admin.rental.cleaning-fee-rules.index', compact('rules'));
    }

    public function create(): View
    {
        return view('admin.rental.cleaning-fee-rules.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:150',
            'applies_to_type' => 'required|in:rental_item,category,packaging_unit,inventory_unit',
            'applies_to_id'   => 'nullable|integer|min:1',
            'fee_type'        => 'required|in:flat,per_item,per_pack,per_unit',
            'amount_net_eur'  => 'required|numeric|min:0',
            'active'          => 'boolean',
            'notes'           => 'nullable|string',
        ]);
        $data['amount_net_milli'] = (int) round((float) $data['amount_net_eur'] * 1_000_000);
        $data['active']           = $request->boolean('active', true);
        unset($data['amount_net_eur']);
        CleaningFeeRule::create($data);
        return redirect()->route('admin.rental.cleaning-fee-rules.index')->with('success', 'Reinigungsregel erstellt.');
    }

    public function edit(CleaningFeeRule $cleaningFeeRule): View
    {
        return view('admin.rental.cleaning-fee-rules.edit', compact('cleaningFeeRule'));
    }

    public function update(Request $request, CleaningFeeRule $cleaningFeeRule): RedirectResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:150',
            'applies_to_type' => 'required|in:rental_item,category,packaging_unit,inventory_unit',
            'applies_to_id'   => 'nullable|integer|min:1',
            'fee_type'        => 'required|in:flat,per_item,per_pack,per_unit',
            'amount_net_eur'  => 'required|numeric|min:0',
            'active'          => 'boolean',
            'notes'           => 'nullable|string',
        ]);
        $data['amount_net_milli'] = (int) round((float) $data['amount_net_eur'] * 1_000_000);
        $data['active']           = $request->boolean('active');
        unset($data['amount_net_eur']);
        $cleaningFeeRule->update($data);
        return redirect()->route('admin.rental.cleaning-fee-rules.index')->with('success', 'Reinigungsregel aktualisiert.');
    }

    public function destroy(CleaningFeeRule $cleaningFeeRule): RedirectResponse
    {
        $cleaningFeeRule->delete();
        return redirect()->route('admin.rental.cleaning-fee-rules.index')->with('success', 'Reinigungsregel gelöscht.');
    }
}
