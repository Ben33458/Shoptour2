<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ergänzt stock_movements um:
     * - korrekturgrund: fester Code bei manuellen Korrekturen
     * - bestandsaufnahme_session_id: Bezug zur Zähl-Session
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('korrekturgrund', 50)->nullable()->after('note');
            $table->unsignedBigInteger('bestandsaufnahme_session_id')->nullable()->after('korrekturgrund');
            $table->foreign('bestandsaufnahme_session_id')
                ->references('id')->on('bestandsaufnahme_sessions')->onDelete('set null');

            $table->index('bestandsaufnahme_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['bestandsaufnahme_session_id']);
            $table->dropIndex(['bestandsaufnahme_session_id']);
            $table->dropColumn(['korrekturgrund', 'bestandsaufnahme_session_id']);
        });
    }
};
