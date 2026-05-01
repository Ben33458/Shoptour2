<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('employee_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open');
            $table->string('priority', 20)->default('normal');
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('ninox_task_id')->nullable()->comment('FK to ninox_77_regelmaessige_aufgaben.id');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
            $table->index(['assigned_to', 'status']);
            $table->index(['shift_id']);
            $table->index(['due_date', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('employee_tasks'); }
};
