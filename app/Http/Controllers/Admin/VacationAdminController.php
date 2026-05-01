<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeNotification;
use App\Models\Employee\VacationBalance;
use App\Models\Employee\VacationRequest;
use App\Services\Employee\VacationService;
use Illuminate\Http\Request;

class VacationAdminController extends Controller
{
    public function __construct(private readonly VacationService $vacationService) {}

    public function index(Request $request)
    {
        $pending   = VacationRequest::with('employee')->where('status', 'pending')->orderBy('start_date')->get();
        $recent    = VacationRequest::with('employee')
            ->whereIn('status', ['approved', 'rejected', 'cancelled'])
            ->orderByDesc('decided_at')
            ->limit(20)
            ->get();
        $employees = Employee::active()->orderBy('last_name')->get();

        // Saldo-Vorschau: Mitarbeiter + Jahr per GET-Parameter
        $balancePreview = null;
        if ($request->filled('balance_employee_id') && $request->filled('balance_year')) {
            $emp = Employee::find($request->integer('balance_employee_id'));
            if ($emp) {
                $balancePreview = $this->vacationService->getBalance(
                    $emp->id,
                    $request->integer('balance_year')
                );
                $balancePreview->setRelation('employee', $emp);
            }
        }

        return view('admin.vacation.index', compact('pending', 'recent', 'employees', 'balancePreview'));
    }

    public function approve(VacationRequest $request)
    {
        $this->vacationService->approve($request, auth()->id());

        EmployeeNotification::create([
            'employee_id' => $request->employee_id,
            'type'        => 'info',
            'title'       => 'Urlaub genehmigt',
            'message'     => "Dein Urlaubsantrag vom {$request->start_date->format('d.m.Y')} bis {$request->end_date->format('d.m.Y')} ({$request->days_requested} Tage) wurde genehmigt.",
        ]);

        return back()->with('success', 'Urlaubsantrag genehmigt.');
    }

    public function reject(Request $httpRequest, VacationRequest $request)
    {
        $data = $httpRequest->validate(['decision_notes' => 'nullable|string|max:500']);
        $this->vacationService->reject($request, auth()->id(), $data['decision_notes'] ?? null);

        EmployeeNotification::create([
            'employee_id' => $request->employee_id,
            'type'        => 'warning',
            'title'       => 'Urlaub abgelehnt',
            'message'     => "Dein Urlaubsantrag vom {$request->start_date->format('d.m.Y')} bis {$request->end_date->format('d.m.Y')} wurde leider abgelehnt."
                . ($data['decision_notes'] ? " Begründung: {$data['decision_notes']}" : ''),
        ]);

        return back()->with('success', 'Urlaubsantrag abgelehnt.');
    }

    /** Admin trägt Urlaub direkt ein → sofort genehmigt. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'notes'       => 'nullable|string|max:500',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $days     = $this->vacationService->countWorkingDays($data['start_date'], $data['end_date'], $employee);
        $balance  = $this->vacationService->getBalance($employee->id, (int) date('Y', strtotime($data['start_date'])));

        if ($days < 1) {
            return back()->withErrors(['end_date' => 'Der gewählte Zeitraum enthält keine Arbeitstage.'])->withInput();
        }

        $vacReq = VacationRequest::create([
            'employee_id'    => $employee->id,
            'start_date'     => $data['start_date'],
            'end_date'       => $data['end_date'],
            'days_requested' => $days,
            'notes'          => $data['notes'] ?? null,
            'status'         => 'pending',
        ]);

        $this->vacationService->approve($vacReq, auth()->id());

        EmployeeNotification::create([
            'employee_id' => $employee->id,
            'type'        => 'info',
            'title'       => 'Urlaub eingetragen',
            'message'     => "Dein Urlaub vom {$vacReq->start_date->format('d.m.Y')} bis {$vacReq->end_date->format('d.m.Y')} ({$days} Tage) wurde vom Admin eingetragen.",
        ]);

        return back()->with('success', "{$days} Urlaubstage für {$employee->full_name} eingetragen und genehmigt.");
    }

    /** Admin pflegt Urlaubsanspruch und Übertrag. */
    public function updateBalance(Request $request)
    {
        $data = $request->validate([
            'employee_id'  => 'required|exists:employees,id',
            'year'         => 'required|integer|min:2020|max:2035',
            'total_days'   => 'required|integer|min:0|max:365',
            'carried_over' => 'required|integer|min:0|max:100',
        ]);

        $balance = $this->vacationService->getBalance((int) $data['employee_id'], (int) $data['year']);
        $balance->update([
            'total_days'   => $data['total_days'],
            'carried_over' => $data['carried_over'],
        ]);

        return back()->with('success', 'Urlaubskonto aktualisiert.');
    }
}
