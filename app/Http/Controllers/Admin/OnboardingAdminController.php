<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use App\Services\Employee\EmployeeMailService;
use App\Services\Employee\OnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingAdminController extends Controller
{
    public function __construct(
        private readonly OnboardingService $service,
        private readonly EmployeeMailService $mailer,
    ) {}

    public function index(Request $request): View
    {
        $filter = $request->input('filter', 'pending_review');

        $employees = Employee::where('onboarding_status', $filter)
            ->orderByDesc('onboarding_completed_at')
            ->orderByDesc('created_at')
            ->paginate(30);

        $counts = [
            'pending'        => Employee::where('onboarding_status', 'pending')->count(),
            'pending_review' => Employee::where('onboarding_status', 'pending_review')->count(),
            'approved'       => Employee::where('onboarding_status', 'approved')->count(),
            'active'         => Employee::where('onboarding_status', 'active')->count(),
        ];

        return view('admin.onboarding.index', compact('employees', 'filter', 'counts'));
    }

    public function show(Employee $employee): View
    {
        return view('admin.onboarding.show', compact('employee'));
    }

    public function approve(Employee $employee, Request $request): RedirectResponse
    {
        $this->service->approve($employee, $request->user()->id);

        $emailNote = '';
        if ($employee->email) {
            $sent = $this->mailer->sendWelcome(
                employee: $employee->fresh(),
                triggeredBy: 'onboarding.approve',
                sentByUserId: $request->user()->id,
            );
            $emailNote = $sent
                ? " Willkommens-E-Mail wurde an {$employee->email} versandt."
                : " Willkommens-E-Mail konnte nicht versandt werden.";
        }

        return back()->with('success', "Mitarbeiter {$employee->full_name} wurde freigegeben und ist nun aktiv.{$emailNote}");
    }

    public function reject(Employee $employee, Request $request): RedirectResponse
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $this->service->reject($employee, $request->user()->id, $request->input('reason', ''));

        return back()->with('success', "Onboarding von {$employee->full_name} wurde zurückgewiesen.");
    }
}
