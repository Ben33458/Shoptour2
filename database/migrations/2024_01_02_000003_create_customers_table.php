<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Optional link to the Laravel auth user
            $table->unsignedBigInteger('user_id')->nullable();

            // Every customer belongs to exactly one pricing group.
            // The system's "default" group is referenced via app_settings.default_customer_group_id
            // and is applied automatically to guest sessions.
            $table->unsignedBigInteger('customer_group_id');

            // Human-readable unique customer identifier (e.g. "KD-00042")
            $table->string('customer_number')->unique();

            // "gross" – prices shown including VAT (B2C default)
            // "net"   – prices shown excluding VAT (B2B / business customers)
            $table->string('price_display_mode')->default('gross'); // gross|net

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('customer_group_id')
                ->references('id')
                ->on('customer_groups')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
