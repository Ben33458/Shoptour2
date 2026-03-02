<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-21: Add Google OAuth fields to users table.
 *
 * Enables customers to sign in / register via Google Socialite.
 * google_id is unique so duplicate registrations via Google are prevented.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->string('google_id', 100)->nullable()->unique()->after('password');
            $table->string('avatar_url', 500)->nullable()->after('google_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn(['google_id', 'avatar_url']);
        });
    }
};
