<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pfand_set_components', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pfand_set_id');
            // Business rule: EXACTLY ONE of pfand_item_id OR child_pfand_set_id must be set.
            // A component references either a leaf PfandItem or a nested PfandSet.
            // This constraint is enforced at the application layer (PfandSetComponent model).
            $table->unsignedBigInteger('pfand_item_id')->nullable();
            $table->unsignedBigInteger('child_pfand_set_id')->nullable();
            $table->integer('qty');
            $table->timestamps();

            $table->foreign('pfand_set_id')
                ->references('id')
                ->on('pfand_sets')
                ->cascadeOnDelete();

            $table->foreign('pfand_item_id')
                ->references('id')
                ->on('pfand_items')
                ->restrictOnDelete();

            $table->foreign('child_pfand_set_id')
                ->references('id')
                ->on('pfand_sets')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pfand_set_components');
    }
};
