<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROJ-2: Create warengruppen (product groups) table.
 *
 * Warengruppen are an additional grouping axis for products,
 * orthogonal to categories. Examples: "Softdrinks", "Bier", "Wasser".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warengruppen', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->nullOnDelete();
            $table->index('company_id', 'warengruppen_company_id_idx');

            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warengruppen');
    }
};
