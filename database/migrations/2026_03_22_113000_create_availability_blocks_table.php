<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('availability_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('type', ['unavailable','preferred','flexible'])->default('unavailable');
            $table->time('from_time')->nullable()->comment('NULL = full day');
            $table->time('to_time')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'date']);
        });
    }
    public function down(): void { Schema::dropIfExists('availability_blocks'); }
};
