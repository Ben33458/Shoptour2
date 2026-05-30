<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_post_calculations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('event_occurrence_id')->unique(); // 1:1
            $table->unsignedInteger('actual_guests')->nullable();
            $table->unsignedInteger('planned_guests')->nullable();
            $table->bigInteger('planned_gross_total_milli')->nullable();
            $table->bigInteger('actual_gross_total_milli')->nullable();
            $table->bigInteger('revenue_per_guest_milli')->nullable();
            $table->bigInteger('delivered_value_milli')->nullable();
            $table->bigInteger('returned_full_goods_value_milli')->nullable();
            $table->bigInteger('deposit_difference_milli')->nullable();
            $table->text('empty_goods_notes')->nullable();
            $table->boolean('third_party_drinks_present')->nullable();
            $table->boolean('exclusive_supplier')->nullable();
            $table->json('self_supplied_categories')->nullable();
            $table->text('special_events_notes')->nullable();
            $table->text('deviation_from_previous_year_notes')->nullable();
            $table->text('recommendation_next_year')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_post_calculations');
    }
};
