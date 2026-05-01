<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('contactable_type')->nullable()->change();
            $table->unsignedBigInteger('contactable_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('contactable_type')->nullable(false)->change();
            $table->unsignedBigInteger('contactable_id')->nullable(false)->change();
        });
    }
};
