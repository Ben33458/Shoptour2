<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('event_occurrence_id')->index();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('open'); // open,in_progress,done,cancelled
            $table->timestamp('due_at')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('task_type', 50)->default('other'); // clarification,offer,delivery,pickup,rental_check,payment,post_calculation,customer_followup,other
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_tasks');
    }
};
