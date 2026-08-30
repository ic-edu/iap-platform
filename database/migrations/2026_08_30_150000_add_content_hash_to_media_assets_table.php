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
        Schema::table('media_assets', function (Blueprint $table) {
            if (!Schema::hasColumn('media_assets', 'content_hash')) {
                $table->string('content_hash', 64)->nullable()->after('mime_type')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            if (Schema::hasColumn('media_assets', 'content_hash')) {
                $table->dropIndex(['content_hash']);
                $table->dropColumn('content_hash');
            }
        });
    }
};
