<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend driver_api_tokens with lifecycle columns.
 *
 * last_used_at     – updated on every successful auth; useful for detecting stale tokens
 * expires_at       – optional hard expiry; null = never expires
 * revoked_at       – set when admin revokes the token; not null = rejected
 * created_by_user_id – admin user who issued the token
 * company_id       – optional multi-company scoping
 *
 * Note: label column already exists from the initial migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_api_tokens', function (Blueprint $table): void {
            $table->timestamp('last_used_at')->nullable()->after('active');
            $table->timestamp('expires_at')->nullable()->after('last_used_at');
            $table->timestamp('revoked_at')->nullable()->after('expires_at');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->after('revoked_at');
            $table->unsignedBigInteger('company_id')->nullable()->after('created_by_user_id');

            $table->index('revoked_at', 'dat_revoked_idx');
            $table->index('company_id', 'dat_company_idx');
        });
    }

    public function down(): void
    {
        Schema::table('driver_api_tokens', function (Blueprint $table): void {
            $table->dropIndex('dat_revoked_idx');
            $table->dropIndex('dat_company_idx');
            $table->dropColumn(['last_used_at', 'expires_at', 'revoked_at', 'created_by_user_id', 'company_id']);
        });
    }
};
