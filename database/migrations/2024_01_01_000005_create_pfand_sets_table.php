<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pfand_sets', function (Blueprint $table) {
            $table->bigIncrements('id');

            // A PfandSet groups multiple pfand items for a packaging unit.
            // Example: A crate set = 1x Kasten-Pfand + 12x Flaschen-Pfand
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pfand_sets');
    }
};
