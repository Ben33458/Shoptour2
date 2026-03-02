<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-16: Performance indexes for reporting queries.
 *
 *  invoices:      (company_id, finalized_at, status) – revenue date-range scans
 *  invoice_items: (invoice_id, line_type)             – line-type filtering per invoice
 *  tour_stops:    (tour_id, status)                   – tour KPI per-status counts
 *  tours:         (tour_date)                         – date-range tour listing
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', static function (Blueprint $table): void {
            $table->index(['company_id', 'finalized_at', 'status'], 'invoices_reporting_idx');
        });

        Schema::table('invoice_items', static function (Blueprint $table): void {
            $table->index(['invoice_id', 'line_type'], 'invoice_items_type_idx');
        });

        Schema::table('tour_stops', static function (Blueprint $table): void {
            $table->index(['tour_id', 'status'], 'tour_stops_kpi_idx');
        });

        Schema::table('tours', static function (Blueprint $table): void {
            $table->index(['tour_date'], 'tours_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', static function (Blueprint $table): void {
            $table->dropIndex('invoices_reporting_idx');
        });

        Schema::table('invoice_items', static function (Blueprint $table): void {
            $table->dropIndex('invoice_items_type_idx');
        });

        Schema::table('tour_stops', static function (Blueprint $table): void {
            $table->dropIndex('tour_stops_kpi_idx');
        });

        Schema::table('tours', static function (Blueprint $table): void {
            $table->dropIndex('tours_date_idx');
        });
    }
};
