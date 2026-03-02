<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-21: Customer address book.
 *
 * Each customer can have multiple addresses of type "delivery" or "billing".
 * One address per type can be flagged as default.
 *
 * Note: orders.delivery_address_id already exists as a nullable FK placeholder
 * (added in an earlier migration) that now references this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['delivery', 'billing']);
            $table->boolean('is_default')->default(false);
            $table->string('label', 100)->nullable();         // e.g. "Büro", "Zuhause"
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('company', 200)->nullable();
            $table->string('street', 200);
            $table->string('house_number', 20)->nullable();
            $table->string('zip', 10);
            $table->string('city', 100);
            $table->string('country_code', 2)->default('DE');
            $table->string('phone', 50)->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'type', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
