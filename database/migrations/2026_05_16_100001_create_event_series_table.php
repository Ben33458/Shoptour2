<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_series', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('name', 255);
            $table->string('event_type', 50)->default('unknown'); // kerb,fastnacht,sommerfest,geburtstag,hochzeit,kommunion,firmenfeier,vereinsfest,weihnachtsmarkt,sonstiges,unknown
            $table->string('default_location_name', 255)->nullable();
            $table->text('default_address')->nullable();
            $table->text('default_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_duplicate_suspect')->default(false);
            $table->string('source_system', 50)->nullable(); // ninox, shoptour2, manual
            $table->string('source_table', 100)->nullable();
            $table->string('source_record_id', 100)->nullable();
            $table->index(['source_system', 'source_record_id'], 'event_series_source');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_series');
    }
};
