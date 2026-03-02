<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Pricing\AppSetting;
use App\Models\Pricing\Customer;
use App\Models\Pricing\CustomerGroup;
use App\Models\User;
use App\Services\Shop\CartMergeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use App\Mail\WelcomeMail;

/**
 * PROJ-1 -- Customer self-registration (email + password).
 *
 * Flow:
 *   1. GET  /registrieren  -> show registration form
 *   2. POST /registrieren  -> validate -> create User (role=kunde) + Customer
 *                             with default customer group -> cart merge -> login -> redirect
 */
class RegisterController extends Controller
{
    public function __construct(
        private readonly CartMergeService $cartMergeService,
    ) {}

    /**
     * Show the registration form.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle the registration form submission.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name'             => ['required', 'string', 'max:100'],
            'last_name'              => ['required', 'string', 'max:100'],
            'company_name'           => ['nullable', 'string', 'max:255'],
            'email'                  => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'               => ['required', 'confirmed', Password::min(8)],
            'address.street'         => ['required', 'string', 'max:200'],
            'address.house_number'   => ['required', 'string', 'max:20'],
            'address.zip'            => ['required', 'string', 'max:10'],
            'address.city'           => ['required', 'string', 'max:100'],
            'address.phone'          => ['nullable', 'string', 'max:50'],
        ], [
            'first_name.required'     => 'Bitte gib deinen Vornamen ein.',
            'last_name.required'      => 'Bitte gib deinen Nachnamen ein.',
            'address.street.required' => 'Bitte gib eine Straße ein.',
            'address.zip.required'    => 'Bitte gib eine Postleitzahl ein.',
            'address.house_number.required' => 'Bitte gib eine Hausnummer ein.',
            'address.city.required'   => 'Bitte gib einen Ort ein.',
        ]);

        $defaultGroupId = AppSetting::getInt('default_customer_group_id', 0);
        $defaultCompanyId = AppSetting::getInt('default_company_id', 1);

        // Fallback: first active customer group if no default is configured
        if (! $defaultGroupId) {
            $defaultGroupId = (int) CustomerGroup::where('active', true)->value('id');
        }

        if (! $defaultGroupId) {
            return back()->withErrors([
                'email' => 'Registrierung derzeit nicht möglich (keine Kundengruppe konfiguriert). Bitte wenden Sie sich an den Administrator.',
            ]);
        }

        // Capture guest session ID before it gets regenerated
        $guestSessionId = $request->session()->getId();

        DB::transaction(function () use ($validated, $defaultGroupId, $defaultCompanyId, $request): void {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'],
                'email'      => $validated['email'],
                'password'   => Hash::make($validated['password']),
                'company_id' => $defaultCompanyId,
            ]);
            $user->role = User::ROLE_KUNDE;
            $user->active = true;
            $user->save();

            $customer = Customer::create([
                'company_id'         => $defaultCompanyId,
                'user_id'            => $user->id,
                'customer_group_id'  => $defaultGroupId,
                'customer_number'    => 'K' . str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                'first_name'         => $validated['first_name'],
                'last_name'          => $validated['last_name'],
                'email'              => $user->email,
                'active'             => true,
                'price_display_mode' => 'gross',
            ]);

            // Set company_name on customer if provided
            if (! empty($validated['company_name'])) {
                $customer->update(['company_name' => $validated['company_name']]);
            }

            $addr = $validated['address'];
            $customer->addresses()->create([
                'type'         => 'delivery',
                'is_default'   => true,
                'first_name'   => $validated['first_name'],
                'last_name'    => $validated['last_name'],
                'street'       => $addr['street'],
                'house_number' => $addr['house_number'] ?? null,
                'zip'          => $addr['zip'],
                'city'         => $addr['city'],
                'country_code' => 'DE',
                'phone'        => $addr['phone'] ?? null,
            ]);

            Auth::login($user);
            $request->session()->regenerate();
        });

        // Merge guest cart into authenticated session
        $this->cartMergeService->merge($guestSessionId);

        // Send welcome email (synchronous, no queue)
        Mail::to($request->user()->email)->send(new WelcomeMail($request->user()));

        return redirect()->intended(route('shop.index'));
    }
}
