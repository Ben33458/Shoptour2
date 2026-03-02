<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-18: DB-backed deferred task queue.
 *
 * Replaces a persistent queue worker on shared hosting where only cron is
 * available. The kolabri:tasks:run command is scheduled every 5 minutes via
 * cron and processes pending rows.
 *
 * Status lifecycle: pending → running → done
 *                   running → pending  (retry after transient failure)
 *                   running → failed   (max_attempts exhausted)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deferred_tasks', static function (Blueprint $table): void {
            $table->bigIncrements('id');

            // Task type identifier, e.g. 'lexoffice.sync_invoice', 'email.invoice_available'
            $table->string('type', 100);

            // JSON payload with task-specific data (model IDs, email addresses, etc.)
            $table->text('payload_json');

            // Current status: pending | running | done | failed
            $table->string('status', 20)->default('pending');

            // How many times this task has been attempted
            $table->unsignedInteger('attempts')->default(0);

            // Maximum allowed attempts before marking as failed
            $table->unsignedInteger('max_attempts')->default(3);

            // Last error message (set on failure, cleared on retry)
            $table->text('last_error')->nullable();

            // Delay support: null = process immediately, future timestamp = wait until then
            $table->timestamp('run_after')->nullable();

            $table->timestamps();

            // Runner query: pick up pending tasks that are ready to run
            $table->index(['status', 'run_after'], 'deferred_tasks_status_run_after_idx');

            // UI filtering: list tasks by type and status
            $table->index(['type', 'status'], 'deferred_tasks_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deferred_tasks');
    }
};
