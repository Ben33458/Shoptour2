<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gebinde_components', function (Blueprint $table) {
            $table->bigIncrements('id');

            // The composite packaging unit (e.g., a crate)
            $table->unsignedBigInteger('parent_gebinde_id');

            // The sub-unit contained within the parent (e.g., a bottle)
            $table->unsignedBigInteger('child_gebinde_id');

            $table->integer('qty');
            $table->timestamps();

            $table->foreign('parent_gebinde_id')
                ->references('id')
                ->on('gebinde')
                ->restrictOnDelete();

            $table->foreign('child_gebinde_id')
                ->references('id')
                ->on('gebinde')
                ->restrictOnDelete();

            // A parent can only contain each child type once; qty handles count
            $table->unique(['parent_gebinde_id', 'child_gebinde_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gebinde_components');
    }
};
