<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('driver_uploads', function (Blueprint $table) {
            $table->string('immich_asset_id', 36)->nullable()->after('status');
            $table->string('immich_status', 20)->nullable()->after('immich_asset_id');
            // immich_status: offen | geprueft | abgerechnet | null (not yet in Immich)
        });
    }

    public function down(): void
    {
        Schema::table('driver_uploads', function (Blueprint $table) {
            $table->dropColumn(['immich_asset_id', 'immich_status']);
        });
    }
};
