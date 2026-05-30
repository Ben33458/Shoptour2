<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_logistics_appointments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('event_occurrence_id')->index();
            $table->string('type', 30); // delivery,pickup,empty_goods_pickup,additional_delivery,setup,dismantling,inspection,other
            $table->string('status', 30)->default('planned'); // planned,scheduled,in_progress,done,cancelled,failed
            $table->date('scheduled_date');
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();
            $table->string('time_flexibility', 30)->default('unknown'); // fixed,flexible,morning,afternoon,by_arrangement,unknown
            $table->text('address_snapshot')->nullable();
            $table->string('contact_name', 100)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->unsignedBigInteger('tour_id')->nullable();
            $table->unsignedBigInteger('vehicle_type_id')->nullable();
            $table->unsignedInteger('estimated_trips')->nullable();
            $table->unsignedInteger('actual_trips')->nullable();
            $table->boolean('helpers_available')->nullable();
            $table->text('access_notes')->nullable();
            $table->text('driver_notes')->nullable();
            $table->index('scheduled_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_logistics_appointments');
    }
};
