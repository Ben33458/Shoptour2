<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_codes', function (Blueprint $table) {
            $table->id();
            $table->char('blz', 8)->unique();
            $table->string('bank_name', 255);
            $table->string('bic', 11)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_codes');
    }
};
