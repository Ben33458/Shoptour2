<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table): void {
            $table->enum('register_type', ['wallet', 'safe', 'register', 'bank'])
                ->default('wallet')
                ->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table): void {
            $table->dropColumn('register_type');
        });
    }
};
