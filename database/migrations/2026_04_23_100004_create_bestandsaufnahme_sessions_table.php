<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bestandsaufnahme_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            $table->unsignedBigInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');

            $table->string('titel', 200)->nullable();
            $table->enum('status', ['offen', 'pausiert', 'abgeschlossen'])->default('offen');

            $table->unsignedBigInteger('gestartet_von');
            $table->foreign('gestartet_von')->references('id')->on('users')->onDelete('restrict');

            $table->timestamp('gestartet_am')->useCurrent();
            $table->timestamp('abgeschlossen_am')->nullable();
            $table->text('notiz')->nullable();

            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bestandsaufnahme_sessions');
    }
};
