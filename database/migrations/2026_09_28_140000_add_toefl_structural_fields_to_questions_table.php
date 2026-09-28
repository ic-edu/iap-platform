<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (!Schema::hasColumn('questions', 'task_type')) {
                $table->string('task_type', 50)->nullable()->after('part_number');
            }
            if (!Schema::hasColumn('questions', 'claim')) {
                $table->string('claim', 100)->nullable()->after('task_type');
            }
            if (!Schema::hasColumn('questions', 'skill')) {
                $table->string('skill', 50)->nullable()->after('claim');
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $columns = array_filter([
                'task_type',
                'claim',
                'skill',
            ], fn ($col) => Schema::hasColumn('questions', $col));

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
