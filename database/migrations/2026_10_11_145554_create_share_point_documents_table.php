<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('share_point_documents', function (Blueprint $table) {
            $table->id();

            // Microsoft Graph identifiers
            $table->string('site_id');
            $table->string('drive_id');
            $table->string('item_id');

            // Document metadata
            $table->string('name');
            $table->string('extension', 20)->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            // SharePoint location
            $table->text('path')->nullable();
            $table->text('web_url')->nullable();
            $table->string('parent_item_id')->nullable();

            // Synchronization metadata
            $table->timestamp('last_modified_at')->nullable();
            $table->string('etag')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            // Prevent duplicate index entries for the same Graph item.
            $table->unique(
                ['drive_id', 'item_id'],
                'share_point_documents_drive_item_unique'
            );

            $table->index('name');
            $table->index('extension');
            $table->index('last_modified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('share_point_documents');
    }
};
