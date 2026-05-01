<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clear any non-JSON values before changing column type
        \DB::statement("UPDATE users SET zustaendigkeit = NULL WHERE zustaendigkeit IS NOT NULL AND zustaendigkeit NOT LIKE '[%'");

        Schema::table('users', function (Blueprint $table): void {
            $table->json('zustaendigkeit')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('zustaendigkeit', 100)->nullable()->change();
        });
    }
};
