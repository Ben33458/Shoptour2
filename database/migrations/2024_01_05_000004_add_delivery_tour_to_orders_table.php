<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Which regular tour this order is assigned to (set at checkout).
            // Nullable: orders placed without a tour selection (e.g. pickup) have null.
            $table->unsignedBigInteger('regular_delivery_tour_id')->nullable()->after('warehouse_id');

            // Placeholder for a future delivery address entity.
            // Stored as plain bigint — no FK constraint until the Address module exists.
            $table->unsignedBigInteger('delivery_address_id')->nullable()->after('regular_delivery_tour_id');

            $table->foreign('regular_delivery_tour_id')
                ->references('id')
                ->on('regular_delivery_tours')
                ->nullOnDelete();

            // Speeds up TourPlannerService query:
            //   WHERE delivery_date = ? AND regular_delivery_tour_id = ?
            $table->index(
                ['regular_delivery_tour_id', 'delivery_date'],
                'orders_tour_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['regular_delivery_tour_id']);
            $table->dropIndex('orders_tour_date_idx');
            $table->dropColumn(['regular_delivery_tour_id', 'delivery_address_id']);
        });
    }
};
