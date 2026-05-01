<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_leergut', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->string('leergut_art_nr', 50);
            $table->string('leergut_name');
            $table->bigInteger('unit_price_net_milli')->default(0);
            $table->bigInteger('unit_price_gross_milli')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();

            $table->unique('product_id', 'pl_product_id_unique');
            $table->index('company_id', 'pl_company_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_leergut');
    }
};
