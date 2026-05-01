<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type', 60)->default('info');   // 'info' | 'correction' | 'warning'
            $table->string('title', 200);
            $table->text('message')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_notifications');
    }
};
