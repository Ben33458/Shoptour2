<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gmail_sync_state', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();

            $table->string('email_address', 255);
            $table->string('last_history_id', 50)->nullable(); // Gmail History API cursor
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('idle'); // idle | running | error
            $table->text('error_message')->nullable();

            // Tokens verschlüsselt via encrypt()/decrypt()
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            $table->timestamps();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gmail_sync_state');
    }
};
