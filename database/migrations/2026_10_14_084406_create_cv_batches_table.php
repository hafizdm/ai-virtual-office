<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_batches', function (Blueprint $table) {
            $table->id();

            // Pengguna yang membuat pekerjaan CV
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Template Word yang digunakan
            $table->string('template_path');

            $table->string('template_original_name');

            // Folder SharePoint sumber CV
            $table->text('source_folder');

            // Status keseluruhan pekerjaan
            $table->string('status')->default('draft');

            // Ringkasan progres
            $table->unsignedInteger('total_files')->default(0);
            $table->unsignedInteger('processed_files')->default(0);
            $table->unsignedInteger('successful_files')->default(0);
            $table->unsignedInteger('failed_files')->default(0);

            // Waktu konfirmasi dan penyelesaian
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_batches');
    }
};
