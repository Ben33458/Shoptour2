<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use App\Models\Employee\VacationRequest;
use App\Services\Employee\VacationService;
use Illuminate\Http\Request;

class MeinVacationController extends Controller
{
    public function __construct(private readonly VacationService $vacationService) {}

    public function index()
    {
        $employee = Employee::findOrFail(session('employee_id'));
        $balance  = $this->vacationService->getBalance($employee->id, now()->year);
        $requests = VacationRequest::where('employee_id', $employee->id)
            ->orderByDesc('start_date')
            ->limit(30)
            ->get();

        return view('mein.urlaub', compact('employee', 'balance', 'requests'));
    }

    public function store(Request $request)
    {
        $employee = Employee::findOrFail(session('employee_id'));
        $balance  = $this->vacationService->getBalance($employee->id, now()->year);

        $data = $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'notes'      => 'nullable|string|max:500',
        ]);

        $days = $this->vacationService->countWorkingDays($data['start_date'], $data['end_date'], $employee);

        if ($days < 0.5) {
            return back()->withErrors(['end_date' => 'Der gewählte Zeitraum enthält keine Arbeitstage.']);
        }

        if ($days > $balance->remaining_days) {
            return back()->withErrors(['end_date' =>
                "Nicht genug Resturlaub — du hast noch {$balance->remaining_days} Tage verfügbar, beantragt: {$days}."
            ]);
        }

        VacationRequest::create([
            'employee_id'    => $employee->id,
            'start_date'     => $data['start_date'],
            'end_date'       => $data['end_date'],
            'days_requested' => $days,
            'notes'          => $data['notes'] ?? null,
            'status'         => 'pending',
        ]);

        $daysDisplay = number_format($days, 1, ',', '') === number_format((int)$days, 1, ',', '') ? (int)$days : number_format($days, 1, ',', '');
        return back()->with('success', "Urlaubsantrag für {$daysDisplay} Arbeitstag(e) eingereicht. Der Admin wird ihn prüfen.");
    }

    public function cancel(VacationRequest $request)
    {
        $employee = Employee::findOrFail(session('employee_id'));

        if ($request->employee_id !== $employee->id || $request->status !== 'pending') {
            return back()->with('error', 'Dieser Antrag kann nicht zurückgezogen werden.');
        }

        $request->update(['status' => 'cancelled']);

        return back()->with('success', 'Urlaubsantrag zurückgezogen.');
    }
}
