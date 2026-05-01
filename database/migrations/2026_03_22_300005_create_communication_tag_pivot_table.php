<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_tag_pivot', function (Blueprint $table) {
            $table->unsignedBigInteger('communication_id');
            $table->unsignedBigInteger('tag_id');

            $table->foreign('communication_id')->references('id')->on('communications')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('communication_tags')->cascadeOnDelete();

            $table->primary(['communication_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_tag_pivot');
    }
};
