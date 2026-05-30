<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('docscan_upload_id')->nullable()->after('document_id');
            $table->string('ninox_bestellung_id')->nullable()->after('notiz');
            $table->timestamp('ninox_pushed_at')->nullable()->after('ninox_bestellung_id');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropColumn(['docscan_upload_id', 'ninox_bestellung_id', 'ninox_pushed_at']);
        });
    }
};
