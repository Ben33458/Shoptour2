<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_tasks', function (Blueprint $table) {
            $table->timestamp('timer_started_at')->nullable()->after('completed_by');
            $table->unsignedInteger('time_spent_seconds')->nullable()->after('timer_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('employee_tasks', function (Blueprint $table) {
            $table->dropColumn(['timer_started_at', 'time_spent_seconds']);
        });
    }
};
