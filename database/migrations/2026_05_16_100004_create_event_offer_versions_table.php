<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_offer_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('event_occurrence_id')->index();
            $table->string('offer_number', 30)->nullable()->unique(); // ST-A-2026-000001 oder null für importierte
            $table->string('external_offer_number', 100)->nullable(); // JTL-Nr, Ninox-ID
            $table->unsignedSmallInteger('version_number')->default(1);
            $table->string('source_system', 50)->default('shoptour2'); // shoptour2,jtl,ninox,gmail,manual
            $table->string('status', 50)->default('draft'); // draft,external_offer_created,sent,revision_requested,accepted,rejected,expired,cancelled,converted_to_order
            $table->date('valid_until')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('converted_order_id')->nullable(); // FK zu orders
            $table->bigInteger('net_total_milli')->nullable();
            $table->bigInteger('gross_total_milli')->nullable();
            $table->bigInteger('deposit_total_milli')->nullable();
            $table->bigInteger('rental_total_milli')->nullable();
            $table->bigInteger('delivery_total_milli')->nullable();
            $table->string('pdf_file_path', 500)->nullable();
            $table->string('external_pdf_reference', 500)->nullable();
            $table->string('email_message_id', 255)->nullable(); // für spätere Gmail-Integration
            $table->string('conversion_status', 50)->nullable(); // ok, needs_review, failed
            $table->json('conversion_error')->nullable(); // Fehlerliste als JSON
            $table->index('offer_number');
            $table->index('external_offer_number');
            $table->index(['event_occurrence_id', 'version_number']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_offer_versions');
    }
};
