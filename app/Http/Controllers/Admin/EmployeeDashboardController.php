<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use App\Models\Employee\Shift;
use App\Models\Employee\TimeEntry;
use App\Models\Employee\VacationRequest;

class EmployeeDashboardController extends Controller
{
    public function index()
    {
        $today = now()->startOfDay();

        $todayShifts = Shift::with('employee', 'shiftArea')
            ->whereDate('planned_start', $today)
            ->orderBy('planned_start')
            ->get();

        $activeEntries = TimeEntry::with('employee', 'breakSegments')
            ->whereNull('clocked_out_at')
            ->get();

        $pendingVacations = VacationRequest::with('employee')
            ->where('status', 'pending')
            ->count();

        $complianceWarnings = TimeEntry::where('compliance_status', '!=', 'ok')
            ->whereDate('clocked_in_at', '>=', now()->subDays(7))
            ->count();

        $totalActive = Employee::active()->count();

        return view('admin.employees.dashboard', compact(
            'todayShifts', 'activeEntries', 'pendingVacations',
            'complianceWarnings', 'totalActive'
        ));
    }
}
