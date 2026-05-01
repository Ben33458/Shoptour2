<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('shift_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->text('summary')->nullable();
            $table->unsignedSmallInteger('customer_count')->nullable();
            $table->decimal('cash_difference', 8, 2)->nullable()->comment('Euros, positive=surplus, negative=deficit');
            $table->enum('incident_level', ['none','minor','major'])->default('none');
            $table->text('incident_notes')->nullable();
            $table->boolean('is_submitted')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['shift_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('shift_reports'); }
};
