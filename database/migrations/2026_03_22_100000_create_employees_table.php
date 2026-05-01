<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_number', 20)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->unique()->nullable();
            $table->string('phone', 30)->nullable();
            $table->date('birth_date')->nullable();
            $table->date('hire_date');
            $table->date('leave_date')->nullable();
            $table->enum('role', ['admin','manager','teamleader','employee'])->default('employee');
            $table->enum('employment_type', ['full_time','part_time','mini_job','intern'])->default('full_time');
            $table->unsignedSmallInteger('weekly_hours')->default(40);
            $table->unsignedSmallInteger('vacation_days_per_year')->default(24);
            $table->boolean('is_active')->default(true);
            $table->string('pin_hash', 255)->nullable()->comment('Bcrypt hash of 4-digit PIN for time clock');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'role']);
        });
    }
    public function down(): void { Schema::dropIfExists('employees'); }
};
