<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('product_line_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('gebinde_id');

            // Intentionally not FK-constrained: tax_rates table does not exist yet.
            // Will be enforced once the Tax module is introduced.
            $table->unsignedBigInteger('tax_rate_id');

            $table->string('artikelnummer')->unique();
            $table->string('produktname');

            // All monetary values stored as milli-cents (1/1000 of a cent) for precision
            $table->integer('base_price_net_milli');
            $table->integer('base_price_gross_milli');

            // When true, this product is a bundle composed of child products via product_components
            $table->boolean('is_bundle')->default(false);

            // Availability mode: e.g. "available", "preorder", "discontinued", "out_of_stock"
            $table->string('availability_mode');

            // Used when availability_mode = "preorder"
            $table->integer('preorder_lead_days')->nullable();
            $table->text('preorder_note')->nullable();

            // Display hint for sales unit, e.g. "per Kasten (24 Flaschen)"
            $table->text('sales_unit_note')->nullable();

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->restrictOnDelete();

            $table->foreign('product_line_id')
                ->references('id')
                ->on('product_lines')
                ->restrictOnDelete();

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->restrictOnDelete();

            $table->foreign('gebinde_id')
                ->references('id')
                ->on('gebinde')
                ->restrictOnDelete();

            // Composite index to speed up catalog filtering by brand / line / category
            $table->index(['brand_id', 'product_line_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
