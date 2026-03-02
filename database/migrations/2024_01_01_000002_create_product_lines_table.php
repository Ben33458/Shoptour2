<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('brand_id');
            $table->string('name');
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->restrictOnDelete();

            // A brand cannot have two product lines with the same name
            $table->unique(['brand_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_lines');
    }
};
