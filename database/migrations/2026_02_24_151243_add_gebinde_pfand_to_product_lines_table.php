<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_lines', function (Blueprint $table): void {
            $table->unsignedBigInteger('gebinde_id')->nullable()->after('brand_id');
            $table->unsignedBigInteger('pfand_set_id')->nullable()->after('gebinde_id');

            $table->foreign('gebinde_id')
                ->references('id')->on('gebinde')
                ->nullOnDelete();

            $table->foreign('pfand_set_id')
                ->references('id')->on('pfand_sets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_lines', function (Blueprint $table): void {
            $table->dropForeign(['gebinde_id']);
            $table->dropForeign(['pfand_set_id']);
            $table->dropColumn(['gebinde_id', 'pfand_set_id']);
        });
    }
};
