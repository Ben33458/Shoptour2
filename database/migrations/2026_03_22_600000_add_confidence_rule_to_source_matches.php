<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds confidence (0–100) and rule (match rule name) to source_matches.
 * These are useful for debugging and the employee reconcile UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_matches', function (Blueprint $table): void {
            $table->unsignedTinyInteger('confidence')->nullable()->after('status');
            $table->string('rule', 64)->nullable()->after('confidence');
        });
    }

    public function down(): void
    {
        Schema::table('source_matches', function (Blueprint $table): void {
            $table->dropColumn(['confidence', 'rule']);
        });
    }
};
