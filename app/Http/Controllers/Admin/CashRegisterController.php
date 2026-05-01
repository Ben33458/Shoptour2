<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver\CashRegister;
use App\Models\Driver\DriverSetting;
use App\Models\Employee\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashRegisterController extends Controller
{
    public function index(): View
    {
        $registers = CashRegister::with('transactions')->orderBy('name')->get();
        $employees = Employee::where('is_active', true)
            ->orderBy('first_name')
            ->get();
        $delayThreshold = (int) DriverSetting::get('delay_threshold_percent', 30);

        return view('admin.cash-registers.index', compact('registers', 'employees', 'delayThreshold'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['name' => 'required|string|max:100']);

        CashRegister::create([
            'name'      => $request->input('name'),
            'is_active' => true,
        ]);

        return back()->with('success', 'Kasse angelegt.');
    }

    public function toggle(CashRegister $register): RedirectResponse
    {
        $register->update(['is_active' => ! $register->is_active]);

        return back()->with('success', 'Status geändert.');
    }

    public function assignEmployee(Request $request, CashRegister $register): RedirectResponse
    {
        $request->validate(['employee_id' => 'nullable|exists:employees,id']);

        $employeeId = $request->input('employee_id') ?: null;

        // Remove this register from any employee currently assigned to it
        Employee::where('cash_register_id', $register->id)->update(['cash_register_id' => null]);

        // Assign to the new employee
        if ($employeeId) {
            Employee::where('id', $employeeId)->update(['cash_register_id' => $register->id]);
        }

        return back()->with('success', 'Mitarbeiter zugeordnet.');
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $request->validate(['delay_threshold_percent' => 'required|integer|min:5|max:300']);

        DriverSetting::set('delay_threshold_percent', $request->input('delay_threshold_percent'));

        return back()->with('success', 'Einstellungen gespeichert.');
    }

    public function transactions(CashRegister $register): View
    {
        $transactions = $register->transactions()
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('admin.cash-registers.transactions', compact('register', 'transactions'));
    }
}
