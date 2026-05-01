<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\CustomerActivationCodeMail;
use App\Mail\CustomerActivationMultipleMail;
use App\Models\CustomerActivationToken;
use App\Models\Pricing\Customer;
use App\Models\User;
use App\Services\Admin\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Handles the "Bestehendes Kundenkonto aktivieren" flow.
 *
 * Cases:
 *   A – exactly one activatable customer found   → send code
 *   B – multiple customers share the email       → send internal alert, no activation
 *   C – a user account already exists            → no activation
 *   D – no matching customer                     → no activation
 *   E – customer found but no email stored       → no activation (handled via D in UI)
 */
class CustomerActivationService
{
    // -------------------------------------------------------------------------
    // Case detection
    // -------------------------------------------------------------------------

    /**
     * Find customers matching this email for activation.
     *
     * Returns one of: 'A', 'B', 'C', 'D'
     *   A → single activatable customer found, $customer set
     *   B → multiple customers found (no single match)
     *   C → a user account already exists for this email
     *   D → no matching customer
     */
    public function detectCase(string $email): array
    {
        // Check if a user account already exists with this email
        if (User::where('email', $email)->exists()) {
            return ['case' => 'C'];
        }

        // Find all active customers with this email and no user account yet
        $customers = Customer::where('email', $email)
            ->where('active', true)
            ->whereNull('user_id')
            ->get();

        if ($customers->count() === 0) {
            return ['case' => 'D'];
        }

        if ($customers->count() > 1) {
            return ['case' => 'B', 'customers' => $customers];
        }

        return ['case' => 'A', 'customer' => $customers->first()];
    }

    // -------------------------------------------------------------------------
    // Code sending
    // -------------------------------------------------------------------------

    /**
     * Generate and send a verification code for the given customer.
     * Invalidates any previous unused token for this customer.
     */
    public function sendCode(Customer $customer, string $ip): CustomerActivationToken
    {
        // Invalidate previous tokens for this customer
        CustomerActivationToken::where('customer_id', $customer->id)
            ->whereNull('used_at')
            ->delete();

        $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $token = CustomerActivationToken::create([
            'customer_id'     => $customer->id,
            'email'           => $customer->email,
            'code_hash'       => hash('sha256', $plainCode),
            'expires_at'      => now()->addMinutes(15),
            'ip_address'      => $ip,
            'verify_attempts' => 0,
        ]);

        Mail::to($customer->email)->send(new CustomerActivationCodeMail($plainCode, $customer));

        app(AuditLogService::class)->log(
            'customer.activation.code_sent',
            $customer,
            ['email' => $customer->email, 'ip' => $ip],
        );

        return $token;
    }

    /**
     * Send internal alert email for a multiple-match situation.
     */
    public function sendMultipleMatchAlert(string $email, \Illuminate\Support\Collection $customers): void
    {
        Mail::to('getraenke@kolabri.de')
            ->send(new CustomerActivationMultipleMail($email, $customers));

        app(AuditLogService::class)->log(
            'customer.activation.multiple_match',
            null,
            ['email' => $email, 'customer_ids' => $customers->pluck('id')->all()],
            level: 'warning',
        );
    }

    // -------------------------------------------------------------------------
    // Code verification
    // -------------------------------------------------------------------------

    /**
     * Verify the plain code against a stored token.
     *
     * Returns the token on success.
     * Increments verify_attempts on failure.
     * Throws \RuntimeException if invalid, expired, or exhausted.
     */
    public function verifyCode(int $tokenId, string $plainCode): CustomerActivationToken
    {
        $token = CustomerActivationToken::findOrFail($tokenId);

        if (! $token->isValid()) {
            if ($token->isExpired()) {
                throw new \RuntimeException('Der Code ist abgelaufen. Bitte fordern Sie einen neuen Code an.');
            }
            if ($token->isUsed()) {
                throw new \RuntimeException('Dieser Aktivierungslink wurde bereits verwendet.');
            }
            if ($token->isExhausted()) {
                throw new \RuntimeException('Zu viele Fehlversuche. Bitte fordern Sie einen neuen Code an.');
            }
        }

        if (! $token->verifyCode($plainCode)) {
            $token->increment('verify_attempts');

            app(AuditLogService::class)->log(
                'customer.activation.wrong_code',
                $token->customer,
                ['attempts' => $token->fresh()?->verify_attempts],
                level: 'warning',
            );

            $remaining = max(0, 10 - ($token->verify_attempts + 1));
            throw new \RuntimeException(
                $remaining > 0
                    ? "Falscher Code. Noch {$remaining} Versuch(e) verbleibend."
                    : 'Zu viele Fehlversuche. Bitte fordern Sie einen neuen Code an.'
            );
        }

        app(AuditLogService::class)->log(
            'customer.activation.code_verified',
            $token->customer,
            ['email' => $token->email],
        );

        return $token;
    }

    // -------------------------------------------------------------------------
    // Account creation
    // -------------------------------------------------------------------------

    /**
     * Create a user account, link it to the existing customer, and start onboarding.
     *
     * @throws \RuntimeException if customer already has a user account
     */
    public function activateAccount(CustomerActivationToken $token, string $password): User
    {
        if ($token->isUsed()) {
            throw new \RuntimeException('Dieser Aktivierungstoken wurde bereits verwendet.');
        }

        $customer = $token->customer;

        if ($customer->user_id !== null) {
            throw new \RuntimeException('Dieses Kundenkonto ist bereits aktiviert.');
        }

        $user = DB::transaction(function () use ($token, $customer, $password): User {
            $user = User::create([
                'first_name'        => $customer->first_name ?? '',
                'last_name'         => $customer->last_name ?? '',
                'email'             => $token->email,
                'password'          => Hash::make($password),
                'role'              => User::ROLE_KUNDE,
                'active'            => true,
                'email_verified_at' => now(),
            ]);

            $customer->update(['user_id' => $user->id]);

            $token->update(['used_at' => now()]);

            return $user;
        });

        app(AuditLogService::class)->log(
            'customer.activation.completed',
            $customer,
            [
                'user_id'         => $user->id,
                'customer_number' => $customer->customer_number,
            ],
        );

        return $user;
    }

    // -------------------------------------------------------------------------
    // Onboarding tour helpers
    // -------------------------------------------------------------------------

    /**
     * Mark a helpbox as dismissed for the authenticated customer.
     */
    public function dismissHelpbox(Customer $customer, string $step): void
    {
        $prefs    = $customer->display_preferences ?? [];
        $dismissed = $prefs['onboarding_helpbox_dismissed'] ?? [];

        if (! in_array($step, $dismissed, true)) {
            $dismissed[] = $step;
        }

        $customer->update([
            'display_preferences' => array_merge($prefs, [
                'onboarding_helpbox_dismissed' => $dismissed,
            ]),
        ]);
    }

    /**
     * Mark onboarding as completed for the given customer.
     */
    public function completeOnboarding(Customer $customer): void
    {
        if (! empty(($customer->display_preferences ?? [])['onboarding_completed'])) {
            return; // already done
        }

        $prefs = $customer->display_preferences ?? [];

        $customer->update([
            'display_preferences' => array_merge($prefs, [
                'onboarding_completed' => true,
            ]),
        ]);

        app(AuditLogService::class)->log(
            'customer.onboarding.completed',
            $customer,
        );
    }

    /**
     * Check whether onboarding is already completed.
     */
    public function isOnboardingCompleted(Customer $customer): bool
    {
        return ! empty(($customer->display_preferences ?? [])['onboarding_completed']);
    }

    /**
     * Returns the ordered tour step definitions.
     * Each entry: ['key', 'label', 'route', 'params', 'description']
     */
    public static function tourSteps(): array
    {
        return [
            [
                'key'         => 'profil',
                'label'       => 'Persönliche Daten',
                'route'       => 'account.profile',
                'params'      => ['onboarding_step' => 'profil'],
                'description' => 'Prüfen Sie hier Ihren Namen, Ihre Firma und Ihre Kontaktdaten und aktualisieren Sie diese bei Bedarf direkt.',
            ],
            [
                'key'         => 'emails',
                'label'       => 'E-Mail-Adressen',
                'route'       => 'account.profile',
                'params'      => ['onboarding_step' => 'emails'],
                'description' => 'Prüfen Sie hier Ihre hinterlegten E-Mail-Adressen – für Rechnungen, Versandbenachrichtigungen und Ihren Login.',
            ],
            [
                'key'         => 'adressen',
                'label'       => 'Adressen',
                'route'       => 'account.addresses',
                'params'      => ['onboarding_step' => 'adressen'],
                'description' => 'Hier sehen und verwalten Sie Ihre Liefer- und Rechnungsadressen. Bitte prüfen Sie, ob alles korrekt hinterlegt ist.',
            ],
            [
                'key'         => 'stammsortiment',
                'label'       => 'Stammsortiment',
                'route'       => 'account.favorites',
                'params'      => ['onboarding_step' => 'stammsortiment'],
                'description' => 'Ihr Stammsortiment enthält Produkte, die Sie regelmäßig bestellen. Sie können Produkte hier hinzufügen, entfernen und Sollbestände hinterlegen.',
            ],
            [
                'key'         => 'unterbenutzer',
                'label'       => 'Unterbenutzer',
                'route'       => 'account.sub-users',
                'params'      => ['onboarding_step' => 'unterbenutzer'],
                'description' => 'Unterbenutzer sind Mitarbeiter oder Kollegen, die in Ihrem Namen bestellen dürfen. Sie können Berechtigungen je Unterbenutzer festlegen.',
            ],
            [
                'key'         => 'rechnungen',
                'label'       => 'Rechnungen',
                'route'       => 'account.invoices',
                'params'      => ['onboarding_step' => 'rechnungen'],
                'description' => 'Hier finden Sie alle Ihre Rechnungen. Sie können diese einsehen und als PDF herunterladen.',
            ],
        ];
    }

    /**
     * Returns the URL for the next tour step after the given step key.
     * Returns null if the given step is the last one.
     */
    public static function nextStepUrl(string $currentKey): ?string
    {
        $steps = self::tourSteps();
        $index = array_search($currentKey, array_column($steps, 'key'), true);

        if ($index === false || $index + 1 >= count($steps)) {
            return null;
        }

        $next = $steps[$index + 1];

        return route($next['route'], $next['params']);
    }
}
