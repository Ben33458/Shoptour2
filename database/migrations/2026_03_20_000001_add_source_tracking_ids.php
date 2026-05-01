<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds nullable FK columns to customers, suppliers, and products
 * so each local record can be linked to its counterpart in Ninox and JTL-WaWi.
 *
 * These columns are purely additive (nullable) — no existing data is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->unsignedBigInteger('ninox_kunden_id')->nullable()->after('lexoffice_contact_id');
            $table->unsignedBigInteger('wawi_kunden_id')->nullable()->after('ninox_kunden_id');
            $table->index('ninox_kunden_id');
            $table->index('wawi_kunden_id');
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->unsignedBigInteger('ninox_lieferanten_id')->nullable()->after('lexoffice_contact_id');
            $table->index('ninox_lieferanten_id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('ninox_artikel_id')->nullable()->after('base_item_product_id');
            $table->unsignedBigInteger('wawi_artikel_id')->nullable()->after('ninox_artikel_id');
            $table->index('ninox_artikel_id');
            $table->index('wawi_artikel_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['ninox_kunden_id']);
            $table->dropIndex(['wawi_kunden_id']);
            $table->dropColumn(['ninox_kunden_id', 'wawi_kunden_id']);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropIndex(['ninox_lieferanten_id']);
            $table->dropColumn('ninox_lieferanten_id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['ninox_artikel_id']);
            $table->dropIndex(['wawi_artikel_id']);
            $table->dropColumn(['ninox_artikel_id', 'wawi_artikel_id']);
        });
    }
};
