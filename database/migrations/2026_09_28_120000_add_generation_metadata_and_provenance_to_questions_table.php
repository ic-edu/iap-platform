<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (!Schema::hasColumn('questions', 'proficiency_target')) {
                $table->string('proficiency_target', 30)->nullable()->after('difficulty_detected_at');
            }
            if (!Schema::hasColumn('questions', 'content_mode')) {
                $table->string('content_mode', 30)->nullable()->after('proficiency_target');
            }
            if (!Schema::hasColumn('questions', 'domain')) {
                $table->string('domain', 50)->nullable()->after('content_mode');
            }
            if (!Schema::hasColumn('questions', 'construct')) {
                $table->string('construct', 50)->nullable()->after('domain');
            }
            if (!Schema::hasColumn('questions', 'context')) {
                $table->string('context', 50)->nullable()->after('construct');
            }
            if (!Schema::hasColumn('questions', 'content_origin')) {
                $table->string('content_origin', 30)->nullable()->after('context');
            }
            if (!Schema::hasColumn('questions', 'generation_batch_id')) {
                $table->string('generation_batch_id', 50)->nullable()->after('content_origin');
            }
            if (!Schema::hasColumn('questions', 'generation_metadata')) {
                $table->json('generation_metadata')->nullable()->after('generation_batch_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $columns = array_filter([
                'proficiency_target',
                'content_mode',
                'domain',
                'construct',
                'context',
                'content_origin',
                'generation_batch_id',
                'generation_metadata',
            ], fn ($col) => Schema::hasColumn('questions', $col));

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
