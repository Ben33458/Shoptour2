<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lexoffice_vouchers', function (Blueprint $table) {
            $table->timestamp('payments_fetched_at')->nullable()->after('synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('lexoffice_vouchers', function (Blueprint $table) {
            $table->dropColumn('payments_fetched_at');
        });
    }
};
