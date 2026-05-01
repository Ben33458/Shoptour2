<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Catalog\Product;
use App\Models\Catalog\Warengruppe;
use App\Models\Pricing\Customer;
use App\Models\Pricing\CustomerGroup;
use App\Models\Pricing\TaxRate;
use App\Models\User;
use App\Services\Catalog\JugendschutzService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the Jugendschutz (youth protection) MVP.
 *
 * Covers:
 *  - Customer birth_date storage
 *  - JugendschutzService::productMinAge() — warengruppe + alkohol fallback
 *  - JugendschutzService::cartMinAge() — mixed cart
 *  - JugendschutzService::checkoutWarning() / deliveryNote() — text output
 *  - Checkout page shows warning for age-restricted cart
 *  - Cart page shows warning for age-restricted cart
 */
class JugendschutzTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCustomerGroup(): CustomerGroup
    {
        return CustomerGroup::firstOrCreate(
            ['name' => 'Testgruppe'],
            ['price_display_mode' => 'brutto', 'active' => true],
        );
    }

    private function makeCustomer(array $overrides = []): Customer
    {
        $group = $this->makeCustomerGroup();

        return Customer::create(array_merge([
            'customer_group_id' => $group->id,
            'customer_number'   => 'TEST-' . uniqid(),
            'email'             => 'kunde@example.com',
            'active'            => true,
            'price_display_mode'=> 'brutto',
            'newsletter_consent'=> 'important_only',
        ], $overrides));
    }

    private function makeWarengruppe(int $id, string $name = 'Test-WG'): Warengruppe
    {
        $existing = Warengruppe::find($id);
        if ($existing) {
            return $existing;
        }

        \Illuminate\Support\Facades\DB::table('warengruppen')->insert([
            'id'         => $id,
            'name'       => $name,
            'active'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Warengruppe::find($id);
    }

    private function makeTaxRate(): TaxRate
    {
        return TaxRate::firstOrCreate(
            ['name' => 'Test 19%'],
            ['rate_basis_points' => 1900],
        );
    }

    /** Make a minimal Product with only the fields we need. */
    private function makeProduct(array $overrides = []): Product
    {
        // Ensure warengruppe_id FK exists
        if (isset($overrides['warengruppe_id'])) {
            $this->makeWarengruppe((int) $overrides['warengruppe_id']);
        }

        $id = uniqid();
        return Product::create(array_merge([
            'artikelnummer'          => 'ART-' . $id,
            'slug'                   => 'testprodukt-' . $id,
            'produktname'            => 'Testprodukt',
            'active'                 => true,
            'show_in_shop'           => true,
            'base_price_gross_milli' => 1_000_000,
            'base_price_net_milli'   => 840_336,
            'availability_mode'      => 'always',
            'tax_rate_id'            => $this->makeTaxRate()->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // 1. Customer birth_date
    // -------------------------------------------------------------------------

    /** @test */
    public function it_saves_birth_date_on_customer(): void
    {
        $customer = $this->makeCustomer(['birth_date' => '1990-06-15']);

        $this->assertNotNull($customer->birth_date);
        $this->assertSame('1990-06-15', $customer->birth_date->format('Y-m-d'));
    }

    /** @test */
    public function it_allows_null_birth_date(): void
    {
        $customer = $this->makeCustomer(['birth_date' => null]);

        $this->assertNull($customer->birth_date);
    }

    // -------------------------------------------------------------------------
    // 2. JugendschutzService::productMinAge — warengruppe_id path
    // -------------------------------------------------------------------------

    /** @test */
    public function product_in_18plus_warengruppe_returns_18(): void
    {
        foreach (JugendschutzService::WG_IDS_18 as $wgId) {
            $product = $this->makeProduct(['warengruppe_id' => $wgId]);
            $this->assertSame(18, JugendschutzService::productMinAge($product),
                "Expected 18 for warengruppe_id={$wgId}");
        }
    }

    /** @test */
    public function product_in_16plus_warengruppe_returns_16(): void
    {
        // Pick a subset to keep the test fast
        $sampleIds = [2, 11, 16, 33];
        foreach ($sampleIds as $wgId) {
            $product = $this->makeProduct(['warengruppe_id' => $wgId]);
            $this->assertSame(16, JugendschutzService::productMinAge($product),
                "Expected 16 for warengruppe_id={$wgId}");
        }
    }

    /** @test */
    public function product_with_no_warengruppe_and_no_alkohol_returns_0(): void
    {
        $product = $this->makeProduct(['warengruppe_id' => null, 'alkoholgehalt_vol_percent' => null]);

        $this->assertSame(0, JugendschutzService::productMinAge($product));
    }

    // -------------------------------------------------------------------------
    // 3. JugendschutzService::productMinAge — alkohol fallback
    // -------------------------------------------------------------------------

    /** @test */
    public function product_with_high_alkohol_and_unknown_warengruppe_returns_18(): void
    {
        // warengruppe_id = 30 (Sonstiges, not in any age list) + >= 15 % ABV
        $product = $this->makeProduct(['warengruppe_id' => 30, 'alkoholgehalt_vol_percent' => 40.0]);

        $this->assertSame(18, JugendschutzService::productMinAge($product));
    }

    /** @test */
    public function product_with_low_alkohol_and_unknown_warengruppe_returns_16(): void
    {
        $product = $this->makeProduct(['warengruppe_id' => 30, 'alkoholgehalt_vol_percent' => 4.5]);

        $this->assertSame(16, JugendschutzService::productMinAge($product));
    }

    /** @test */
    public function product_with_alkoholfrei_and_unknown_warengruppe_returns_0(): void
    {
        // < 1.2 % ABV → not age-restricted
        $product = $this->makeProduct(['warengruppe_id' => 30, 'alkoholgehalt_vol_percent' => 0.5]);

        $this->assertSame(0, JugendschutzService::productMinAge($product));
    }

    // -------------------------------------------------------------------------
    // 4. JugendschutzService::cartMinAge — mixed cart
    // -------------------------------------------------------------------------

    /** @test */
    public function cart_min_age_returns_highest_age_across_items(): void
    {
        $soft    = $this->makeProduct(['warengruppe_id' => 1]);   // Limonade → 0
        $beer    = $this->makeProduct(['warengruppe_id' => 2]);   // Bier → 16
        $spirits = $this->makeProduct(['warengruppe_id' => 17]);  // Spirituosen → 18

        $cartItems = [
            1 => ['product' => $soft,    'qty' => 2],
            2 => ['product' => $beer,    'qty' => 1],
            3 => ['product' => $spirits, 'qty' => 1],
        ];

        $this->assertSame(18, JugendschutzService::cartMinAge($cartItems));
    }

    /** @test */
    public function cart_min_age_returns_16_for_beer_only_cart(): void
    {
        $beer = $this->makeProduct(['warengruppe_id' => 11]);  // Pils → 16

        $cartItems = [
            1 => ['product' => $beer, 'qty' => 6],
        ];

        $this->assertSame(16, JugendschutzService::cartMinAge($cartItems));
    }

    /** @test */
    public function cart_min_age_returns_0_for_soft_drink_only_cart(): void
    {
        $water = $this->makeProduct(['warengruppe_id' => 24]);  // Mineralwasser → 0

        $cartItems = [
            1 => ['product' => $water, 'qty' => 2],
        ];

        $this->assertSame(0, JugendschutzService::cartMinAge($cartItems));
    }

    /** @test */
    public function cart_min_age_returns_0_for_empty_cart(): void
    {
        $this->assertSame(0, JugendschutzService::cartMinAge([]));
    }

    // -------------------------------------------------------------------------
    // 5. Warning / delivery note text
    // -------------------------------------------------------------------------

    /** @test */
    public function checkout_warning_is_null_for_age_0(): void
    {
        $this->assertNull(JugendschutzService::checkoutWarning(0));
    }

    /** @test */
    public function checkout_warning_mentions_16_for_age_16(): void
    {
        $warning = JugendschutzService::checkoutWarning(16);
        $this->assertNotNull($warning);
        $this->assertStringContainsString('16', $warning);
    }

    /** @test */
    public function checkout_warning_mentions_18_for_age_18(): void
    {
        $warning = JugendschutzService::checkoutWarning(18);
        $this->assertNotNull($warning);
        $this->assertStringContainsString('18', $warning);
    }

    /** @test */
    public function delivery_note_is_null_for_age_0(): void
    {
        $this->assertNull(JugendschutzService::deliveryNote(0));
    }

    /** @test */
    public function delivery_note_mentions_18_for_spirits(): void
    {
        $note = JugendschutzService::deliveryNote(18);
        $this->assertNotNull($note);
        $this->assertStringContainsString('18', $note);
    }

    // -------------------------------------------------------------------------
    // 6. HTTP: Jugendschutz warning appears on cart and checkout pages
    // -------------------------------------------------------------------------

    /** @test */
    public function cart_page_shows_jugendschutz_warning_for_age_restricted_product(): void
    {
        // Arrange: authenticated kunde with a Spirituosen product in DB cart
        $customer = $this->makeCustomer();
        $user     = User::factory()->create(['role' => User::ROLE_KUNDE]);
        $customer->update(['user_id' => $user->id]);

        $product = $this->makeProduct(['warengruppe_id' => 17]); // Spirituosen → 18+

        // Add product to cart via CartService (uses DB cart for auth users)
        $cartService = app(\App\Services\Shop\CartService::class);
        $cartService->add($product->id, 2, $user);

        $response = $this->actingAs($user)->get(route('cart.index'));

        $response->assertStatus(200);
        $response->assertSeeText('18');
        $response->assertSeeText('Altersprüfung');
    }
}
