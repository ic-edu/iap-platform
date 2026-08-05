<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            if (!Schema::hasColumn('media_assets', 'category')) {
                $table->string('category')->nullable()->after('type');
            }
            if (!Schema::hasColumn('media_assets', 'sub_category')) {
                $table->string('sub_category')->nullable()->after('category');
            }
            if (!Schema::hasColumn('media_assets', 'exam_type')) {
                $table->string('exam_type', 30)->default('general')->after('sub_category');
            }
            if (!Schema::hasColumn('media_assets', 'difficulty')) {
                $table->string('difficulty', 20)->default('medium')->after('exam_type');
            }
            if (!Schema::hasColumn('media_assets', 'tags')) {
                $table->json('tags')->nullable()->after('difficulty');
            }
            if (!Schema::hasColumn('media_assets', 'approval_status')) {
                $table->string('approval_status', 30)->default('approved')->after('status');
            }
            if (!Schema::hasColumn('media_assets', 'version')) {
                $table->string('version', 20)->default('1.0')->after('approval_status');
            }
            if (!Schema::hasColumn('media_assets', 'content_text')) {
                $table->text('content_text')->nullable()->after('description');
            }
        });

        Schema::table('questions', function (Blueprint $table) {
            if (!Schema::hasColumn('questions', 'media_asset_id')) {
                $table->string('media_asset_id', 36)->nullable()->after('question_bank_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'sub_category',
                'exam_type',
                'difficulty',
                'tags',
                'approval_status',
                'version',
                'content_text',
            ]);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('media_asset_id');
        });
    }
};
