<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('lexoffice_contact_id', 100)->nullable()->after('customer_number');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('lexoffice_voucher_id', 100)->nullable()->after('pdf_path');
            $table->timestamp('lexoffice_synced_at')->nullable()->after('lexoffice_voucher_id');
            $table->text('lexoffice_sync_error')->nullable()->after('lexoffice_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('lexoffice_contact_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['lexoffice_voucher_id', 'lexoffice_synced_at', 'lexoffice_sync_error']);
        });
    }
};