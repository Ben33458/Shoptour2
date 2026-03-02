<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend audit_logs with company_id and token_id columns.
 *
 * company_id  – which company context was active when the action occurred
 * token_id    – driver API token ID when action was triggered by a driver (not a web user)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('token_id')->nullable()->after('company_id');

            $table->index('company_id', 'audit_company_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_company_idx');
            $table->dropColumn(['company_id', 'token_id']);
        });
    }
};
