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
            $table->unsignedTinyInteger('dunning_level')->default(0)->after('payments_fetched_at');
            $table->boolean('is_dunning_blocked')->default(false)->after('dunning_level');
            $table->text('dunning_block_reason')->nullable()->after('is_dunning_blocked');
            $table->timestamp('last_dunned_at')->nullable()->after('dunning_block_reason');

            $table->index('dunning_level');
            $table->index('is_dunning_blocked');
        });
    }

    public function down(): void
    {
        Schema::table('lexoffice_vouchers', function (Blueprint $table) {
            $table->dropIndex(['dunning_level']);
            $table->dropIndex(['is_dunning_blocked']);
            $table->dropColumn(['dunning_level', 'is_dunning_blocked', 'dunning_block_reason', 'last_dunned_at']);
        });
    }
};
