<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gebinde', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Human-readable name, e.g. "24er Kasten", "6er Träger"
            $table->string('name');

            // Packaging classification, e.g. "Kasten", "Flasche", "Dose", "Traeger"
            $table->string('gebinde_type')->nullable();

            // Every packaging unit carries a deposit set
            $table->unsignedBigInteger('pfand_set_id');

            // Physical material, e.g. "Glas", "PET", "Blech", "Karton"
            $table->string('material')->nullable();

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('pfand_set_id')
                ->references('id')
                ->on('pfand_sets')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gebinde');
    }
};
