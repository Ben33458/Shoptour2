<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->enum('device_type', ['public', 'private'])->default('public');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_preferences');
    }
};
