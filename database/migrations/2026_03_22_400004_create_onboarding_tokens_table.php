<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique()->comment('SHA-256 of URL token');
            $table->char('code', 6)->comment('6-digit numeric fallback code');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_tokens');
    }
};
