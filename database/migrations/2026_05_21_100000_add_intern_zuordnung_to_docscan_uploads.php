<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('docscan_uploads', function (Blueprint $table) {
            $table->enum('intern_typ', ['anlieferung', 'lieferschein_kunden'])->nullable()->after('error_message');
            $table->unsignedBigInteger('intern_id')->nullable()->after('intern_typ');
            $table->timestamp('intern_zugeordnet_at')->nullable()->after('intern_id');
            $table->unsignedBigInteger('intern_zugeordnet_by')->nullable()->after('intern_zugeordnet_at');
        });
    }

    public function down(): void
    {
        Schema::table('docscan_uploads', function (Blueprint $table) {
            $table->dropColumn(['intern_typ', 'intern_id', 'intern_zugeordnet_at', 'intern_zugeordnet_by']);
        });
    }
};
