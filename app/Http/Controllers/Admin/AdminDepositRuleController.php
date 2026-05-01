<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental\DepositRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDepositRuleController extends Controller
{
    public function index(): View
    {
        $rules = DepositRule::orderBy('name')->paginate(30);
        return view('admin.rental.deposit-rules.index', compact('rules'));
    }

    public function create(): View
    {
        return view('admin.rental.deposit-rules.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:150',
            'rule_type'      => 'required|in:none,fixed_per_item,private_only,risk_class',
            'amount_net_eur' => 'nullable|numeric|min:0',
            'private_only'   => 'boolean',
            'min_risk_class' => 'nullable|string|max:50',
            'active'         => 'boolean',
            'notes'          => 'nullable|string',
        ]);
        $data['amount_net_milli'] = (int) round((float) ($data['amount_net_eur'] ?? 0) * 1_000_000);
        $data['private_only']     = $request->boolean('private_only');
        $data['active']           = $request->boolean('active', true);
        unset($data['amount_net_eur']);
        DepositRule::create($data);
        return redirect()->route('admin.rental.deposit-rules.index')->with('success', 'Kautionsregel erstellt.');
    }

    public function edit(DepositRule $depositRule): View
    {
        return view('admin.rental.deposit-rules.edit', compact('depositRule'));
    }

    public function update(Request $request, DepositRule $depositRule): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:150',
            'rule_type'      => 'required|in:none,fixed_per_item,private_only,risk_class',
            'amount_net_eur' => 'nullable|numeric|min:0',
            'private_only'   => 'boolean',
            'min_risk_class' => 'nullable|string|max:50',
            'active'         => 'boolean',
            'notes'          => 'nullable|string',
        ]);
        $data['amount_net_milli'] = (int) round((float) ($data['amount_net_eur'] ?? 0) * 1_000_000);
        $data['private_only']     = $request->boolean('private_only');
        $data['active']           = $request->boolean('active');
        unset($data['amount_net_eur']);
        $depositRule->update($data);
        return redirect()->route('admin.rental.deposit-rules.index')->with('success', 'Kautionsregel aktualisiert.');
    }

    public function destroy(DepositRule $depositRule): RedirectResponse
    {
        $depositRule->delete();
        return redirect()->route('admin.rental.deposit-rules.index')->with('success', 'Kautionsregel gelöscht.');
    }
}
