<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regular_delivery_tours', function (Blueprint $table) {
            $table->unsignedInteger('ninox_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('regular_delivery_tours', function (Blueprint $table) {
            $table->dropUnique(['ninox_id']);
            $table->dropColumn('ninox_id');
        });
    }
};
