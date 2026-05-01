<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use App\Models\Employee\Shift;
use App\Models\Employee\ShiftArea;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $week = $request->input('week', now()->format('Y-\WW'));
        [$year, $w] = explode('-W', $week);
        $monday = \Carbon\Carbon::now()->setISODate((int)$year, (int)$w)->startOfDay();
        $sunday = $monday->copy()->addDays(6)->endOfDay();

        $shifts    = Shift::with('employee', 'shiftArea')
            ->whereBetween('planned_start', [$monday, $sunday])
            ->orderBy('planned_start')
            ->get();
        $employees = Employee::active()->orderBy('last_name')->get();
        $areas     = ShiftArea::active()->get();

        return view('admin.shifts.index', compact('shifts', 'employees', 'areas', 'monday', 'week'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'    => 'required|exists:employees,id',
            'shift_area_id'  => 'nullable|exists:shift_areas,id',
            'planned_start'  => 'required|date',
            'planned_end'    => 'required|date|after:planned_start',
            'notes'          => 'nullable|string|max:500',
        ]);
        $data['created_by'] = auth()->id();
        Shift::create($data);
        return back()->with('success', 'Schicht angelegt.');
    }

    public function destroy(Shift $shift)
    {
        if ($shift->status !== 'planned') {
            return back()->with('error', 'Nur geplante Schichten können gelöscht werden.');
        }
        $shift->delete();
        return back()->with('success', 'Schicht gelöscht.');
    }

    public function update(Request $request, Shift $shift)
    {
        $data = $request->validate([
            'planned_start' => 'required|date',
            'planned_end'   => 'required|date|after:planned_start',
            'shift_area_id' => 'nullable|exists:shift_areas,id',
            'notes'         => 'nullable|string|max:500',
        ]);
        $shift->update($data);
        return back()->with('success', 'Schicht aktualisiert.');
    }
}
