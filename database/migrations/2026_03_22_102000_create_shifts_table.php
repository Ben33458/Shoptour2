<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_area_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('planned_start');
            $table->dateTime('planned_end');
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_end')->nullable();
            $table->string('status', 20)->default('planned');
            $table->boolean('auto_closed_by_system')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'planned_start']);
            $table->index(['planned_start', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('shifts'); }
};
