<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missing_product_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('artikelnummer', 50)->nullable();
            $table->string('produktname', 255);
            $table->text('info_text')->nullable();
            $table->enum('status', ['open', 'ordered', 'done', 'rejected'])->default('open');
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index('employee_id');
            $table->index('status');
            $table->index('created_at');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missing_product_reports');
    }
};
