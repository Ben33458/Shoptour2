<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_task_completions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ninox_task_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->json('images')->nullable();          // array of storage paths
            $table->date('next_due_date')->nullable();   // calculated next due date
            $table->timestamp('completed_at')->useCurrent();

            $table->index(['ninox_task_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_task_completions');
    }
};
