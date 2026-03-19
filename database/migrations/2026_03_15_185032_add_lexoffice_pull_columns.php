<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // suppliers: Lexoffice-Kontakt-ID (vendor role)
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('lexoffice_contact_id', 36)->nullable()->after('lieferanten_nr');
            $table->index('lexoffice_contact_id');
        });

        // invoices: Zahlungsstatus aus Lexoffice
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('lexoffice_payment_status', 32)->nullable()->after('lexoffice_sync_error');
            $table->timestamp('lexoffice_paid_at')->nullable()->after('lexoffice_payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['lexoffice_contact_id']);
            $table->dropColumn('lexoffice_contact_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['lexoffice_payment_status', 'lexoffice_paid_at']);
        });
    }
};
