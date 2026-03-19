<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Type: lexoffice_diff | manual | system
            $table->string('type', 32)->default('manual')->index();

            // Short headline shown in list
            $table->string('subject', 255);

            // Free-text body, markdown-friendly
            $table->text('body')->nullable();

            // Structured payload (e.g. diff details for lexoffice_diff)
            $table->json('meta_json')->nullable();

            // Review workflow
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Who created the note (null = system)
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Append-only → no updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'type']);
            $table->index(['customer_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notes');
    }
};
