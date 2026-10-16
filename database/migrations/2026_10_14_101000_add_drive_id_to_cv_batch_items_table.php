<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cv_batch_items', function (Blueprint $table) {
            $table->string('drive_id')->nullable()->after('source_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('cv_batch_items', function (Blueprint $table) {
            $table->dropColumn('drive_id');
        });
    }
};
