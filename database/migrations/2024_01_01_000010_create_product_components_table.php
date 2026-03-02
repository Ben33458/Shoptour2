<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_components', function (Blueprint $table) {
            $table->bigIncrements('id');

            // The bundle product (must have is_bundle = true)
            $table->unsignedBigInteger('parent_product_id');

            // A component product contained within the bundle
            $table->unsignedBigInteger('child_product_id');

            $table->integer('qty');
            $table->timestamps();

            $table->foreign('parent_product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->foreign('child_product_id')
                ->references('id')
                ->on('products')
                ->restrictOnDelete();

            // Each child product can appear only once per bundle; qty handles count
            $table->unique(['parent_product_id', 'child_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_components');
    }
};
