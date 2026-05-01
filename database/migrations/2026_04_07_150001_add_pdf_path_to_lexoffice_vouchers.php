<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lexoffice_vouchers', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('raw_json');
            $table->timestamp('pdf_fetched_at')->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('lexoffice_vouchers', function (Blueprint $table) {
            $table->dropColumn(['pdf_path', 'pdf_fetched_at']);
        });
    }
};
