<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROJ-4: Add checkout-specific fields to orders table.
 *
 * - order_number: unique human-readable identifier (B260302001 format)
 * - delivery_type: home_delivery or pickup
 * - payment_method: stripe, paypal, sepa, invoice, cash, ec
 * - pickup_location_id: FK to warehouses (for pickup orders)
 * - notes: internal admin notes
 * - customer_notes: customer-facing notes
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Unique human-readable order number (e.g. B260302001)
            $table->string('order_number', 20)->nullable()->unique()->after('company_id');

            // Delivery type: home_delivery or pickup
            $table->enum('delivery_type', ['home_delivery', 'pickup'])
                ->default('home_delivery')
                ->after('status');

            // Payment method chosen at checkout
            $table->enum('payment_method', ['stripe', 'paypal', 'sepa', 'invoice', 'cash', 'ec'])
                ->nullable()
                ->after('delivery_type');

            // Pickup location (FK to warehouses) — only set when delivery_type = pickup
            $table->unsignedBigInteger('pickup_location_id')->nullable()->after('warehouse_id');
            $table->foreign('pickup_location_id')
                ->references('id')
                ->on('warehouses')
                ->nullOnDelete();

            // External payment provider reference (Stripe session ID, PayPal order ID)
            $table->string('payment_reference', 255)->nullable()->after('pickup_location_id');

            // Notes
            $table->text('notes')->nullable()->after('total_pfand_brutto_milli');
            $table->text('customer_notes')->nullable()->after('notes');

            // Indexes for common queries
            $table->index('delivery_type');
            $table->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['pickup_location_id']);
            $table->dropIndex(['delivery_type']);
            $table->dropIndex(['payment_method']);
            $table->dropColumn([
                'order_number',
                'delivery_type',
                'payment_method',
                'pickup_location_id',
                'payment_reference',
                'notes',
                'customer_notes',
            ]);
        });
    }
};
