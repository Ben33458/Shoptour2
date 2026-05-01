<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\CustomerActivationCodeMail;
use App\Mail\CustomerActivationMultipleMail;
use App\Models\CustomerActivationToken;
use App\Models\Pricing\Customer;
use App\Models\Pricing\CustomerGroup;
use App\Models\User;
use App\Services\CustomerActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class CustomerActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Mail::fake();
        RateLimiter::clear('activation-email:customer@example.com');
        RateLimiter::clear('activation-email:shared@example.com');
        RateLimiter::clear('activation-email:existing@example.com');
        RateLimiter::clear('activation-email:nobody@example.com');
        RateLimiter::clear('activation-email:ratelimited@example.com');
        RateLimiter::clear('activation-ip:127.0.0.1');
        RateLimiter::clear('activation-code-ip:127.0.0.1');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeCustomerGroup(): CustomerGroup
    {
        return CustomerGroup::firstOrCreate(
            ['name' => 'Standard'],
            ['slug' => 'standard', 'adjustment_type' => 'none', 'adjustment_value' => 0],
        );
    }

    private function makeCustomer(array $attrs = []): Customer
    {
        $group = $this->makeCustomerGroup();

        return Customer::create(array_merge([
            'customer_number'   => 'K' . str_pad((string) random_int(1, 99999), 6, '0', STR_PAD_LEFT),
            'customer_group_id' => $group->id,
            'email'             => 'customer@example.com',
            'first_name'        => 'Max',
            'last_name'         => 'Mustermann',
            'active'            => true,
            'user_id'           => null,
            'price_display_mode' => 'brutto',
        ], $attrs));
    }

    // =========================================================================
    // Email form
    // =========================================================================

    public function test_email_form_visible_to_guests(): void
    {
        $response = $this->get(route('activation.show'));
        $response->assertStatus(200);
        $response->assertSee('Konto aktivieren');
    }

    public function test_email_form_redirects_authenticated_users(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_KUNDE]);
        $response = $this->actingAs($user)->get(route('activation.show'));
        $response->assertRedirect(); // guest middleware redirects
    }

    // =========================================================================
    // Case A: Single match — send code
    // =========================================================================

    public function test_case_a_sends_code_and_redirects_to_code_form(): void
    {
        $this->makeCustomer(['email' => 'customer@example.com']);

        $response = $this->post(route('activation.submit'), [
            'email' => 'customer@example.com',
        ]);

        $response->assertRedirect(route('activation.code.show'));

        Mail::assertSent(CustomerActivationCodeMail::class, function ($mail) {
            return $mail->hasTo('customer@example.com');
        });

        $this->assertDatabaseHas('customer_activation_tokens', [
            'email'   => 'customer@example.com',
            'used_at' => null,
        ]);
    }

    public function test_case_a_stores_token_id_in_session(): void
    {
        $customer = $this->makeCustomer(['email' => 'customer@example.com']);

        $response = $this->post(route('activation.submit'), [
            'email' => 'customer@example.com',
        ]);

        $response->assertSessionHas('activation_token_id');
        $response->assertSessionHas('activation_email', 'customer@example.com');
    }

    // =========================================================================
    // Case B: Multiple matches — internal mail
    // =========================================================================

    public function test_case_b_sends_internal_mail_and_shows_result(): void
    {
        $this->makeCustomer(['email' => 'shared@example.com', 'customer_number' => 'K000001']);
        $this->makeCustomer(['email' => 'shared@example.com', 'customer_number' => 'K000002']);

        $response = $this->post(route('activation.submit'), [
            'email' => 'shared@example.com',
        ]);

        $response->assertOk();
        $response->assertSee('Manuelle Prüfung erforderlich');

        Mail::assertSent(CustomerActivationMultipleMail::class, function ($mail) {
            return $mail->hasTo('getraenke@kolabri.de');
        });
    }

    // =========================================================================
    // Case C: User already exists
    // =========================================================================

    public function test_case_c_shows_already_active_message(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com', 'role' => User::ROLE_KUNDE]);

        $response = $this->post(route('activation.submit'), [
            'email' => 'existing@example.com',
        ]);

        $response->assertOk();
        $response->assertSee('bereits vorhanden');
        Mail::assertNotSent(CustomerActivationCodeMail::class);
    }

    // =========================================================================
    // Case D: No matching customer
    // =========================================================================

    public function test_case_d_shows_no_match_message(): void
    {
        $response = $this->post(route('activation.submit'), [
            'email' => 'nobody@example.com',
        ]);

        $response->assertOk();
        $response->assertSee('automatische Aktivierung');
    }

    // =========================================================================
    // Code verification
    // =========================================================================

    public function test_correct_code_redirects_to_password_form(): void
    {
        $customer = $this->makeCustomer(['email' => 'customer@example.com']);

        // Send code
        $this->post(route('activation.submit'), ['email' => 'customer@example.com']);

        $token = CustomerActivationToken::where('customer_id', $customer->id)->latest()->first();
        $plainCode = '123456';

        // Manually set the code hash so we know the code
        $token->update(['code_hash' => hash('sha256', $plainCode)]);

        $this->withSession(['activation_token_id' => $token->id, 'activation_email' => 'customer@example.com'])
            ->post(route('activation.code.verify'), ['code' => $plainCode])
            ->assertRedirect(route('activation.password.show'));
    }

    public function test_wrong_code_increments_attempts(): void
    {
        $customer = $this->makeCustomer(['email' => 'customer@example.com']);

        $token = CustomerActivationToken::create([
            'customer_id' => $customer->id,
            'email'       => 'customer@example.com',
            'code_hash'   => hash('sha256', '654321'),
            'expires_at'  => now()->addMinutes(15),
        ]);

        $this->withSession(['activation_token_id' => $token->id, 'activation_email' => 'customer@example.com'])
            ->post(route('activation.code.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertEquals(1, $token->fresh()->verify_attempts);
    }

    public function test_expired_token_returns_error(): void
    {
        $customer = $this->makeCustomer(['email' => 'customer@example.com']);

        $token = CustomerActivationToken::create([
            'customer_id' => $customer->id,
            'email'       => 'customer@example.com',
            'code_hash'   => hash('sha256', '123456'),
            'expires_at'  => now()->subMinute(),
        ]);

        $this->withSession(['activation_token_id' => $token->id, 'activation_email' => 'customer@example.com'])
            ->post(route('activation.code.verify'), ['code' => '123456'])
            ->assertSessionHasErrors('code');
    }

    public function test_exhausted_token_blocks_verification(): void
    {
        $customer = $this->makeCustomer(['email' => 'customer@example.com']);

        $token = CustomerActivationToken::create([
            'customer_id'     => $customer->id,
            'email'           => 'customer@example.com',
            'code_hash'       => hash('sha256', '123456'),
            'expires_at'      => now()->addMinutes(15),
            'verify_attempts' => 10,
        ]);

        $this->withSession(['activation_token_id' => $token->id, 'activation_email' => 'customer@example.com'])
            ->post(route('activation.code.verify'), ['code' => '123456'])
            ->assertSessionHasErrors('code');
    }

    // =========================================================================
    // Password + account creation
    // =========================================================================

    public function test_valid_password_creates_user_and_links_customer(): void
    {
        $customer = $this->makeCustomer(['email' => 'customer@example.com']);

        $token = CustomerActivationToken::create([
            'customer_id' => $customer->id,
            'email'       => 'customer@example.com',
            'code_hash'   => hash('sha256', '123456'),
            'expires_at'  => now()->addMinutes(15),
        ]);

        $this->withSession([
            'activation_verified'          => true,
            'activation_verified_token_id' => $token->id,
        ])->post(route('activation.password.set'), [
            'password'              => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'customer@example.com',
            'role'  => User::ROLE_KUNDE,
        ]);

        $customer->refresh();
        $this->assertNotNull($customer->user_id);

        $token->refresh();
        $this->assertNotNull($token->used_at);
    }

    public function test_duplicate_activation_blocked(): void
    {
        $existingUser = User::factory()->create(['email' => 'taken@example.com', 'role' => User::ROLE_KUNDE]);
        $customer     = $this->makeCustomer(['email' => 'customer@example.com', 'user_id' => $existingUser->id]);

        $token = CustomerActivationToken::create([
            'customer_id' => $customer->id,
            'email'       => 'customer@example.com',
            'code_hash'   => hash('sha256', '123456'),
            'expires_at'  => now()->addMinutes(15),
        ]);

        $this->withSession([
            'activation_verified'          => true,
            'activation_verified_token_id' => $token->id,
        ])->post(route('activation.password.set'), [
            'password'              => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ])->assertSessionHasErrors();
    }

    // =========================================================================
    // Rate limiting
    // =========================================================================

    public function test_email_rate_limit_per_email(): void
    {
        // Hit the email rate limit manually
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('activation-email:ratelimited@example.com', 3600);
        }

        $response = $this->post(route('activation.submit'), [
            'email' => 'ratelimited@example.com',
        ]);

        $response->assertOk();
        $response->assertSee('Zu viele Anfragen');
    }

    public function test_ip_rate_limit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('activation-ip:127.0.0.1', 3600);
        }

        $response = $this->post(route('activation.submit'), [
            'email' => 'anyone@example.com',
        ]);

        $response->assertOk();
        $response->assertSee('Zu viele Anfragen');
    }

    // =========================================================================
    // Onboarding tour
    // =========================================================================

    public function test_profile_page_shows_onboarding_banner_when_step_set(): void
    {
        $user     = User::factory()->create(['role' => User::ROLE_KUNDE]);
        $customer = $this->makeCustomer(['email' => $user->email, 'user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('account.profile', ['onboarding_step' => 'profil']));

        $response->assertOk();
        $response->assertSee('Konto-Einrichtung');
        $response->assertSee('Schritt 1 von 6');
    }

    public function test_invoices_page_auto_completes_onboarding_on_last_step(): void
    {
        $user     = User::factory()->create(['role' => User::ROLE_KUNDE]);
        $customer = $this->makeCustomer([
            'email'                => $user->email,
            'user_id'              => $user->id,
            'lexoffice_contact_id' => 'test-uuid-' . uniqid(),
            'display_preferences'  => ['onboarding_completed' => false],
        ]);

        // The page will 404 for Lexoffice vouchers fetch — but completion happens before that
        $this->actingAs($user)
            ->get(route('account.invoices', ['onboarding_step' => 'rechnungen']));

        $customer->refresh();
        $this->assertTrue((bool) ($customer->display_preferences['onboarding_completed'] ?? false));
    }

    public function test_helpbox_dismiss_persists_in_display_preferences(): void
    {
        $user     = User::factory()->create(['role' => User::ROLE_KUNDE]);
        $customer = $this->makeCustomer(['email' => $user->email, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('onboarding.helpbox.dismiss', 'profil'))
            ->assertRedirect();

        $customer->refresh();
        $this->assertContains('profil', $customer->display_preferences['onboarding_helpbox_dismissed'] ?? []);
    }

    public function test_tour_step_keys_are_complete(): void
    {
        $steps = CustomerActivationService::tourSteps();
        $keys  = array_column($steps, 'key');

        $this->assertContains('profil', $keys);
        $this->assertContains('emails', $keys);
        $this->assertContains('adressen', $keys);
        $this->assertContains('stammsortiment', $keys);
        $this->assertContains('unterbenutzer', $keys);
        $this->assertContains('rechnungen', $keys);
        $this->assertCount(6, $steps);
    }

    public function test_next_step_url_returns_null_for_last_step(): void
    {
        $this->assertNull(CustomerActivationService::nextStepUrl('rechnungen'));
    }

    public function test_next_step_url_returns_next_step(): void
    {
        $url = CustomerActivationService::nextStepUrl('profil');
        $this->assertNotNull($url);
        $this->assertStringContainsString('onboarding_step=emails', $url);
    }
}
