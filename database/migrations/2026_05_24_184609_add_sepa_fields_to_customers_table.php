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
            $table->string('iban', 34)->nullable()->after('birth_date');
            $table->string('iban_account_holder', 100)->nullable()->after('iban');
            $table->string('sepa_mandate_ref', 50)->nullable()->after('iban_account_holder');
            $table->date('sepa_mandate_date')->nullable()->after('sepa_mandate_ref');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['iban', 'iban_account_holder', 'sepa_mandate_ref', 'sepa_mandate_date']);
        });
    }
};
