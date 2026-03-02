<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();

            // Source reference (nullable — at least one must be set)
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('adjustment_id')->nullable();

            // product | adjustment | deposit | shipping
            $table->string('line_type')->default('product');

            $table->string('description', 500);

            $table->decimal('qty', 12, 3)->default(1);

            // Milli-cents per unit
            $table->bigInteger('unit_price_net_milli')->default(0);
            $table->bigInteger('unit_price_gross_milli')->default(0);

            // Tax rate in basis points (190_000 = 19%)
            $table->unsignedInteger('tax_rate_basis_points')->default(0);

            // Pre-computed line totals
            $table->bigInteger('line_total_net_milli')->default(0);
            $table->bigInteger('line_total_gross_milli')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
