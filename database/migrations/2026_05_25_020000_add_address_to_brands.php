<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('adresse', 255)->nullable()->after('name');
            $table->string('plz', 10)->nullable()->after('adresse');
            $table->string('ort', 100)->nullable()->after('plz');
            $table->string('land', 100)->nullable()->default('Deutschland')->after('ort');
            $table->string('email', 255)->nullable()->after('land');
            $table->string('website', 255)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['adresse', 'plz', 'ort', 'land', 'email', 'website']);
        });
    }
};
