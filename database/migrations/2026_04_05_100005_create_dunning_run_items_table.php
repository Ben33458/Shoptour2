<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dunning_run_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dunning_run_id')->index();
            $table->unsignedBigInteger('customer_id')->index();

            // Channel: email | post
            $table->string('channel')->default('email');

            // Dunning level for this customer in this run (1 or 2)
            $table->unsignedTinyInteger('dunning_level')->default(1);

            // Total open amount across all included vouchers (milli-cents)
            $table->unsignedBigInteger('total_open_milli')->default(0);

            // Interest/fees added (milli-cents)
            $table->unsignedBigInteger('interest_milli')->default(0);
            $table->unsignedBigInteger('flat_fee_milli')->default(0);

            // JSON array of lexoffice_voucher IDs included in this dunning notice
            $table->json('voucher_ids')->nullable();

            $table->string('recipient_email')->nullable();
            $table->string('recipient_name')->nullable();

            // Status: pending | sent | failed | skipped
            $table->string('status')->default('pending');

            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();

            // Path to generated dunning PDF in storage
            $table->string('pdf_path', 500)->nullable();

            $table->timestamps();

            $table->foreign('dunning_run_id')
                ->references('id')->on('dunning_runs')
                ->cascadeOnDelete();

            $table->foreign('customer_id')
                ->references('id')->on('customers')
                ->restrictOnDelete();

            $table->index(['dunning_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dunning_run_items');
    }
};
