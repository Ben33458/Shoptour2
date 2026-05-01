<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use App\Models\Employee\VacationRequest;
use App\Services\Employee\VacationService;
use Illuminate\Http\Request;

class VacationRequestController extends Controller
{
    public function __construct(private readonly VacationService $vacationService) {}

    public function index(Request $request)
    {
        // For now show a simple form; in production this would be linked to a logged-in employee
        $employee  = null;
        $requests  = collect();
        $balance   = null;

        if ($request->has('employee_id')) {
            $employee = Employee::findOrFail($request->employee_id);
            $requests = VacationRequest::where('employee_id', $employee->id)->orderByDesc('created_at')->get();
            $balance  = $this->vacationService->getBalance($employee->id, now()->year);
        }

        $employees = Employee::active()->orderBy('last_name')->get();
        return view('employee.vacation.index', compact('employee', 'requests', 'balance', 'employees'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'start_date'  => 'required|date|after_or_equal:today',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'notes'       => 'nullable|string|max:500',
        ]);

        $days = $this->vacationService->countWorkingDays($data['start_date'], $data['end_date']);

        VacationRequest::create([
            'employee_id'    => $data['employee_id'],
            'start_date'     => $data['start_date'],
            'end_date'       => $data['end_date'],
            'days_requested' => $days,
            'notes'          => $data['notes'] ?? null,
        ]);

        return back()->with('success', "Urlaubsantrag für {$days} Arbeitstage eingereicht.");
    }
}
