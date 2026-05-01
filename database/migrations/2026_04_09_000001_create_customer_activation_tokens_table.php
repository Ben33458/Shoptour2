<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_activation_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('email');
            $table->char('code_hash', 64);          // SHA-256 of 6-digit numeric code
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->tinyInteger('verify_attempts')->default(0); // how many wrong code entries
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index(['customer_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_activation_tokens');
    }
};
