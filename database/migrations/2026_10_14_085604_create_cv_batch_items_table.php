<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_batch_items', function (Blueprint $table) {
            $table->id();

            // Batch induk
            $table->foreignId('cv_batch_id')
                ->constrained('cv_batches')
                ->cascadeOnDelete();

            // Identitas CV sumber di SharePoint
            $table->string('source_item_id')->nullable();
            $table->string('source_filename');
            $table->text('source_path')->nullable();
            $table->text('source_web_url')->nullable();

            // Status pemrosesan setiap CV
            $table->string('status')->default('pending');

            // Lokasi dokumen Word hasil
            $table->string('output_path')->nullable();
            $table->string('output_filename')->nullable();

            // Informasi hasil dan kegagalan
            $table->text('error_message')->nullable();
            $table->json('validation_warnings')->nullable();

            // Waktu pemrosesan
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['cv_batch_id', 'status']);
            $table->index('source_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_batch_items');
    }
};
