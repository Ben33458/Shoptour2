<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gebinde', function (Blueprint $table): void {
            $table->string('leergut_art_nr', 20)->nullable()->after('pfand_set_id');
        });

        // Set correct wawi_artikel art-nrs for Faß gebinde.
        // Faß deposits don't follow the "1"+cents art-nr pattern used for bottles/crates.
        DB::table('gebinde')->where('id', 70)->update(['leergut_art_nr' => '968']); // Pfand Faß 10 €
        DB::table('gebinde')->where('id', 72)->update(['leergut_art_nr' => '969']); // Pfand Faß 30 €
    }

    public function down(): void
    {
        Schema::table('gebinde', function (Blueprint $table): void {
            $table->dropColumn('leergut_art_nr');
        });
    }
};
