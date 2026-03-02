<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * PROJ-1 -- Password reset: set new password via token.
 */
class ResetPasswordController extends Controller
{
    /**
     * GET /passwort-reset/{token} -- show the reset form.
     */
    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * POST /passwort-reset -- validate token + set new password.
     */
    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('status', 'Ihr Passwort wurde erfolgreich zurückgesetzt. Sie können sich jetzt anmelden.');
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
            Password::INVALID_USER  => 'Wir konnten keinen Benutzer mit dieser E-Mail-Adresse finden.',
            Password::INVALID_TOKEN => 'Der Link zum Zurücksetzen ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen Link an.',
            Password::RESET_THROTTLED => 'Bitte warten Sie, bevor Sie es erneut versuchen.',
            default                 => 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
        };
    }
}
