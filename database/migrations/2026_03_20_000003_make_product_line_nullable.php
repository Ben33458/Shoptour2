<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes product_line_id nullable on the products table.
 *
 * Previously required (NOT NULL). After this migration, products can be created
 * without assigning a product line — simplifying the import workflow from
 * Ninox / JTL-WaWi where product lines may not be known yet.
 *
 * Existing products are not changed; their product_line_id remains as-is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_line_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Revert only safe if all rows already have a product_line_id set.
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_line_id')->nullable(false)->change();
        });
    }
};
