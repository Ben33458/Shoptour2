<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * PROJ-1 -- Password reset: request link via email.
 *
 * Uses Laravel's built-in Password broker (synchronous email sending).
 */
class ForgotPasswordController extends Controller
{
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

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'Wir haben Ihnen einen Link zum Zurücksetzen Ihres Passworts per E-Mail gesendet.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => $this->translateStatus($status)]);
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
