<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->boolean('po_filter_own_products')->default(false)->after('active')
                ->comment('Einkauf: nur Produkte anzeigen die bereits über diesen Lieferanten bezogen wurden');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn('po_filter_own_products');
        });
    }
};
