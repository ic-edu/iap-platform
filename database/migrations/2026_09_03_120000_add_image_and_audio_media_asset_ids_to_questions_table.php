<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (!Schema::hasColumn('questions', 'image_media_asset_id')) {
                $table->string('image_media_asset_id', 36)->nullable()->after('media_asset_id')->index();
            }
            if (!Schema::hasColumn('questions', 'audio_media_asset_id')) {
                $table->string('audio_media_asset_id', 36)->nullable()->after('image_media_asset_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (Schema::hasColumn('questions', 'image_media_asset_id')) {
                $table->dropColumn('image_media_asset_id');
            }
            if (Schema::hasColumn('questions', 'audio_media_asset_id')) {
                $table->dropColumn('audio_media_asset_id');
            }
        });
    }
};
