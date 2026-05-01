<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('communication_id');
            $table->foreign('communication_id')->references('id')->on('communications')->cascadeOnDelete();

            $table->string('filename', 500);
            $table->string('mime_type', 200)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->string('storage_path', 1000)->nullable(); // relativ zu storage/app/
            $table->char('sha256_hash', 64)->nullable()->index();

            $table->string('processing_status', 20)->default('pending'); // pending | processed | error

            $table->longText('extracted_text')->nullable();
            $table->timestamp('extracted_at')->nullable();

            $table->string('gmail_attachment_id', 200)->nullable(); // Gmail Part ID für späten Download
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_attachments');
    }
};
