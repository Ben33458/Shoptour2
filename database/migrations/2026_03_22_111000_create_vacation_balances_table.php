<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vacation_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('total_days')->default(0);
            $table->unsignedSmallInteger('used_days')->default(0);
            $table->smallInteger('carried_over')->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'year']);
        });
    }
    public function down(): void { Schema::dropIfExists('vacation_balances'); }
};
