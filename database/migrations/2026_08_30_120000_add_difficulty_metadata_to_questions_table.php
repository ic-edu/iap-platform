<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (!Schema::hasColumn('questions', 'difficulty_score')) {
                $table->unsignedTinyInteger('difficulty_score')->nullable()->after('difficulty');
            }
            if (!Schema::hasColumn('questions', 'difficulty_status')) {
                $table->string('difficulty_status', 20)->default('final')->after('difficulty_score');
            }
            if (!Schema::hasColumn('questions', 'difficulty_source')) {
                $table->string('difficulty_source', 20)->default('auto')->after('difficulty_status');
            }
            if (!Schema::hasColumn('questions', 'difficulty_factors')) {
                $table->json('difficulty_factors')->nullable()->after('difficulty_source');
            }
            if (!Schema::hasColumn('questions', 'difficulty_detected_at')) {
                $table->timestamp('difficulty_detected_at')->nullable()->after('difficulty_factors');
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $columns = array_filter([
                'difficulty_score',
                'difficulty_status',
                'difficulty_source',
                'difficulty_factors',
                'difficulty_detected_at',
            ], fn ($col) => Schema::hasColumn('questions', $col));

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
