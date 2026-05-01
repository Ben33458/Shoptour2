<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lexoffice_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('lexoffice_uuid', 36)->unique();
            $table->unsignedInteger('version')->default(0);
            $table->boolean('archived')->default(false);
            $table->string('article_number', 100)->nullable()->index();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('unit_name', 50)->nullable();
            $table->string('type', 32)->nullable();
            $table->string('gtin', 100)->nullable();
            $table->unsignedBigInteger('price_net')->default(0);
            $table->unsignedBigInteger('price_gross')->default(0);
            $table->decimal('tax_rate_percent', 5, 2)->nullable();
            $table->json('raw_json');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lexoffice_articles');
    }
};
