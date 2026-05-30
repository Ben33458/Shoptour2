<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_occurrences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('event_series_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('billing_customer_id')->nullable()->index();
            $table->string('title', 255);
            $table->string('event_type', 50)->default('unknown');
            $table->boolean('is_recurring')->default(false);
            $table->smallInteger('event_year')->nullable();
            $table->dateTime('event_start_at')->nullable();
            $table->dateTime('event_end_at')->nullable();
            $table->unsignedSmallInteger('calendar_week')->nullable();
            $table->string('location_name', 255)->nullable();
            $table->string('address_line1', 255)->nullable();
            $table->string('address_line2', 255)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 10)->default('DE');
            $table->unsignedInteger('expected_guests')->nullable();
            $table->unsignedInteger('actual_guests')->nullable();
            $table->string('indoor_outdoor_type', 20)->nullable(); // indoor, outdoor, mixed
            $table->string('request_channel', 30)->default('unknown'); // email,phone,whatsapp,in_person,shop,jtl,ninox,other,unknown
            $table->string('offer_status', 50)->default('requested'); // requested,needs_clarification,draft,external_offer_created,sent,revision_requested,accepted,rejected,expired,cancelled,converted_to_order
            $table->string('event_status', 50)->default('planned'); // planned,confirmed,delivery_planned,partially_delivered,delivered,during_event,pickup_planned,picked_up,post_calculation_open,closed
            $table->string('source_system', 50)->nullable();
            $table->string('source_table', 100)->nullable();
            $table->string('source_record_id', 100)->nullable();
            $table->decimal('import_confidence', 3, 2)->default(1.00);
            $table->boolean('needs_review')->default(false);
            $table->text('internal_notes')->nullable();
            $table->text('customer_visible_notes')->nullable();
            $table->index(['source_system', 'source_record_id'], 'event_occ_source');
            $table->index(['offer_status', 'event_year']);
            $table->index(['event_start_at']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_occurrences');
    }
};
