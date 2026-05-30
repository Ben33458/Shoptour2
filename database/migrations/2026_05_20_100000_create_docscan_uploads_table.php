<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('docscan_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('storage_path');
            $table->unsignedSmallInteger('page_count')->default(1);
            $table->enum('ai_suggestion', ['rechnung', 'dokument', 'beides', 'unknown'])->nullable();
            $table->text('ai_reason')->nullable();
            $table->enum('status', ['pending', 'routed', 'failed'])->default('pending');
            $table->enum('destination', ['paperless', 'lexoffice', 'both'])->nullable();
            $table->foreignId('routed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('routed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docscan_uploads');
    }
};
