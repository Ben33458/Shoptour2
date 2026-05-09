<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Catalog\Product;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Models\Pricing\Customer;
use App\Models\Pricing\CustomerGroup;
use App\Models\Pricing\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Admin Order Edit (WP-22).
 *
 * Covers:
 *  - GET /admin/orders/{order}/edit — edit form accessible to admin
 *  - POST /admin/orders/{order}/items — update item qty
 *  - POST /admin/orders/{order}/items — qty=0 removes item
 *  - POST /admin/orders/{order}/items — order totals recalculated after update
 *  - POST /admin/orders/{order}/items/add — add new product
 *  - POST /admin/orders/{order}/items/add — increment qty if product already in order
 *  - Price snapshot (unit_price_*_milli) is NOT changed during qty update
 *  - Unauthenticated access is redirected
 */
class AdminOrderEditTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAdmin(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        return $user;
    }

    private function makeTaxRate(): TaxRate
    {
        return TaxRate::firstOrCreate(
            ['name' => 'MwSt 19%'],
            ['rate_basis_points' => 1_900],
        );
    }

    private function makeCustomer(): Customer
    {
        $group = CustomerGroup::firstOrCreate(
            ['name' => 'Standard'],
            ['price_display_mode' => 'brutto', 'active' => true],
        );

        return Customer::create([
            'customer_group_id'  => $group->id,
            'customer_number'    => 'TEST-' . uniqid(),
            'email'              => uniqid() . '@example.com',
            'active'             => true,
            'price_display_mode' => 'brutto',
            'newsletter_consent' => 'important_only',
        ]);
    }

    private function makeProduct(array $overrides = []): Product
    {
        $taxRate = $this->makeTaxRate();
        $id      = uniqid();

        return Product::create(array_merge([
            'artikelnummer'          => 'ART-' . $id,
            'slug'                   => 'produkt-' . $id,
            'produktname'            => 'Testprodukt',
            'active'                 => true,
            'show_in_shop'           => true,
            'base_price_gross_milli' => 1_500_000,
            'base_price_net_milli'   => 1_260_504,
            'availability_mode'      => 'always',
            'tax_rate_id'            => $taxRate->id,
        ], $overrides));
    }

    private function makeOrder(Customer $customer): Order
    {
        return Order::create([
            'customer_id'                => $customer->id,
            'customer_group_id_snapshot' => $customer->customer_group_id,
            'status'                     => Order::STATUS_PENDING,
            'delivery_type'              => 'home_delivery',
            'total_net_milli'            => 0,
            'total_gross_milli'          => 0,
            'total_pfand_brutto_milli'   => 0,
            'has_backorder'              => false,
        ]);
    }

    private function makeOrderItem(Order $order, Product $product, int $qty = 2): OrderItem
    {
        return OrderItem::create([
            'order_id'               => $order->id,
            'product_id'             => $product->id,
            'unit_price_net_milli'   => $product->base_price_net_milli,
            'unit_price_gross_milli' => $product->base_price_gross_milli,
            'price_source'           => 'base_price',
            'tax_rate_id'            => $product->tax_rate_id,
            'tax_rate_basis_points'  => 1_900,
            'unit_deposit_milli'     => 0,
            'qty'                    => $qty,
            'is_backorder'           => false,
            'product_name_snapshot'  => $product->produktname,
            'artikelnummer_snapshot' => $product->artikelnummer,
        ]);
    }

    // -------------------------------------------------------------------------
    // 1. Edit form
    // -------------------------------------------------------------------------

    /** @test */
    public function admin_can_access_order_edit_form(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);

        $response = $this->actingAs($admin)->get(route('admin.orders.edit', $order));

        $response->assertStatus(200);
        $response->assertViewIs('admin.orders.edit');
    }

    /** @test */
    public function unauthenticated_user_is_redirected_from_edit_form(): void
    {
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);

        $response = $this->get(route('admin.orders.edit', $order));

        $response->assertRedirect();
    }

    // -------------------------------------------------------------------------
    // 2. Update item quantity
    // -------------------------------------------------------------------------

    /** @test */
    public function admin_can_update_order_item_qty(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);
        $product  = $this->makeProduct();
        $item     = $this->makeOrderItem($order, $product, qty: 3);

        $response = $this->actingAs($admin)->post(
            route('admin.orders.items.update', $order),
            ['qty' => [$item->id => 7]]
        );

        $response->assertRedirect(route('admin.orders.show', $order));
        $this->assertDatabaseHas('order_items', ['id' => $item->id, 'qty' => 7]);
    }

    /** @test */
    public function price_snapshot_is_not_changed_when_updating_qty(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);
        $product  = $this->makeProduct(['base_price_gross_milli' => 2_000_000]);
        $item     = $this->makeOrderItem($order, $product, qty: 1);

        $originalNet   = $item->unit_price_net_milli;
        $originalGross = $item->unit_price_gross_milli;

        $this->actingAs($admin)->post(
            route('admin.orders.items.update', $order),
            ['qty' => [$item->id => 5]]
        );

        $this->assertDatabaseHas('order_items', [
            'id'                     => $item->id,
            'qty'                    => 5,
            'unit_price_net_milli'   => $originalNet,
            'unit_price_gross_milli' => $originalGross,
        ]);
    }

    /** @test */
    public function order_totals_are_recalculated_after_qty_update(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);
        $product  = $this->makeProduct([
            'base_price_net_milli'   => 1_000_000,
            'base_price_gross_milli' => 1_190_000,
        ]);
        $item = $this->makeOrderItem($order, $product, qty: 1);

        $this->actingAs($admin)->post(
            route('admin.orders.items.update', $order),
            ['qty' => [$item->id => 3]]
        );

        // 3 × 1_190_000 = 3_570_000 gross
        $this->assertDatabaseHas('orders', [
            'id'               => $order->id,
            'total_gross_milli' => 3_570_000,
        ]);
    }

    // -------------------------------------------------------------------------
    // 3. Remove item via qty = 0
    // -------------------------------------------------------------------------

    /** @test */
    public function setting_qty_to_zero_removes_the_item(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);
        $product  = $this->makeProduct();
        $item     = $this->makeOrderItem($order, $product, qty: 2);

        $this->actingAs($admin)->post(
            route('admin.orders.items.update', $order),
            ['qty' => [$item->id => 0]]
        );

        $this->assertDatabaseMissing('order_items', ['id' => $item->id]);
    }

    /** @test */
    public function explicitly_removed_item_is_deleted(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);
        $product  = $this->makeProduct();
        $item     = $this->makeOrderItem($order, $product, qty: 2);

        $this->actingAs($admin)->post(
            route('admin.orders.items.update', $order),
            ['remove' => [$item->id]]
        );

        $this->assertDatabaseMissing('order_items', ['id' => $item->id]);
    }

    // -------------------------------------------------------------------------
    // 4. Add item
    // -------------------------------------------------------------------------

    /** @test */
    public function admin_can_add_new_product_to_order(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);
        $product  = $this->makeProduct();

        $response = $this->actingAs($admin)->post(
            route('admin.orders.items.add', $order),
            ['product_id' => $product->id, 'qty' => 4]
        );

        $response->assertRedirect(route('admin.orders.edit', $order));
        $this->assertDatabaseHas('order_items', [
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'qty'        => 4,
        ]);
    }

    /** @test */
    public function adding_existing_product_increments_qty(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);
        $product  = $this->makeProduct();
        $item     = $this->makeOrderItem($order, $product, qty: 3);

        $this->actingAs($admin)->post(
            route('admin.orders.items.add', $order),
            ['product_id' => $product->id, 'qty' => 2]
        );

        $this->assertDatabaseHas('order_items', ['id' => $item->id, 'qty' => 5]);
        $this->assertSame(1, OrderItem::where('order_id', $order->id)->count());
    }

    /** @test */
    public function add_item_requires_valid_product_id(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);

        $response = $this->actingAs($admin)->post(
            route('admin.orders.items.add', $order),
            ['product_id' => 99999, 'qty' => 1]
        );

        $response->assertSessionHasErrors('product_id');
    }
}
