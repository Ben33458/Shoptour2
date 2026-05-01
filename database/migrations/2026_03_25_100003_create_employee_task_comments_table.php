<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('employee_tasks')->onDelete('cascade');
            $table->enum('author_type', ['user', 'employee']);
            $table->unsignedBigInteger('author_id');
            $table->text('body');
            $table->json('images')->nullable();
            $table->boolean('is_liveblog')->default(false);
            $table->timestamps();

            $table->index(['task_id']);
            $table->index(['is_liveblog', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_task_comments');
    }
};
