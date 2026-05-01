<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Shop\CartMergeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * PROJ-1 -- Email/password login + logout.
 *
 * Replaces the inline closures in routes/web.php.
 * Rate limiting is applied via the 'throttle:login' middleware on the route.
 */
class LoginController extends Controller
{
    public function __construct(
        private readonly CartMergeService $cartMergeService,
    ) {}

    /**
     * GET /anmelden -- show the login form.
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    /**
     * POST /anmelden -- authenticate the user.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // Only allow active accounts to log in
        $credentials['active'] = true;

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // Check if account exists but is deactivated
            $user = \App\Models\User::where('email', $credentials['email'])->first();

            if ($user && ! $user->active) {
                return back()
                    ->withErrors(['email' => 'Ihr Konto ist deaktiviert. Bitte wenden Sie sich an den Kundenservice.'])
                    ->onlyInput('email');
            }

            return back()
                ->withErrors(['email' => 'E-Mail oder Passwort falsch.'])
                ->onlyInput('email');
        }

        // Capture the guest session ID before regeneration
        $guestSessionId = $request->session()->getId();

        $request->session()->regenerate();

        // Merge guest cart into the authenticated session
        $this->cartMergeService->merge($guestSessionId);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        return redirect()->intended(
            $user->hasAdminAccess() ? route('admin.dashboard') : '/mein-konto'
        );
    }

    /**
     * POST /abmelden -- log the user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
