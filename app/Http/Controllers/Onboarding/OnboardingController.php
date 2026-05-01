<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\OnboardingDataRequest;
use App\Models\Employee\Employee;
use App\Services\Employee\OnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function __construct(private readonly OnboardingService $service) {}

    // ── Step 1: Start ─────────────────────────────────────────────────────────

    public function start(): View
    {
        return view('onboarding.start');
    }

    public function postStart(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        $email    = strtolower(trim($request->email));
        $employee = $this->service->findByEmail($email);

        // Employee already fully onboarded — tell them clearly
        $alreadyActive = Employee::where('email', $email)
            ->whereIn('onboarding_status', ['active'])
            ->first();

        if ($alreadyActive) {
            return back()->with('info', 'Dein Onboarding ist bereits abgeschlossen. Du kannst dich direkt anmelden.');
        }

        if (! $employee) {
            // Generic message — do not reveal if email exists
            return back()->with('info', 'Wenn eine E-Mail-Adresse zu einem Mitarbeiter-Datensatz gehört, haben wir dir jetzt eine E-Mail geschickt.');
        }

        if ($employee->onboarding_status === 'pending_review') {
            return back()->with('info', 'Deine Angaben wurden bereits übernommen. Dein Zugang wird vorbereitet.');
        }

        $this->service->sendVerification($employee, $request->ip());

        $request->session()->put('onboarding_email', $email);

        return redirect()->route('onboarding.verify')->with('info', 'Wenn eine E-Mail-Adresse zu einem Mitarbeiter-Datensatz gehört, haben wir dir jetzt eine E-Mail geschickt.');
    }

    // ── Step 2: Verifikation ─────────────────────────────────────────────────

    /** Verify via direct link from email. */
    public function verifyLink(Request $request, string $token): RedirectResponse
    {
        $employee = $this->service->verifyByToken($token);

        if (! $employee) {
            return redirect()->route('onboarding.start')
                ->with('error', 'Der Link ist ungültig oder abgelaufen. Bitte starte das Onboarding erneut.');
        }

        $request->session()->put('onboarding_employee_id', $employee->id);
        $request->session()->forget('onboarding_email');

        return redirect()->route('onboarding.form');
    }

    /** Show code-entry form. */
    public function verifyForm(): View
    {
        return view('onboarding.verify');
    }

    /** Submit 6-digit code. */
    public function postVerify(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|digits:6']);

        $email    = $request->session()->get('onboarding_email');
        $employee = $email ? $this->service->findByEmail($email) : null;

        if (! $employee) {
            return redirect()->route('onboarding.start')
                ->with('error', 'Sitzung abgelaufen. Bitte starte das Onboarding erneut.');
        }

        if (! $this->service->verifyByCode($employee, $request->code)) {
            return back()->with('error', 'Der Code ist ungültig oder abgelaufen.');
        }

        $request->session()->put('onboarding_employee_id', $employee->id);
        $request->session()->forget('onboarding_email');

        return redirect()->route('onboarding.form');
    }

    // ── Step 3: Formular ─────────────────────────────────────────────────────

    public function form(Request $request): View|RedirectResponse
    {
        $employee = $this->getVerifiedEmployee($request);
        if (! $employee) {
            return redirect()->route('onboarding.start')
                ->with('error', 'Sitzung abgelaufen. Bitte starte das Onboarding erneut.');
        }

        return view('onboarding.form', compact('employee'));
    }

    public function postForm(OnboardingDataRequest $request): RedirectResponse
    {
        $employee = $this->getVerifiedEmployee($request);
        if (! $employee) {
            return redirect()->route('onboarding.start')
                ->with('error', 'Sitzung abgelaufen.');
        }

        try {
            $this->service->saveData($employee, $request->validated());
            $this->service->setCredentials(
                $employee,
                $request->validated()['employee_number'],
                $request->validated()['pin'],
            );
            $this->service->submit($employee);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['credentials' => $e->getMessage()])->withInput();
        }

        $request->session()->forget('onboarding_employee_id');

        return redirect()->route('onboarding.done');
    }

    // ── Step 4: Done ─────────────────────────────────────────────────────────

    public function done(): View
    {
        return view('onboarding.done');
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function getVerifiedEmployee(Request $request): ?Employee
    {
        $id = $request->session()->get('onboarding_employee_id');
        if (! $id) {
            return null;
        }
        return Employee::find($id);
    }
}
