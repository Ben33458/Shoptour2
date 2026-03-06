<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pfand_sets', function (Blueprint $table) {
            $table->text('beschreibung')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('pfand_sets', function (Blueprint $table) {
            $table->dropColumn('beschreibung');
        });
    }
};
