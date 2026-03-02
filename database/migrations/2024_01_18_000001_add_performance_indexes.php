<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-18: Performance indexes for hot-path queries.
 *
 * Existing indexes (not duplicated here):
 *  orders:           (customer_id, created_at), status, company_id
 *  order_items:      product_id, order_id  (individual)
 *  tours:            (tour_date, regular_delivery_tour_id), tour_date, company_id
 *  tour_stops:       (tour_id, stop_index), unique(tour_id, order_id), (tour_id, status)
 *  invoices:         company_id, (company_id, finalized_at, status)  [WP-16]
 *  products:         artikelnummer (UNIQUE)
 *  product_barcodes: barcode (UNIQUE)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── orders ──────────────────────────────────────────────────────────
        Schema::table('orders', static function (Blueprint $table): void {
            // Delivery-date filter (e.g. "show today's orders")
            $table->index('delivery_date', 'orders_delivery_date_idx');
            // Company+status+delivery composite for admin list page
            $table->index(['company_id', 'status', 'delivery_date'], 'orders_company_status_delivery_idx');
        });

        // ── order_items ──────────────────────────────────────────────────────
        Schema::table('order_items', static function (Blueprint $table): void {
            // Covering index for JOIN order_items ON order_id WHERE product_id = ?
            $table->index(['order_id', 'product_id'], 'order_items_order_product_idx');
        });

        // ── products ─────────────────────────────────────────────────────────
        Schema::table('products', static function (Blueprint $table): void {
            // Admin product list: filter active products by category
            $table->index(['active', 'category_id'], 'products_active_category_idx');
        });

        // ── product_barcodes ─────────────────────────────────────────────────
        Schema::table('product_barcodes', static function (Blueprint $table): void {
            // Primary barcode lookup per product
            $table->index(['product_id', 'is_primary'], 'product_barcodes_product_primary_idx');
            // Validity window filter on barcode scans
            $table->index(['barcode', 'valid_to'], 'product_barcodes_barcode_valid_to_idx');
        });

        // ── tours ─────────────────────────────────────────────────────────────
        Schema::table('tours', static function (Blueprint $table): void {
            // Driver app: "show all planned tours for today"
            $table->index(['tour_date', 'status'], 'tours_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropIndex('orders_delivery_date_idx');
            $table->dropIndex('orders_company_status_delivery_idx');
        });

        Schema::table('order_items', static function (Blueprint $table): void {
            $table->dropIndex('order_items_order_product_idx');
        });

        Schema::table('products', static function (Blueprint $table): void {
            $table->dropIndex('products_active_category_idx');
        });

        Schema::table('product_barcodes', static function (Blueprint $table): void {
            $table->dropIndex('product_barcodes_product_primary_idx');
            $table->dropIndex('product_barcodes_barcode_valid_to_idx');
        });

        Schema::table('tours', static function (Blueprint $table): void {
            $table->dropIndex('tours_date_status_idx');
        });
    }
};
