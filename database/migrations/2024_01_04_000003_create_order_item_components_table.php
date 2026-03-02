<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_components', function (Blueprint $table) {
            $table->bigIncrements('id');

            // The bundle order_item this component belongs to
            $table->unsignedBigInteger('order_item_id');

            // The leaf product that was resolved from the bundle at order time.
            // Not FK-constrained (same reasoning as order_items.product_id).
            $table->unsignedBigInteger('component_product_id');

            // Frozen product name and artikelnummer at order time
            $table->string('component_product_name_snapshot');
            $table->string('component_artikelnummer_snapshot');

            // How many units of this component are contained per bundle unit,
            // AFTER recursive flattening. E.g. a "24er Kasten" bundle
            // containing 2× "6er Träger" (each 6 bottles) would record qty_per_bundle = 12
            // for the leaf bottle product.
            $table->integer('qty_per_bundle');

            // Total quantity = order_item.qty × qty_per_bundle
            $table->integer('qty_total');

            $table->timestamps();

            $table->foreign('order_item_id')
                ->references('id')
                ->on('order_items')
                ->cascadeOnDelete();

            $table->index('order_item_id');
            $table->index('component_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_components');
    }
};
