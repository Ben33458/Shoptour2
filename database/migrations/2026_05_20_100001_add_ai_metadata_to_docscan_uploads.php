<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('docscan_uploads', function (Blueprint $table) {
            $table->json('ai_metadata')->nullable()->after('ai_reason');
        });
    }

    public function down(): void
    {
        Schema::table('docscan_uploads', function (Blueprint $table) {
            $table->dropColumn('ai_metadata');
        });
    }
};
