<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gemeinsames Mängel-/Asset-Modul für Fahrzeuge und Festbedarfs-Inventareinheiten.
 *
 * asset_type: vehicle | rental_inventory_unit
 * asset_id: FK to vehicles.id or rental_inventory_units.id
 *
 * blocks_usage: Fahrzeug/Gerät nicht einsatzbereit
 * blocks_rental: Mietartikel nicht verleihbar
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_issues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->enum('asset_type', ['vehicle', 'rental_inventory_unit']);
            $table->unsignedBigInteger('asset_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['open', 'scheduled', 'in_progress', 'resolved', 'closed'])
                ->default('open')
                ->index();
            $table->enum('severity', ['minor', 'moderate', 'major'])->default('moderate');
            $table->boolean('blocks_usage')->default(false);
            $table->boolean('blocks_rental')->default(false);
            // Estimated repair/replacement cost in milli-cents
            $table->unsignedBigInteger('estimated_cost_milli')->nullable();
            $table->string('workshop_name', 255)->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->foreign('assigned_to')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['asset_type', 'asset_id']);
            $table->index(['status', 'blocks_usage']);
            $table->index(['status', 'blocks_rental']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_issues');
    }
};
