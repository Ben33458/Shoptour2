<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_task_id')->nullable()->after('id');
            $table->unsignedBigInteger('depends_on_task_id')->nullable()->after('parent_task_id');
            $table->text('body')->nullable()->after('description');
            $table->json('images')->nullable()->after('body');

            $table->foreign('parent_task_id')->references('id')->on('employee_tasks')->onDelete('set null');
            $table->foreign('depends_on_task_id')->references('id')->on('employee_tasks')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('employee_tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_task_id']);
            $table->dropForeign(['depends_on_task_id']);
            $table->dropColumn(['parent_task_id', 'depends_on_task_id', 'body', 'images']);
        });
    }
};
