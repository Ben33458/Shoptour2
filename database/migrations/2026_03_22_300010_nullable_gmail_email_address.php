<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gmail_sync_state', function (Blueprint $table) {
            $table->string('email_address', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('gmail_sync_state', function (Blueprint $table) {
            $table->string('email_address', 255)->nullable(false)->change();
        });
    }
};
