<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (!Schema::hasColumn('questions', 'assessment_family')) {
                $table->string('assessment_family', 40)->nullable()->after('question_type');
            }
            if (!Schema::hasColumn('questions', 'assessment_standard_id')) {
                $table->string('assessment_standard_id', 50)->nullable()->index()->after('assessment_family');
            }
            if (!Schema::hasColumn('questions', 'standard_version')) {
                $table->string('standard_version', 30)->nullable()->after('assessment_standard_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $columns = array_filter([
                'assessment_family',
                'assessment_standard_id',
                'standard_version',
            ], fn ($col) => Schema::hasColumn('questions', $col));

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
