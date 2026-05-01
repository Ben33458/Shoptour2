<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->string('category', 50)->nullable()->after('type');
            $table->unsignedBigInteger('transfer_target_register_id')->nullable()->after('category');
            $table->foreign('transfer_target_register_id')
                ->references('id')
                ->on('cash_registers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->dropForeign(['transfer_target_register_id']);
            $table->dropColumn(['category', 'transfer_target_register_id']);
        });
    }
};
