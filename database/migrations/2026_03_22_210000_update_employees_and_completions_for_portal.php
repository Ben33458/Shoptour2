<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Make user_id nullable in recurring_task_completions (portable version)
        Schema::table('recurring_task_completions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        // Add employee_id column
        Schema::table('recurring_task_completions', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('user_id')
                  ->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recurring_task_completions', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
        });

        Schema::table('recurring_task_completions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
