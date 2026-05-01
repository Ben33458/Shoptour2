<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logs every email sent to an employee.
 * Allows admins to see what was sent, when, by whom, and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_employee_emails', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('to_address');
            $table->string('subject');
            $table->string('type', 50)->comment('welcome, onboarding, info, custom …');
            $table->text('body_preview')->nullable()->comment('First 500 chars of rendered body');
            $table->string('triggered_by', 100)->nullable()->comment('syncNinox, onboarding, manual …');
            $table->unsignedBigInteger('sent_by_user_id')->nullable()->comment('Admin who triggered send');
            $table->string('status', 20)->default('sent')->comment('sent, failed');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_employee_emails');
    }
};
