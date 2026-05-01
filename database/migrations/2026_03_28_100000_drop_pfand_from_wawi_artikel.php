<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wawi_artikel', function (Blueprint $table) {
            $table->dropColumn('pfand_betrag_netto');
        });
    }

    public function down(): void
    {
        Schema::table('wawi_artikel', function (Blueprint $table) {
            $table->decimal('pfand_betrag_netto', 10, 4)->nullable()->after('fEKNetto');
        });
    }
};
