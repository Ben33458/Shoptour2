<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Communications\CommunicationRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunicationRuleController extends Controller
{
    public function index(): View
    {
        $rules = CommunicationRule::orderBy('priority')->orderBy('id')->get();
        return view('admin.communications.rules.index', compact('rules'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'             => 'required|string|max:200',
            'description'      => 'nullable|string',
            'condition_type'   => 'required|string|max:50',
            'condition_value'  => 'required|string|max:500',
            'action_type'      => 'required|string|max:50',
            'action_value'     => 'nullable|string|max:500',
            'confidence_boost' => 'required|integer|min:0|max:100',
            'priority'         => 'required|integer',
            'active'           => 'boolean',
        ]);

        $data['company_id'] = auth()->user()->company_id;
        $data['active']     = $request->boolean('active', true);

        CommunicationRule::create($data);

        return redirect()->route('admin.communications.rules.index')
            ->with('success', 'Regel erstellt.');
    }

    public function update(Request $request, CommunicationRule $rule): RedirectResponse
    {
        $data = $request->validate([
            'name'             => 'required|string|max:200',
            'description'      => 'nullable|string',
            'condition_type'   => 'required|string|max:50',
            'condition_value'  => 'required|string|max:500',
            'action_type'      => 'required|string|max:50',
            'action_value'     => 'nullable|string|max:500',
            'confidence_boost' => 'required|integer|min:0|max:100',
            'priority'         => 'required|integer',
            'active'           => 'boolean',
        ]);

        $data['active'] = $request->boolean('active', true);
        $rule->update($data);

        return redirect()->route('admin.communications.rules.index')
            ->with('success', 'Regel aktualisiert.');
    }

    public function destroy(CommunicationRule $rule): RedirectResponse
    {
        $rule->delete();
        return redirect()->route('admin.communications.rules.index')
            ->with('success', 'Regel gelöscht.');
    }
}
