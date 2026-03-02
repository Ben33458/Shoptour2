<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Payment provider identifier (stripe | null = manual)
            $table->string('provider', 50)->nullable()->after('payment_method');
            // Provider-side reference for idempotency (e.g. Stripe checkout session id)
            $table->string('provider_ref', 200)->nullable()->unique()->after('provider');
            // Full raw webhook payload for audit
            $table->longText('raw_json')->nullable()->after('provider_ref');
            // Payment lifecycle status (pending | paid | failed | refunded)
            // Default 'paid' keeps existing manual payment records valid
            $table->string('status', 20)->default('paid')->after('raw_json');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['provider_ref']);
            $table->dropColumn(['provider', 'provider_ref', 'raw_json', 'status']);
        });
    }
};