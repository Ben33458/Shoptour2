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
        Schema::table('lexoffice_vouchers', function (Blueprint $table) {
            $table->text('assignment_note')->nullable()->after('pdf_fetched_at');
            $table->timestamp('manually_confirmed_at')->nullable()->after('assignment_note');
        });
    }

    public function down(): void
    {
        Schema::table('lexoffice_vouchers', function (Blueprint $table) {
            $table->dropColumn(['assignment_note', 'manually_confirmed_at']);
        });
    }
};
