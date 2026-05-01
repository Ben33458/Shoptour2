<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->dateTime('clocked_in_at');
            $table->dateTime('clocked_out_at')->nullable();
            $table->unsignedSmallInteger('break_minutes')->default(0)->comment('Legal break deducted');
            $table->unsignedSmallInteger('net_minutes')->nullable()->comment('Worked minutes after break');
            $table->string('compliance_status', 20)->default('ok');
            $table->json('compliance_notes')->nullable()->comment('Array of compliance warning strings');
            $table->boolean('is_manual_correction')->default(false);
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'clocked_in_at']);
            $table->index(['shift_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('time_entries'); }
};
