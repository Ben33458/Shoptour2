<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Verwendetes Bestellprofil (optional, für Protokollierung)
            $table->unsignedBigInteger('order_profile_id')->nullable()->after('warehouse_id');
            $table->foreign('order_profile_id')
                ->references('id')->on('supplier_order_profiles')->onDelete('set null');

            // Tatsächlich verwendeter Kanal (kann vom Profil abweichen)
            $table->enum('bestellkanal', [
                'portal',
                'email_pdf',
                'email_csv',
                'email_xml',
                'upload_datei',
                'fallback_freitext',
            ])->nullable()->after('order_profile_id');

            // Kontrollstufen-Override für diesen Wareneingang (null = Lieferanten-Default)
            $table->enum('kontrollstufe_override', [
                'nur_angekommen',
                'summenkontrolle_vpe',
                'summenkontrolle_palette',
                'positionskontrolle',
                'positionskontrolle_mit_mhd',
            ])->nullable()->after('bestellkanal');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['order_profile_id']);
            $table->dropColumn(['order_profile_id', 'bestellkanal', 'kontrollstufe_override']);
        });
    }
};
