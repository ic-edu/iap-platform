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
        Schema::table('passages', function (Blueprint $table) {
            if (!Schema::hasColumn('passages', 'media_asset_id')) {
                $table->string('media_asset_id', 36)->nullable()->after('document_type')->index();
            }
            if (!Schema::hasColumn('passages', 'image_url')) {
                $table->string('image_url')->nullable()->after('media_asset_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('passages', function (Blueprint $table) {
            if (Schema::hasColumn('passages', 'image_url')) {
                $table->dropColumn('image_url');
            }
            if (Schema::hasColumn('passages', 'media_asset_id')) {
                $table->dropColumn('media_asset_id');
            }
        });
    }
};
