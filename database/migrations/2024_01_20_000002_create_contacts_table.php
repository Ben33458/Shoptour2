<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->morphs('contactable'); // contactable_type + contactable_id + index
            $table->string('name', 150);
            $table->string('phone', 50)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('role', 100)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
