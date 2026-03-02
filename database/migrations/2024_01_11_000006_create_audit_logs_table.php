<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only admin audit trail.
     * No updated_at — these records are never modified.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Who performed the action (null = system/CLI)
            $table->unsignedBigInteger('user_id')->nullable();

            // Short action identifier, e.g. "invoice.finalized", "adjustment.created", "csv.import.customers"
            $table->string('action', 100);

            // Optional polymorphic subject
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // Free-form JSON context (counts, filenames, old/new values, etc.)
            $table->json('meta_json')->nullable();

            // Append-only
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id'], 'audit_subject');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
