<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CustomerActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\View\View;

/**
 * Handles the "Bestehendes Kundenkonto aktivieren" flow.
 *
 * Routes (all guest-only except onboarding helpers):
 *   GET  /konto-aktivieren                  showEmailForm
 *   POST /konto-aktivieren                  submitEmail
 *   GET  /konto-aktivieren/code             showCodeForm
 *   POST /konto-aktivieren/code             verifyCode
 *   GET  /konto-aktivieren/passwort         showPasswordForm
 *   POST /konto-aktivieren/passwort         setPassword
 *
 * Auth-required onboarding helpers:
 *   POST /mein-konto/onboarding/{step}/hilfebox-schliessen   dismissHelpbox
 *   POST /mein-konto/onboarding/abschliessen                  completeOnboarding
 */
class CustomerActivationController extends Controller
{
    public function __construct(
        private readonly CustomerActivationService $service,
    ) {}

    // =========================================================================
    // Step 1: Email form
    // =========================================================================

    public function showEmailForm(): View
    {
        return view('auth.activate.email');
    }

    public function submitEmail(Request $request): RedirectResponse|View
    {
        $request->validate(['email' => ['required', 'email', 'max:200']]);

        $email = mb_strtolower(trim($request->input('email')));
        $ip    = $request->ip();

        // ── Rate limiting ────────────────────────────────────────────────────
        $emailKey = 'activation-email:' . $email;
        $ipKey    = 'activation-ip:' . $ip;

        if (RateLimiter::tooManyAttempts($emailKey, 10)) {
            return view('auth.activate.result', [
                'case'    => 'blocked_email',
                'message' => 'Zu viele Anfragen für diese E-Mail-Adresse. Bitte versuchen Sie es später erneut oder kontaktieren Sie uns.',
            ]);
        }

        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            return view('auth.activate.result', [
                'case'    => 'blocked_ip',
                'message' => 'Zu viele Anfragen von Ihrem Gerät. Bitte versuchen Sie es später erneut.',
            ]);
        }

        RateLimiter::hit($emailKey, 3600);
        RateLimiter::hit($ipKey, 3600);

        // ── Case detection ───────────────────────────────────────────────────
        $detected = $this->service->detectCase($email);

        return match ($detected['case']) {
            'A' => $this->handleCaseA($detected['customer'], $ip, $request),
            'B' => $this->handleCaseB($email, $detected['customers']),
            'C' => view('auth.activate.result', ['case' => 'C']),
            default => view('auth.activate.result', ['case' => 'D']),
        };
    }

    // =========================================================================
    // Step 2: Code entry
    // =========================================================================

    public function showCodeForm(Request $request): View|RedirectResponse
    {
        if (! session()->has('activation_token_id')) {
            return redirect()->route('activation.show');
        }

        $cooldownUntil = session('activation_cooldown_until');
        $cooldownLeft  = $cooldownUntil ? max(0, (int) ceil($cooldownUntil - now()->timestamp)) : 0;

        return view('auth.activate.code', [
            'email'        => session('activation_email'),
            'cooldownLeft' => $cooldownLeft,
        ]);
    }

    public function verifyCode(Request $request): RedirectResponse|View
    {
        if (! session()->has('activation_token_id')) {
            return redirect()->route('activation.show');
        }

        $request->validate(['code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/']]);

        $ipKey = 'activation-code-ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            return back()->withErrors(['code' => 'Zu viele Versuche. Bitte versuchen Sie es später erneut.']);
        }

        RateLimiter::hit($ipKey, 900); // 15-minute window

        try {
            $token = $this->service->verifyCode(
                (int) session('activation_token_id'),
                $request->input('code'),
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        session(['activation_verified' => true, 'activation_verified_token_id' => $token->id]);

        return redirect()->route('activation.password.show');
    }

    public function resendCode(Request $request): RedirectResponse
    {
        if (! session()->has('activation_token_id')) {
            return redirect()->route('activation.show');
        }

        $cooldownUntil = session('activation_cooldown_until');
        if ($cooldownUntil && now()->timestamp < $cooldownUntil) {
            return redirect()->route('activation.code.show')
                ->with('error', 'Bitte warten Sie, bevor Sie einen neuen Code anfordern.');
        }

        $tokenId  = (int) session('activation_token_id');
        $oldToken = \App\Models\CustomerActivationToken::find($tokenId);

        if (! $oldToken) {
            return redirect()->route('activation.show');
        }

        $token = $this->service->sendCode($oldToken->customer, $request->ip());

        session([
            'activation_token_id'     => $token->id,
            'activation_email'        => $token->email,
            'activation_cooldown_until' => now()->addSeconds(60)->timestamp,
        ]);

        return redirect()->route('activation.code.show')
            ->with('success', 'Ein neuer Code wurde versendet.');
    }

    // =========================================================================
    // Step 3: Set password
    // =========================================================================

    public function showPasswordForm(): View|RedirectResponse
    {
        if (! session('activation_verified')) {
            return redirect()->route('activation.show');
        }

        return view('auth.activate.password');
    }

    public function setPassword(Request $request): RedirectResponse
    {
        if (! session('activation_verified')) {
            return redirect()->route('activation.show');
        }

        $request->validate([
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $tokenId = (int) session('activation_verified_token_id');
        $token   = \App\Models\CustomerActivationToken::findOrFail($tokenId);

        try {
            $user = $this->service->activateAccount($token, $request->input('password'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['password' => $e->getMessage()]);
        }

        // Clear activation session data
        session()->forget([
            'activation_token_id',
            'activation_email',
            'activation_verified',
            'activation_verified_token_id',
            'activation_cooldown_until',
        ]);

        // Auto-login the new user
        Auth::login($user);
        $request->session()->regenerate();

        // Start the onboarding tour at step 1
        $firstStep = CustomerActivationService::tourSteps()[0];

        return redirect()->route($firstStep['route'], $firstStep['params'])
            ->with('onboarding_started', true);
    }

    // =========================================================================
    // Onboarding helpers (auth required — routes set in web.php)
    // =========================================================================

    public function dismissHelpbox(Request $request, string $step): RedirectResponse
    {
        $validSteps = array_column(CustomerActivationService::tourSteps(), 'key');

        if (! in_array($step, $validSteps, true)) {
            abort(404);
        }

        $customer = $this->requireCustomer();
        $this->service->dismissHelpbox($customer, $step);

        return redirect()->back();
    }

    public function completeOnboarding(Request $request): RedirectResponse
    {
        $customer = $this->requireCustomer();
        $this->service->completeOnboarding($customer);

        return redirect()->route('shop.index')->with('success', 'Konto-Einrichtung abgeschlossen. Willkommen!');
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function handleCaseA(
        \App\Models\Pricing\Customer $customer,
        string $ip,
        Request $request,
    ): RedirectResponse {
        $token = $this->service->sendCode($customer, $ip);

        session([
            'activation_token_id'       => $token->id,
            'activation_email'          => $token->email,
            'activation_cooldown_until' => now()->addSeconds(60)->timestamp,
        ]);

        return redirect()->route('activation.code.show');
    }

    private function handleCaseB(
        string $email,
        \Illuminate\Support\Collection $customers,
    ): View {
        $this->service->sendMultipleMatchAlert($email, $customers);

        return view('auth.activate.result', ['case' => 'B']);
    }

    private function requireCustomer(): \App\Models\Pricing\Customer
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->isSubUser()) {
            $subUser = $user->subUser;
            if (! $subUser?->active) {
                abort(403);
            }
            return $subUser->parentCustomer;
        }

        $customer = $user->customer;
        if (! $customer) {
            abort(403);
        }

        return $customer;
    }
}
