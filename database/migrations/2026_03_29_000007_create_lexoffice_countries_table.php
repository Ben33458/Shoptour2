<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lexoffice_countries', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 10)->unique();
            $table->string('country_name_de', 100);
            $table->string('country_name_en', 100);
            $table->string('tax_classification', 50)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lexoffice_countries');
    }
};
