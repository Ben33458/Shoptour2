<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_contacts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('event_occurrence_id')->index();
            $table->unsignedBigInteger('customer_contact_id')->nullable();
            $table->string('source_contact_id', 100)->nullable(); // ninox_id
            $table->string('name', 255);
            $table->string('role', 30)->default('main'); // main,billing,delivery,setup,pickup,board,cashier,other
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->boolean('whatsapp_allowed')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_contacts');
    }
};
