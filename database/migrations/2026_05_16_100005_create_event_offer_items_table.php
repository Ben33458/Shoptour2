<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_offer_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('event_offer_version_id')->index();
            $table->unsignedBigInteger('article_id')->nullable(); // FK zu products
            $table->string('external_article_number', 100)->nullable();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 30)->default('Stück');
            $table->bigInteger('unit_price_net_milli')->nullable();
            $table->bigInteger('unit_price_gross_milli')->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->bigInteger('deposit_amount_milli')->nullable();
            $table->bigInteger('line_total_net_milli')->nullable();
            $table->bigInteger('line_total_gross_milli')->nullable();
            $table->string('item_type', 30)->default('beverage'); // beverage,rental,delivery,pickup,deposit,discount,fee,free_text,other
            $table->boolean('is_optional')->default(false);
            $table->boolean('is_alternative')->default(false);
            $table->string('alternative_group', 50)->nullable();
            $table->boolean('is_free_text')->default(false);
            $table->boolean('supplied_by_us')->default(true);
            $table->text('third_party_supply_note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_offer_items');
    }
};
