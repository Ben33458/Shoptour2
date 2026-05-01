<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('name');
            $table->string('street', 255)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 10)->default('DE');
            $table->decimal('geo_lat', 10, 7)->nullable();
            $table->decimal('geo_lng', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->string('source_type', 50)->nullable(); // manual, ninox, external_api
            $table->string('source_id', 100)->nullable();
            $table->timestamps();
            $table->index(['zip', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_locations');
    }
};
