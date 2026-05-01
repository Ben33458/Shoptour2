<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('billing_email')->nullable()->after('email');
            $table->string('notification_email')->nullable()->after('billing_email');
            $table->boolean('email_notification_shipping')->default(true)->after('notification_email');
            // 'all' | 'important_only' | 'none'
            $table->string('newsletter_consent')->default('important_only')->after('email_notification_shipping');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['billing_email', 'notification_email', 'email_notification_shipping', 'newsletter_consent']);
        });
    }
};
