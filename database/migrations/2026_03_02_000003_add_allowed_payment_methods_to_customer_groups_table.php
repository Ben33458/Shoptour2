<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROJ-4: Add allowed_payment_methods JSON column to customer_groups.
 *
 * Stores an array of payment method strings that customers in this group
 * may choose at checkout. Null means all methods are allowed.
 *
 * Example: ["stripe", "paypal", "invoice", "cash"]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_groups', function (Blueprint $table): void {
            $table->json('allowed_payment_methods')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('customer_groups', function (Blueprint $table): void {
            $table->dropColumn('allowed_payment_methods');
        });
    }
};
