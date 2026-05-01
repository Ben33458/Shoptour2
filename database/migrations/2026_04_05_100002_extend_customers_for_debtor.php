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
            // Lieferfreigabe / Liefersperre
            $table->string('delivery_status')->default('normal')->after('active');
            // normal | warning | blocked
            $table->string('delivery_condition')->nullable()->after('delivery_status');
            // cash_only | prepayment | stop_check
            $table->text('delivery_status_note')->nullable()->after('delivery_condition');
            $table->unsignedBigInteger('delivery_status_set_by')->nullable()->after('delivery_status_note');

            // Mahnwesen: Hold / Klärfall
            $table->boolean('debt_hold')->default(false)->after('delivery_status_set_by');
            $table->text('debt_hold_reason')->nullable()->after('debt_hold');

            $table->index('delivery_status');
            $table->index('debt_hold');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['delivery_status']);
            $table->dropIndex(['debt_hold']);
            $table->dropColumn([
                'delivery_status',
                'delivery_condition',
                'delivery_status_note',
                'delivery_status_set_by',
                'debt_hold',
                'debt_hold_reason',
            ]);
        });
    }
};
