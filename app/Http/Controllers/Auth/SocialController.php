<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\Pricing\AppSetting;
use App\Models\Pricing\Customer;
use App\Models\Pricing\CustomerGroup;
use App\Models\User;
use App\Services\Shop\CartMergeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;

/**
 * PROJ-1 -- Google OAuth login / registration.
 *
 * Flow:
 *   GET  /auth/google          -> redirect to Google
 *   GET  /auth/google/callback -> handle response
 *
 * Callback logic:
 *   1. If a User with matching google_id already exists -> login
 *   2. If a User with matching e-mail exists -> link google_id, then login
 *   3. Otherwise -> create new User (role=kunde) + Customer -> login
 *   -> cart merge -> redirect
 */
class SocialController extends Controller
{
    public function __construct(
        private readonly CartMergeService $cartMergeService,
    ) {}

    /**
     * Redirect the user to the Google OAuth page.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google after user authorizes the app.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Google-Anmeldung fehlgeschlagen. Bitte versuche es erneut.']);
        }

        // Capture guest session before login regeneration
        $guestSessionId = request()->session()->getId();

        try {
            $user = DB::transaction(function () use ($googleUser): User {
            // 1. Existing user already linked to this Google account
            $existing = User::where('google_id', $googleUser->getId())->first();
            if ($existing) {
                $existing->update(['avatar_url' => $googleUser->getAvatar()]);

                // Check if account is deactivated
                if (! $existing->active) {
                    throw new \RuntimeException('ACCOUNT_DEACTIVATED');
                }

                return $existing;
            }

            // 2. User exists with same e-mail -> link Google account
            $byEmail = User::where('email', $googleUser->getEmail())->first();
            if ($byEmail) {
                if (! $byEmail->active) {
                    throw new \RuntimeException('ACCOUNT_DEACTIVATED');
                }

                $byEmail->update([
                    'google_id'  => $googleUser->getId(),
                    'avatar_url' => $googleUser->getAvatar(),
                ]);
                return $byEmail;
            }

            // 3. New user -- create User + Customer + Address
            $defaultGroupId = AppSetting::getInt('default_customer_group_id', 0);
            $defaultCompanyId = AppSetting::getInt('default_company_id', 1);

            // Fallback: first active customer group if no default is configured
            if (! $defaultGroupId) {
                $defaultGroupId = (int) CustomerGroup::where('active', true)->value('id');
            }

            if (! $defaultGroupId) {
                throw new \RuntimeException('NO_CUSTOMER_GROUP');
            }

            // Parse Google name into first/last
            $fullName = $googleUser->getName() ?? '';
            $parts = explode(' ', $fullName, 2);
            $firstName = $parts[0] ?: $googleUser->getEmail();
            $lastName = $parts[1] ?? '';

            $newUser = User::create([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $googleUser->getEmail(),
                'password'   => null, // OAuth-only user, no password
                'google_id'  => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
                'company_id' => $defaultCompanyId,
            ]);
            $newUser->role = User::ROLE_KUNDE;
            $newUser->active = true;
            $newUser->save();

            $customer = Customer::create([
                'company_id'         => $defaultCompanyId,
                'user_id'            => $newUser->id,
                'customer_group_id'  => $defaultGroupId,
                'customer_number'    => 'K' . str_pad((string) $newUser->id, 6, '0', STR_PAD_LEFT),
                'first_name'         => $firstName,
                'last_name'          => $lastName,
                'email'              => $newUser->email,
                'active'             => true,
                'price_display_mode' => 'gross',
            ]);

            // Create a placeholder delivery address (user must complete later)
            $customer->addresses()->create([
                'type'         => 'delivery',
                'is_default'   => true,
                'first_name'   => $firstName,
                'last_name'    => $lastName,
                'street'       => '',
                'house_number' => null,
                'zip'          => '',
                'city'         => '',
                'country_code' => 'DE',
                'phone'        => null,
            ]);

            return $newUser;
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'ACCOUNT_DEACTIVATED') {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Ihr Konto ist deaktiviert. Bitte wenden Sie sich an den Kundenservice.']);
            }
            if ($e->getMessage() === 'NO_CUSTOMER_GROUP') {
                return redirect()->route('login')
                    ->withErrors(['email' => 'System-Konfigurationsfehler: Bitte kontaktieren Sie den Support.']);
            }
            throw $e;
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        // Merge guest cart
        $this->cartMergeService->merge($guestSessionId);

        // Send welcome email for newly created OAuth users
        if ($user->wasRecentlyCreated) {
            Mail::to($user->email)->send(new WelcomeMail($user));
        }

        return redirect()->intended(
            $user->hasAdminAccess() ? route('admin.orders.index') : '/mein-konto'
        );
    }
}
