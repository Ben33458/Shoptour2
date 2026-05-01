<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Pricing\Customer;
use App\Models\User;
use App\Services\Admin\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * PROJ-1 -- Password reset: request link via email.
 *
 * Uses Laravel's built-in Password broker (synchronous email sending).
 */
class ForgotPasswordController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * GET /passwort-vergessen -- show the "forgot password" form.
     */
    public function showLinkRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * POST /passwort-vergessen -- send the reset link email (synchronous).
     */
    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        // Auto-create user account if email exists in customers but has no login yet
        $this->ensureUserExistsForCustomer($request->email);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            $this->audit->log('password_reset.link_sent', meta: ['email' => $request->email]);
            return back()->with('status', 'Wir haben Ihnen einen Link zum Zurücksetzen Ihres Passworts per E-Mail gesendet.');
        }

        $this->audit->log('password_reset.failed', meta: ['email' => $request->email, 'status' => $status], level: 'warning');

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => $this->translateStatus($status)]);
    }

    /**
     * If the email belongs to a customer without a user account, create one silently.
     */
    private function ensureUserExistsForCustomer(string $email): void
    {
        if (User::where('email', $email)->exists()) {
            return;
        }

        $customer = Customer::whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->whereNull('user_id')
            ->first();

        if (! $customer) {
            return;
        }

        $nameParts = array_filter(explode(' ', $customer->company_name ?? ''));
        $firstName = $nameParts[0] ?? explode('@', $email)[0];
        $lastName  = implode(' ', array_slice($nameParts, 1));

        $user = User::create([
            'first_name'        => $firstName,
            'last_name'         => $lastName,
            'email'             => $customer->email,
            'password'          => Hash::make(\Illuminate\Support\Str::random(32)),
            'role'              => 'kunde',
            'active'            => true,
            'email_verified_at' => now(),
        ]);

        $customer->update(['user_id' => $user->id]);

        $this->audit->log('password_reset.user_auto_created', $user, [
            'email'       => $user->email,
            'customer_id' => $customer->id,
        ]);
    }

    /**
     * Map Password broker status codes to German messages.
     */
    private function translateStatus(string $status): string
    {
        return match ($status) {
            Password::INVALID_USER     => 'Wir konnten keinen Benutzer mit dieser E-Mail-Adresse finden.',
            Password::RESET_THROTTLED  => 'Bitte warten Sie, bevor Sie es erneut versuchen.',
            default                    => 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
        };
    }
}
