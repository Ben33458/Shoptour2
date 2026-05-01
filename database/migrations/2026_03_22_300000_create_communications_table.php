<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            $table->string('source', 20)->default('gmail'); // gmail | manual | phone
            $table->string('direction', 10)->default('in'); // in | out

            // Gmail identifiers
            $table->string('message_id', 500)->nullable()->unique(); // RFC Message-ID, dedup key
            $table->string('thread_id', 500)->nullable()->index();
            $table->string('gmail_id', 100)->nullable();

            // Addresses
            $table->string('from_address', 255)->nullable();
            $table->json('to_addresses')->nullable();
            $table->json('cc_addresses')->nullable();

            // Content
            $table->string('subject', 500)->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('snippet', 500)->nullable();

            // Timing
            $table->timestamp('received_at')->nullable();
            $table->timestamp('imported_at')->nullable();

            // Status
            $table->string('status', 20)->default('new'); // new | review | assigned | archived
            $table->index(['company_id', 'status']);

            // Assignment (polymorphic)
            $table->string('communicable_type', 100)->nullable();
            $table->unsignedBigInteger('communicable_id')->nullable();
            $table->index(['communicable_type', 'communicable_id']);

            // Sender contact
            $table->unsignedBigInteger('sender_contact_id')->nullable();
            $table->foreign('sender_contact_id')->references('id')->on('contacts')->nullOnDelete();

            // Confidence
            $table->unsignedTinyInteger('overall_confidence')->nullable();

            // Review
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->foreign('reviewed_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();

            // Raw
            $table->json('raw_headers')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
