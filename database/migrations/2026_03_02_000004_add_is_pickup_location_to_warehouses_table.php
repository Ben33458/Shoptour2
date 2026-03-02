<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROJ-4: Mark warehouses that can serve as customer pickup locations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->boolean('is_pickup_location')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->dropColumn('is_pickup_location');
        });
    }
};
